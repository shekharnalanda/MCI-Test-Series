<?php

namespace App\Services;

use App\Models\EmailOtp;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use RuntimeException;

class EmailOtpService
{
    public function send(
        string $email,
        string $purpose = 'admission',
        ?string $ip = null,
        ?string $userAgent = null
    ): void {
        EmailOtp::where('email', $email)
            ->where('purpose', $purpose)
            ->whereNull('verified_at')
            ->delete();

        $otp = (string) random_int(100000, 999999);

        EmailOtp::create([
            'email' => $email,
            'purpose' => $purpose,
            'otp_hash' => Hash::make($otp),
            'expires_at' => now()->addMinutes(10),
            'request_ip' => $ip,
            'user_agent' => $userAgent,
        ]);

        Mail::raw(
            "Your MCI Test Series verification OTP is {$otp}. It is valid for 10 minutes. Do not share this OTP.",
            function ($message) use ($email) {
                $message->to($email)
                    ->subject('MCI Test Series - Email Verification OTP');
            }
        );
    }

    public function verify(
        string $email,
        string $otp,
        string $purpose = 'admission'
    ): bool {
        $record = EmailOtp::where('email', $email)
            ->where('purpose', $purpose)
            ->whereNull('verified_at')
            ->latest('id')
            ->first();

        if (!$record || $record->expires_at->isPast()) {
            return false;
        }

        if ($record->attempts >= 5) {
            return false;
        }

        $record->increment('attempts');

        if (!Hash::check($otp, $record->otp_hash)) {
            return false;
        }

        $record->update([
            'verified_at' => now(),
        ]);

        return true;
    }

    public function requireVerified(
        string $email,
        string $purpose = 'admission'
    ): EmailOtp {
        $record = EmailOtp::where('email', $email)
            ->where('purpose', $purpose)
            ->whereNotNull('verified_at')
            ->latest('verified_at')
            ->first();

        if (!$record) {
            throw new RuntimeException('Email OTP verification is required.');
        }

        return $record;
    }
}
