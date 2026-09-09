<?php

namespace Tests\Feature;

use App\Models\Question;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WikidataOrganizationInceptionYearImporterTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_imports_only_unambiguous_bilingual_inception_year_facts(): void
    {
        $this->seed();
        Http::fake([
            'https://www.wikidata.org/*' => Http::response('ok', 200),
            'https://query.wikidata.org/*' => Http::response($this->response(), 200),
        ]);

        $this->artisan('mci:wikidata-organization-inception-years --limit=20')->assertSuccessful();
        $this->artisan('mci:wikidata-organization-inception-years --limit=20')->assertSuccessful();

        $questions = Question::where('source_reference', 'wikidata-international-organization-inception-year')->get();
        $this->assertCount(4, $questions);
        $this->assertFalse($questions->contains(fn (Question $question) => str_contains($question->question_text, 'Ambiguous')));
        $this->assertCount(4, $questions->first()->options);
        $this->assertCount(1, $questions->first()->options->where('is_correct', true));
        $this->assertTrue($questions->first()->is_verified);
    }

    private function response(): array
    {
        $facts = [
            ['Q1','Organization One','संगठन एक','2001-01-01T00:00:00Z'],
            ['Q2','Organization Two','संगठन दो','2002-01-01T00:00:00Z'],
            ['Q3','Organization Three','संगठन तीन','2003-01-01T00:00:00Z'],
            ['Q4','Organization Four','संगठन चार','2004-01-01T00:00:00Z'],
            ['Q5','Ambiguous','अस्पष्ट','2005-01-01T00:00:00Z'],
            ['Q5','Ambiguous','अस्पष्ट','2006-01-01T00:00:00Z'],
        ];

        return ['results'=>['bindings'=>array_map(fn(array $f)=>[
            'organization'=>['value'=>'https://www.wikidata.org/entity/'.$f[0]],
            'organizationLabelEn'=>['value'=>$f[1]], 'organizationLabelHi'=>['value'=>$f[2]],
            'inception'=>['value'=>$f[3]],
        ],$facts)]];
    }
}
