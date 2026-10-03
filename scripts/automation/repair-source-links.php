<?php

use App\Models\ContentSource;
use App\Models\Question;
use App\Services\TrustedSourcePolicy;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

try {
    $root = $argv[1] ?? '';
    $private = $argv[2] ?? '';
    if (! is_file($root.'/artisan') || ! is_dir($private) || is_link($private)) {
        throw new RuntimeException('A valid application/private backup is required.');
    }
    require $root.'/vendor/autoload.php';
    $app = require $root.'/bootstrap/app.php';
    $app->make(Kernel::class)->bootstrap();
    $manifests = glob('/home4/mcied45x/mci-automation-backups/*/created-content.json');
    sort($manifests);
    $previous = end($manifests);
    if (! $previous || ! str_contains(file_get_contents(dirname($previous).'/install.log'), 'AUTOMATION_COMMITTED')) {
        throw new RuntimeException('The verified originating import manifest is required.');
    }
    $created = json_decode(file_get_contents($previous), true, 512, JSON_THROW_ON_ERROR);
    $protected = json_decode(file_get_contents(dirname($previous).'/protected-records.json'), true, 512, JSON_THROW_ON_ERROR);
    $oldMaximum = (int) $protected['questions']['maximum_id'];
    $ids = $created['question_ids'];
    if (count($ids) < 1 || count($ids) > 50 || min($ids) <= $oldMaximum) {
        throw new RuntimeException('Repair must be confined to newly introduced questions.');
    }
    $published = DB::transaction(function () use ($ids, $private): int {
        $source = ContentSource::where('slug', 'wikidata')->firstOrFail();
        if (! app(TrustedSourcePolicy::class)->canAutoPublishQuestions($source)) {
            throw new RuntimeException('The existing source policy does not permit automatic verification.');
        }
        $questions = Question::whereIn('id', $ids)->lockForUpdate()->with('options')->get();
        if ($questions->count() !== count($ids)) {
            throw new RuntimeException('The introduced import changed.');
        }
        file_put_contents($private.'/new-question-records-before.json', json_encode($questions->toArray(), JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR), LOCK_EX);
        $published = 0;
        foreach ($questions as $question) {
            if ($question->verification_status === 'verified' && $question->is_published) {
                continue;
            }
            $url = preg_replace('#^http://www\.wikidata\.org/entity/(Q[1-9][0-9]*)$#', 'https://www.wikidata.org/entity/$1', (string) $question->source_url);
            $rawDate = $question->getRawOriginal('source_published_at');
            if ($question->content_source_id !== $source->id || $question->source_reference !== 'wikidata-country-legislature'
                || ! preg_match('#^https://www\.wikidata\.org/entity/Q[1-9][0-9]*$#', $url)
                || $question->verification_status !== 'pending' || $question->is_published || ! $question->is_active
                || $question->generation_method !== 'automated' || ! $question->question_text_hi || ! $question->explanation_hi
                || ! $rawDate || strtotime($rawDate) === false || strtotime($rawDate) > now()->timestamp
                || $question->options->count() !== 4 || $question->options->where('is_correct', true)->count() !== 1
                || $question->options->contains(fn ($o) => ! $o->option_text || ! $o->option_text_hi)
                || $question->options->pluck('option_text')->unique()->count() !== 4
                || $question->options->pluck('option_text_hi')->unique()->count() !== 4
                || DB::table('question_test')->where('question_id', $question->id)->exists()
                || DB::table('attempt_questions')->where('question_id', $question->id)->exists()) {
                throw new RuntimeException('A newly introduced question failed the strict provenance/content guard.');
            }
            // The same trusted-source/HTTPS-host/provenance checks used by ingestion apply.
            // Content/options and every older question/test/attempt remain untouched.
            $question->forceFill(['source_url' => $url, 'verification_status' => 'verified', 'auto_publish' => true,
                'is_published' => true, 'published_at' => now(), 'verified_at' => now()])->save();
            $published++;
        }

        return $published;
    });
    printf("VERIFIED | newly introduced questions published=%d | secure official provenance | original question content/options untouched\n", $published);
    printf("SOURCE_LINKS_COMMITTED | published=%d | original bank IDs <=%d unchanged\n", $published, $oldMaximum);
} catch (Throwable $error) {
    fwrite(STDERR, 'STOPPED: Source-link repair rolled back: '.$error->getMessage()."\n");
    exit(1);
}
