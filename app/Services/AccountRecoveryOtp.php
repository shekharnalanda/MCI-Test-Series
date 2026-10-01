<?php

namespace App\Services;

use App\Mail\AccountRecoveryCode;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class AccountRecoveryOtp
{
    public function issue(string $email, string $purpose, array $payload, string $sessionId, string $ip): string
    {
        if (in_array(config('mail.default'), ['log', 'array'], true)) {
            throw new \RuntimeException('Email delivery is not configured. Please contact the institution.');
        }
        $email = Str::lower(trim($email));
        $id = (string) Str::uuid();
        $code = (string) random_int(100000, 999999);
        DB::transaction(function () use ($email, $purpose, $payload, $sessionId, $id, $code) {
            // Persist limits independently of browser cookies and IP rotation.
            $key = hash('sha256', $purpose.'|'.$email);
            DB::table('mci_recovery_limits')->insertOrIgnore(['key' => $key, 'sent_at' => 0]);
            $limit = DB::table('mci_recovery_limits')->where('key', $key)->lockForUpdate()->first();
            if ($limit->sent_at > time() - 60) {
                throw new \RuntimeException('Please wait one minute before requesting another code.');
            }
            DB::table('mci_recovery_limits')->where('key', $key)->update(['sent_at' => time()]);
            DB::table('mci_recovery_challenges')->insert([
                'id' => $id, 'email' => $email, 'purpose' => $purpose,
                'code_hash' => Hash::make($code), 'payload' => json_encode($payload),
                'session_hash' => hash('sha256', $sessionId), 'attempts' => 0,
                'expires_at' => time() + 600, 'consumed_at' => null,
            ]);
        });
        try {
            Mail::to($email)->send(new AccountRecoveryCode($code, (string) config('app.name')));
        } catch (\Throwable $e) {
            DB::table('mci_recovery_challenges')->where('id', $id)->update(['consumed_at' => time()]);
            report($e);
            throw new \RuntimeException('Email delivery is unavailable. Please try again later.');
        }

        return $id;
    }

    public function consume(string $id, string $code, string $purpose, string $sessionId, callable $action): mixed
    {
        // Return failures from the transaction so an invalid attempt is committed.
        $result = DB::transaction(function () use ($id, $code, $purpose, $sessionId, $action) {
            $row = DB::table('mci_recovery_challenges')->where('id', $id)->lockForUpdate()->first();
            if (! $row || $row->consumed_at !== null || $row->expires_at < time()
                || $row->attempts >= 5 || $row->purpose !== $purpose
                || ! hash_equals($row->session_hash, hash('sha256', $sessionId))) {
                return ['ok' => false];
            }
            DB::table('mci_recovery_challenges')->where('id', $id)->increment('attempts');
            if (! Hash::check($code, $row->code_hash)) {
                return ['ok' => false];
            }
            $payload = json_decode($row->payload, true, 512, JSON_THROW_ON_ERROR);
            DB::table('mci_recovery_challenges')->where('id', $id)->update(['consumed_at' => time()]);

            return ['ok' => true, 'value' => $action($payload, $row->email)];
        });
        if (! $result['ok']) {
            throw new \RuntimeException('The code is invalid, expired, or already used.');
        }

        return $result['value'];
    }
}
