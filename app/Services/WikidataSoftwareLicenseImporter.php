<?php

namespace App\Services;

use App\Models\ContentSource;
use App\Models\Subject;
use App\Models\Topic;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class WikidataSoftwareLicenseImporter
{
    private const ENDPOINT='https://query.wikidata.org/sparql';

    public function __construct(
        private readonly QuestionIngestionService $ingestion,
        private readonly TrustedSourcePolicy $sourcePolicy,
        private readonly TrustedSourceHealthService $health,
    ) {}

    public function import(int $limit=500,bool $dryRun=false): array
    {
        $limit=max(10,min($limit,500));
        $source=ContentSource::where('slug','wikidata')->where('is_active',true)->firstOrFail();
        $this->health->check($source); $source->refresh();
        if(! $this->sourcePolicy->canGenerateQuestions($source)) {
            throw new RuntimeException('Wikidata has not passed the trusted-source policy.');
        }

        $response=Http::withHeaders(['Accept'=>'application/sparql-results+json'])
            ->withUserAgent('MCI-Test-Series/1.0 (+https://test.mciedu.com)')
            ->timeout(45)->retry(2,750,throw:false)
            ->get(self::ENDPOINT,['query'=>$this->query($limit),'format'=>'json']);
        if(! $response->successful()) throw new RuntimeException('Wikidata query failed with HTTP '.$response->status().'.');

        $facts=collect($response->json('results.bindings',[]))->map(fn(array $row)=>$this->fact($row))->filter()
            ->groupBy('software_url')->filter(fn(Collection $rows)=>$rows->unique('license_url')->count()===1)
            ->map(fn(Collection $rows)=>$rows->first())->values();
        $licenses=$facts->unique('license_url')->values();
        if($facts->count()<4 || $licenses->count()<4) {
            throw new RuntimeException('At least four unambiguous bilingual software titles and distinct licenses are required.');
        }

        $subject=Subject::where('name','Computer Knowledge')->firstOrFail();
        $topic=Topic::where('subject_id',$subject->id)->where('name','Software')->firstOrFail();
        $examIds=$subject->exams()->where('is_active',true)->pluck('exams.id')->all();
        $questions=$facts->map(function(array $fact) use($licenses,$subject,$topic,$examIds): array {
            $options=$licenses->reject(fn(array $c)=>$c['license_url']===$fact['license_url'])
                ->sortBy(fn(array $c)=>hash('sha256',$fact['software_url'].'|'.$c['license_url']))->take(3)->push($fact)
                ->sortBy(fn(array $c)=>hash('sha256',$fact['software_url'].'|option|'.$c['license_url']))->values()
                ->map(fn(array $c)=>['option_text'=>$c['license_en'],'option_text_hi'=>$c['license_hi'],
                    'is_correct'=>$c['license_url']===$fact['license_url']])->all();
            return [
                'question_text'=>"Which license is used by {$fact['software_en']}?",
                'question_text_hi'=>"{$fact['software_hi']} किस लाइसेंस के अंतर्गत उपलब्ध है?",
                'explanation'=>"{$fact['software_en']} uses the {$fact['license_en']} license.",
                'explanation_hi'=>"{$fact['software_hi']} {$fact['license_hi']} लाइसेंस के अंतर्गत उपलब्ध है।",
                'subject_id'=>$subject->id,'topic_id'=>$topic->id,'exam_ids'=>$examIds,
                'difficulty'=>'medium','language'=>'bilingual','source_url'=>$fact['software_url'],
                'source_reference'=>'wikidata-software-license','source_published_at'=>now()->toDateString(),
                'generation_method'=>'automated','options'=>$options,
            ];
        })->all();

        if($dryRun) return ['fetched'=>count($questions),'accepted'=>count($questions),'duplicates'=>0,'rejected'=>0,'dry_run'=>true];
        $batch=$this->ingestion->ingest($questions,$source,'json');
        return ['fetched'=>count($questions),'accepted'=>$batch->accepted_count,'duplicates'=>$batch->duplicate_count,
            'rejected'=>$batch->rejected_count,'dry_run'=>false];
    }

    private function fact(array $row): ?array
    {
        $fact=['software_url'=>data_get($row,'software.value'),'software_en'=>data_get($row,'softwareLabelEn.value'),
            'software_hi'=>data_get($row,'softwareLabelHi.value'),'license_url'=>data_get($row,'license.value'),
            'license_en'=>data_get($row,'licenseLabelEn.value'),'license_hi'=>data_get($row,'licenseLabelHi.value')];
        return collect($fact)->every(fn($v)=>is_string($v)&&trim($v)!=='')?$fact:null;
    }

    private function query(int $limit): string
    {
        return <<<SPARQL
SELECT DISTINCT ?software ?softwareLabelEn ?softwareLabelHi ?license ?licenseLabelEn ?licenseLabelHi WHERE {
  ?software wdt:P275 ?license;
            wdt:P31/wdt:P279* wd:Q7397;
            rdfs:label ?softwareLabelEn;
            rdfs:label ?softwareLabelHi.
  ?license rdfs:label ?licenseLabelEn; rdfs:label ?licenseLabelHi.
  FILTER(LANG(?softwareLabelEn) = "en")
  FILTER(LANG(?softwareLabelHi) = "hi")
  FILTER(LANG(?licenseLabelEn) = "en")
  FILTER(LANG(?licenseLabelHi) = "hi")
}
ORDER BY ?softwareLabelEn ?licenseLabelEn
LIMIT {$limit}
SPARQL;
    }
}
