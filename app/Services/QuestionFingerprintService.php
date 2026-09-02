<?php

namespace App\Services;

class QuestionFingerprintService
{
    public function normalize(string $text): string
    {
        $text = mb_strtolower(trim($text));

        $text = preg_replace('/\s+/u', ' ', $text);

        $text = preg_replace(
            '/[^\p{L}\p{N}\s]/u',
            '',
            $text
        );

        return trim($text);
    }

    public function hash(string $text): string
    {
        return hash(
            'sha256',
            $this->normalize($text)
        );
    }
}
