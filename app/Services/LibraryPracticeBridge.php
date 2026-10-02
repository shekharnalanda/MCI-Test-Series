<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/** Same-account bridge: private configuration and single-use, short-lived tickets. */
class LibraryPracticeBridge
{
    public function configuration(): array
    {
        $path = config('library-practice.bridge_path');
        if (! is_file($path) || is_link($path) || (fileperms($path) & 0077)) {
            throw new RuntimeException('Online practice is not configured. Please contact the library desk.');
        }
        $config = json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        if (! preg_match('/^[a-f0-9]{32}$/', $config['bridge_id'] ?? '')
            || ! preg_match('/^[a-f0-9]{64}$/', $config['secret'] ?? '')
            || ($config['test_url'] ?? '') !== 'https://test.mciedu.com'
            || ! in_array($config['library_connection']['driver'] ?? '', ['mysql', 'sqlite'], true)) {
            throw new RuntimeException('Online practice configuration needs review.');
        }

        return $config;
    }

    public function identity(int $id, string $code, int $userId): ?object
    {
        $config = $this->configuration();
        $name = 'library_practice_source';
        if (config('database.connections.'.$name) !== $config['library_connection']) {
            DB::purge($name);
            config(['database.connections.'.$name => $config['library_connection']]);
        }
        $db = DB::connection($name);

        // Only read the library connection; never update another site's tables.
        return $db->table('students as s')->join('users as u', 'u.id', '=', 's.user_id')
            ->where('s.id', $id)->where('s.student_code', $code)->where('u.id', $userId)
            ->where('s.status', 'active')->where('u.role', 'student')->where('u.status', true)
            ->select('s.id', 's.student_code', 's.name', 's.branch_id', 's.user_id')->first();
    }

    public function eligible(object $identity): bool
    {
        $this->configuration();
        $db = DB::connection('library_practice_source');
        $date = CarbonImmutable::now('Asia/Kolkata')->toDateString();
        $memberships = $db->table('student_memberships')->where('student_id', $identity->id)
            ->where('status', 'active')->whereDate('start_date', '<=', $date)
            ->whereDate('expiry_date', '>=', $date)->get();
        foreach ($memberships as $membership) {
            $start = CarbonImmutable::parse($membership->start_date, 'Asia/Kolkata');
            $deadline = max($start->toDateString(), $start->startOfMonth()->addDays(9)->toDateString());
            if ($date <= $deadline) {
                return true;
            }
            $payments = $db->table('payments')->where('student_membership_id', $membership->id)
                ->whereIn('payment_status', ['paid', 'partial']);
            $gross = (float) (clone $payments)->sum('amount');
            $adjustments = (float) $db->table('payment_adjustments')
                ->whereIn('payment_id', (clone $payments)->select('id'))->sum('amount');
            if ((int) round(($gross - $adjustments) * 100) >= (int) round($membership->final_fee * 100)) {
                return true;
            }
        }

        return false;
    }

    public function issue(object $identity): string
    {
        if (! $this->eligible($identity)) {
            throw new RuntimeException('An active library membership is required for free practice.');
        }
        $config = $this->configuration();
        $dir = dirname(config('library-practice.bridge_path')).'/tickets';
        if (! is_dir($dir) || is_link($dir) || (fileperms($dir) & 0077)) {
            throw new RuntimeException('Practice ticket directory needs review.');
        }
        $token = bin2hex(random_bytes(32));
        $payload = json_encode([
            'bridge_id' => $config['bridge_id'], 'student_id' => (int) $identity->id,
            'student_code' => $identity->student_code, 'user_id' => (int) $identity->user_id,
            'issued_at' => now()->getTimestamp(), 'audience' => 'mci-library-practice',
        ], JSON_THROW_ON_ERROR);
        $data = json_encode(['payload' => $payload, 'mac' => hash_hmac('sha256', $payload, $config['secret'])], JSON_THROW_ON_ERROR);
        $path = $dir.'/'.hash('sha256', $token).'.json';
        $handle = fopen($path, 'xb');
        if (! $handle) {
            throw new RuntimeException('Cannot create practice ticket.');
        }
        try {
            if (! chmod($path, 0600) || fwrite($handle, $data) !== strlen($data) || ! fflush($handle)) {
                throw new RuntimeException('Cannot save practice ticket.');
            }
        } finally {
            fclose($handle);
        }
        // Bound expiry cleanup to avoid scanning large directories in one request.
        $scanned = 0;
        foreach (new \DirectoryIterator($dir) as $file) {
            if (++$scanned > 200) {
                break;
            }
            if ($file->isFile() && ! $file->isLink() && $file->getMTime() < time() - 600) {
                unlink($file->getPathname());
            }
        }

        return $config['test_url'].'/library-practice/enter?ticket='.$token;
    }

    public function consume(string $token): object
    {
        $config = $this->configuration();
        if (! preg_match('/^[a-f0-9]{64}$/', $token)) {
            throw new RuntimeException('Invalid practice login.');
        }
        $path = dirname(config('library-practice.bridge_path')).'/tickets/'.hash('sha256', $token).'.json';
        $claimed = $path.'.'.bin2hex(random_bytes(8)).'.used';
        if (! is_file($path) || is_link($path) || ! @rename($path, $claimed)) {
            throw new RuntimeException('This practice login expired or was already used. Open it again from your library panel.');
        }
        try {
            $ticket = json_decode(file_get_contents($claimed), true, 512, JSON_THROW_ON_ERROR);
            $payload = $ticket['payload'] ?? '';
            if (! hash_equals(hash_hmac('sha256', $payload, $config['secret']), $ticket['mac'] ?? '')) {
                throw new RuntimeException('Invalid practice login.');
            }
            $data = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);
            $age = now()->getTimestamp() - ($data['issued_at'] ?? 0);
            if ($age < 0 || $age > 90 || ($data['bridge_id'] ?? '') !== $config['bridge_id']
                || ($data['audience'] ?? '') !== 'mci-library-practice') {
                throw new RuntimeException('This practice login expired.');
            }
            $identity = $this->identity((int) $data['student_id'], (string) $data['student_code'], (int) $data['user_id']);
            if (! $identity || ! $this->eligible($identity)) {
                throw new RuntimeException('Your library membership is not active.');
            }

            return $identity;
        } finally {
            @unlink($claimed);
        }
    }
}
