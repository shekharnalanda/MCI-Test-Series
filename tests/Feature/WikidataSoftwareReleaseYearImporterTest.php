<?php

namespace Tests\Feature;

use App\Models\Question;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WikidataSoftwareReleaseYearImporterTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_imports_only_unambiguous_bilingual_release_year_facts(): void
    {
        $this->seed();
        Http::fake(['https://www.wikidata.org/*'=>Http::response('ok',200),
            'https://query.wikidata.org/*'=>Http::response($this->response(),200)]);

        $this->artisan('mci:wikidata-software-release-years --limit=20')->assertSuccessful();
        $this->artisan('mci:wikidata-software-release-years --limit=20')->assertSuccessful();
        $questions = Question::where('source_reference','wikidata-software-first-release-year')->get();
        $this->assertCount(4,$questions);
        $this->assertFalse($questions->contains(fn(Question $q)=>str_contains($q->question_text,'Ambiguous')));
        $this->assertCount(4,$questions->first()->options);
        $this->assertCount(1,$questions->first()->options->where('is_correct',true));
        $this->assertTrue($questions->first()->is_verified);
    }

    private function response(): array
    {
        $facts=[['Q1','Software One','सॉफ्टवेयर एक','2001-01-01T00:00:00Z'],
            ['Q2','Software Two','सॉफ्टवेयर दो','2002-01-01T00:00:00Z'],
            ['Q3','Software Three','सॉफ्टवेयर तीन','2003-01-01T00:00:00Z'],
            ['Q4','Software Four','सॉफ्टवेयर चार','2004-01-01T00:00:00Z'],
            ['Q5','Ambiguous','अस्पष्ट','2005-01-01T00:00:00Z'],
            ['Q5','Ambiguous','अस्पष्ट','2006-01-01T00:00:00Z']];

        return ['results'=>['bindings'=>array_map(fn(array $f)=>[
            'software'=>['value'=>'https://www.wikidata.org/entity/'.$f[0]],
            'softwareLabelEn'=>['value'=>$f[1]], 'softwareLabelHi'=>['value'=>$f[2]],
            'release'=>['value'=>$f[3]],
        ],$facts)]];
    }
}
