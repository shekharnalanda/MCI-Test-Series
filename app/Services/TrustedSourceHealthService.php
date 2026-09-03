<?php

namespace App\Services;

use App\Models\ContentSource;
use App\Models\ContentSourceCheck;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Throwable;

class TrustedSourceHealthService
{
    public function check(ContentSource $source): array
    {
        $checkedAt = now();

        if ($source->source_type === 'internal') {
            $source->forceFill([
                'last_checked_at' => $checkedAt,
                'last_success_at' => $checkedAt,
            ])->save();

            return $this->record($source, true, null, 'internal_source', $checkedAt);
        }

        if (! $this->isSecureUrl($source->base_url)) {
            $source->forceFill(['last_checked_at' => $checkedAt])->save();

            return $this->record($source, false, null, 'invalid_https_url', $checkedAt);
        }

        try {
            $response = Http::acceptJson()
                ->withUserAgent('MCI-Test-Series-Source-Monitor/1.0')
                ->timeout(12)
                ->retry(2, 250, throw: false)
                ->get($source->base_url);

            $healthy = $response->status() >= 200 && $response->status() < 400;
            $values = ['last_checked_at' => $checkedAt];

            if ($healthy) {
                $values['last_success_at'] = $checkedAt;
            }

            $source->forceFill($values)->save();

            return $this->record(
                $source,
                $healthy,
                $response->status(),
                $healthy ? 'reachable' : 'http_error',
                $checkedAt
            );
        } catch (ConnectionException|Throwable $exception) {
            $source->forceFill(['last_checked_at' => $checkedAt])->save();

            return $this->record($source, false, null, 'connection_error', $checkedAt);
        }
    }

    private function record(
        ContentSource $source,
        bool $healthy,
        ?int $status,
        string $reason,
        mixed $checkedAt
    ): array {
        $check = ContentSourceCheck::create([
            'content_source_id' => $source->id,
            'healthy' => $healthy,
            'http_status' => $status,
            'reason' => $reason,
            'checked_at' => $checkedAt,
        ]);

        $this->updateQuarantineState($source, $healthy, $reason, $checkedAt);
        $source->refresh();

        return [
            'check_id' => $check->id,
            'source_id' => $source->id,
            'slug' => $source->slug,
            'healthy' => $healthy,
            'status' => $status,
            'reason' => $reason,
            'checked_at' => $source->last_checked_at,
            'last_success_at' => $source->last_success_at,
        ];
    }


    private function updateQuarantineState(
        ContentSource $source,
        bool $healthy,
        string $reason,
        mixed $checkedAt
    ): void {
        if ($source->source_type === 'internal') {
            return;
        }

        if ($healthy) {
            $recentChecks = $source->healthChecks()
                ->latest('checked_at')
                ->limit(3)
                ->pluck('healthy');

            if (
                $source->is_quarantined
                && $recentChecks->count() === 3
                && $recentChecks->every(fn ($value) => (bool) $value)
            ) {
                $source->forceFill([
                    'is_quarantined' => false,
                    'quarantined_at' => null,
                    'quarantine_reason' => null,
                ])->save();
            }

            return;
        }

        $recentChecks = $source->healthChecks()
            ->latest('checked_at')
            ->limit(3)
            ->pluck('healthy');

        if ($recentChecks->count() === 3 && $recentChecks->every(fn ($value) => ! $value)) {
            $source->forceFill([
                'is_quarantined' => true,
                'quarantined_at' => $source->quarantined_at ?? $checkedAt,
                'quarantine_reason' => $reason,
            ])->save();
        }
    }

    private function isSecureUrl(?string $url): bool
    {
        return filled($url)
            && filter_var($url, FILTER_VALIDATE_URL) !== false
            && parse_url($url, PHP_URL_SCHEME) === 'https'
            && filled(parse_url($url, PHP_URL_HOST));
    }
}
