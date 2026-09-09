<?php

namespace Tests\Feature;

use App\Models\Question;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WikidataSoftwareLicenseImporterTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_imports_only_unambiguous_bilingual_license_facts(): void
    {
        $this->seed();
        Http::fake(['https://www.wikidata.org/*'=>Http::response('ok',200),
            'https://query.wikidata.org/*'=>Http::response($this->response(),200)]);
        $this->artisan('mci:wikidata-software-licenses --limit=20')->assertSuccessful();
        $this->artisan('mci:wikidata-software-licenses --limit=20')->assertSuccessful();
        $questions=Question::where('source_reference','wikidata-software-license')->get();
        $this->assertCount(4,$questions);
        $this->assertFalse($questions->contains(fn(Question $q)=>str_contains($q->question_text,'Ambiguous')));
        $this->assertCount(4,$questions->first()->options);
        $this->assertCount(1,$questions->first()->options->where('is_correct',true));
        $this->assertTrue($questions->first()->is_verified);
    }

    private function response(): array
    {
        $facts=[['Q1','Software One','सॉफ्टवेयर एक','Q101','License One','लाइसेंस एक'],
            ['Q2','Software Two','सॉफ्टवेयर दो','Q102','License Two','लाइसेंस दो'],
            ['Q3','Software Three','सॉफ्टवेयर तीन','Q103','License Three','लाइसेंस तीन'],
            ['Q4','Software Four','सॉफ्टवेयर चार','Q104','License Four','लाइसेंस चार'],
            ['Q5','Ambiguous','अस्पष्ट','Q105','License Five','लाइसेंस पाँच'],
            ['Q5','Ambiguous','अस्पष्ट','Q106','License Six','लाइसेंस छह']];
        return ['results'=>['bindings'=>array_map(fn(array $f)=>[
            'software'=>['value'=>'https://www.wikidata.org/entity/'.$f[0]],
            'softwareLabelEn'=>['value'=>$f[1]],'softwareLabelHi'=>['value'=>$f[2]],
            'license'=>['value'=>'https://www.wikidata.org/entity/'.$f[3]],
            'licenseLabelEn'=>['value'=>$f[4]],'licenseLabelHi'=>['value'=>$f[5]],
        ],$facts)]];
    }
}
