<?php
namespace App\Services;
use App\Models\ContentSource;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Throwable;
class TrustedSourceHealthService
{
    public function check(ContentSource $source): array
    {
        $checkedAt = now();
        if ($source->source_type === 'internal') {
            $source->forceFill(['last_checked_at' => $checkedAt, 'last_success_at' => $checkedAt])->save();
            return $this->result($source, true, null, 'internal_source');
        }
        if (! $this->isSecureUrl($source->base_url)) {
            $source->forceFill(['last_checked_at' => $checkedAt])->save();
            return $this->result($source, false, null, 'invalid_https_url');
        }
        try {
            $response = Http::acceptJson()->withUserAgent('MCI-Test-Series-Source-Monitor/1.0')
                ->timeout(12)->retry(2, 250, throw: false)->get($source->base_url);
            $healthy = $response->status() >= 200 && $response->status() < 400;
            $values = ['last_checked_at' => $checkedAt];
            if ($healthy) {
                $values['last_success_at'] = $checkedAt;
            }
            $source->forceFill($values)->save();
            return $this->result($source, $healthy, $response->status(), $healthy ? 'reachable' : 'http_error');
        } catch (ConnectionException|Throwable $exception) {
            $source->forceFill(['last_checked_at' => $checkedAt])->save();
            return $this->result($source, false, null, 'connection_error');
        }
    }
    private function result(ContentSource $source, bool $healthy, ?int $status, string $reason): array
    {
        return ['source_id' => $source->id, 'slug' => $source->slug, 'healthy' => $healthy, 'status' => $status, 'reason' => $reason, 'checked_at' => $source->last_checked_at, 'last_success_at' => $source->last_success_at];
    }
    private function isSecureUrl(?string $url): bool
    {
        return filled($url) && filter_var($url, FILTER_VALIDATE_URL) !== false
            && parse_url($url, PHP_URL_SCHEME) === 'https' && filled(parse_url($url, PHP_URL_HOST));
    }
}
