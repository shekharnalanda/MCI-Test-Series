<?php

namespace App\Services;

use App\Models\ContentSource;

class TrustedSourcePolicy
{
    public const MIN_QUESTION_TRUST = 90;
    public const MIN_AUTO_PUBLISH_TRUST = 95;

    public function audit(ContentSource $source): array
    {
        $reasons = [];

        if (! $source->is_active) {
            $reasons[] = 'source_inactive';
        }

        if ($source->is_quarantined) {
            $reasons[] = 'source_quarantined';
        }

        if ((int) $source->trust_score < self::MIN_QUESTION_TRUST) {
            $reasons[] = 'trust_score_below_90';
        }

        if (! $source->allow_question_generation) {
            $reasons[] = 'question_generation_not_allowed';
        }
        if ($source->source_type !== 'internal') {
            $latestCheck = $source->healthChecks()->latest('checked_at')->first();
            $healthCutoff = now()->subHours(24);

            if (! $latestCheck && $source->created_at?->lt($healthCutoff)) {
                $reasons[] = 'health_check_missing';
            } elseif ($latestCheck && $latestCheck->checked_at->lt($healthCutoff)) {
                $reasons[] = 'health_check_stale';
            } elseif ($latestCheck && ! $latestCheck->healthy) {
                $reasons[] = 'health_check_failed';
            }
        }


        if ($source->source_type !== 'internal') {
            if (! $this->isSecureUrl($source->base_url)) {
                $reasons[] = 'secure_base_url_required';
            }

            if (! filled($source->license_note)) {
                $reasons[] = 'license_note_required';
            }
        }

        if ($source->source_type === 'open_knowledge'
            && ! $this->hasOpenLicense($source->license_note)) {
            $reasons[] = 'open_license_evidence_required';
        }

        return [
            'trusted_for_questions' => $reasons === [],
            'trusted_for_auto_publish' => $reasons === []
                && $source->allow_current_affairs
                && $source->auto_publish_allowed
                && (int) $source->trust_score >= self::MIN_AUTO_PUBLISH_TRUST,
            'reasons' => $reasons,
        ];
    }

    public function canGenerateQuestions(ContentSource $source): bool
    {
        return $this->audit($source)['trusted_for_questions'];
    }

    public function canAutoPublishCurrentAffairs(ContentSource $source): bool
    {
        return $this->audit($source)['trusted_for_auto_publish'];
    }

    private function isSecureUrl(?string $url): bool
    {
        return filled($url)
            && filter_var($url, FILTER_VALIDATE_URL) !== false
            && parse_url($url, PHP_URL_SCHEME) === 'https'
            && filled(parse_url($url, PHP_URL_HOST));
    }

    private function hasOpenLicense(?string $note): bool
    {
        if (! filled($note)) {
            return false;
        }

        return preg_match('/\b(CC0|CC BY|Creative Commons|public domain|open license)\b/i', $note) === 1;
    }
}
