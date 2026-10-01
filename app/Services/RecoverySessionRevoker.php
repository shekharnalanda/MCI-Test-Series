<?php

namespace App\Services;

use Illuminate\Support\Facades\Crypt;

class RecoverySessionRevoker
{
    public function revokeFileSessions(int|string $userId): void
    {
        if (config('session.driver') !== 'file') {
            return;
        }
        $directory = config('session.files');
        if (! is_string($directory) || ! is_dir($directory) || ! is_readable($directory)) {
            throw new \RuntimeException('Session storage is unavailable. Please contact the institution.');
        }
        foreach (new \DirectoryIterator($directory) as $file) {
            if (! $file->isFile() || $file->isLink() || ! preg_match('/^[a-zA-Z0-9]{40}$/D', $file->getFilename())) {
                continue;
            }
            $contents = file_get_contents($file->getPathname());
            if ($contents === false) {
                throw new \RuntimeException('Session storage is unavailable. Please contact the institution.');
            }
            try {
                if (config('session.encrypt')) {
                    $contents = Crypt::decrypt($contents);
                }
                $session = @unserialize($contents, ['allowed_classes' => false]);
            } catch (\Throwable $e) {
                continue;
            }
            if (! is_array($session)) {
                continue;
            }
            foreach ($session as $key => $value) {
                if (is_string($key) && str_starts_with($key, 'login_') && is_scalar($value) && (string) $value === (string) $userId) {
                    if (! unlink($file->getPathname())) {
                        throw new \RuntimeException('Existing sessions could not be revoked. Please contact the institution.');
                    }
                    break;
                }
            }
        }
    }
}
