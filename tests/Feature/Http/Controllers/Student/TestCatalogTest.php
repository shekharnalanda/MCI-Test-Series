<?php

namespace Tests\Feature\Http\Controllers\Student;

use App\Models\Exam;
use App\Models\ExamCategory;
use App\Models\Package;
use App\Models\StudentProfile;
use App\Models\Test;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TestCatalogTest extends TestCase
{
    use RefreshDatabase;

    private function student(): User
    {
        $user = User::factory()->create(['role' => 'student', 'is_active' => true]);
        $student = StudentProfile::create(['user_id' => $user->id, 'student_code' => 'CATALOG-001', 'status' => 'active']);
        DB::table('student_enrollments')->insert(['student_profile_id' => $student->id, 'status' => 'active', 'test_limit' => 10]);

        return $user;
    }

    public function test_paid_catalog_shows_only_exams_and_tests_of_the_selected_category(): void
    {
        $student = $this->student();
        $sscCategory = ExamCategory::create(['name' => 'SSC', 'slug' => 'ssc']);
        $policeCategory = ExamCategory::create(['name' => 'Bihar Police', 'slug' => 'bihar-police']);
        $ssc = Exam::create(['exam_category_id' => $sscCategory->id, 'name' => 'SSC CGL', 'slug' => 'ssc-cgl']);
        $police = Exam::create(['exam_category_id' => $policeCategory->id, 'name' => 'Bihar Police SI', 'slug' => 'bihar-police-si']);
        $test = Test::create(['exam_id' => $ssc->id, 'title' => 'SSC CGL Practice']);
        Test::create(['exam_id' => $police->id, 'title' => 'Bihar Police Mock', 'test_type' => 'full_mock']);

        $response = $this->actingAs($student)->get('/student/tests?category='.$sscCategory->id.'&exam='.$police->id);

        $response->assertViewHas('exams', fn ($exams): bool => $exams->pluck('id')->all() === [$ssc->id])
            ->assertViewHas('tests', fn ($tests): bool => $tests->pluck('id')->all() === [$test->id])
            ->assertSee('SSC CGL Practice')->assertDontSee('Bihar Police Mock')
            ->assertSee('data-test-catalog', false)->assertSee('data-clear="exam subject topic type q"', false);
        $this->assertSame(0, DB::table('student_enrollment_tests')->count());
    }

    public function test_exam_restricted_package_cannot_browse_unrelated_exams_or_tests(): void
    {
        $student = $this->student();
        $category = ExamCategory::create(['name' => 'SSC', 'slug' => 'ssc']);
        $allowed = Exam::create(['exam_category_id' => $category->id, 'name' => 'SSC CGL', 'slug' => 'ssc-cgl']);
        $other = Exam::create(['exam_category_id' => $category->id, 'name' => 'SSC CHSL', 'slug' => 'ssc-chsl']);
        $package = Package::create(['exam_id' => $allowed->id, 'name' => 'CGL 10 Pack', 'slug' => 'cgl-pack']);
        DB::table('student_enrollments')->update(['package_id' => $package->id]);
        $visible = Test::create(['exam_id' => $allowed->id, 'title' => 'CGL Set']);
        Test::create(['exam_id' => $other->id, 'title' => 'CHSL Set']);

        $response = $this->actingAs($student)->get('/student/tests?category='.$category->id.'&exam='.$other->id);

        $response->assertViewHas('exams', fn ($exams): bool => $exams->pluck('id')->all() === [$allowed->id])
            ->assertViewHas('tests', fn ($tests): bool => $tests->pluck('id')->all() === [$visible->id])
            ->assertDontSee('CHSL Set');
    }

    public function test_catalog_rejects_invalid_topic_input_and_does_not_change_quota(): void
    {
        $student = $this->student();

        $response = $this->actingAs($student)->from('/student/tests')->get('/student/tests?topic=abc');

        $response->assertRedirect('/student/tests')->assertSessionHasErrors('topic');
        $this->assertSame(0, DB::table('student_enrollment_tests')->count());
    }

    public function test_catalog_escapes_search_text(): void
    {
        $student = $this->student();
        $search = '<script>alert(1)</script>';

        $response = $this->actingAs($student)->get('/student/tests?'.http_build_query(['q' => $search]));

        $response->assertSee($search)->assertDontSee($search, false);
    }

    public function test_unauthenticated_catalog_redirects_to_login(): void
    {
        $this->get('/student/tests')->assertRedirect(route('login'));
    }

    public function test_admin_cannot_open_student_catalog(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin', 'is_active' => true]))->get('/student/tests')->assertForbidden();
    }

    public function test_demo_catalog_does_not_expose_paid_or_inactive_test_options(): void
    {
        $student = $this->student();
        $category = ExamCategory::create(['name' => 'SSC', 'slug' => 'ssc']);
        $demoExam = Exam::create(['exam_category_id' => $category->id, 'name' => 'Demo Exam', 'slug' => 'demo-exam']);
        $paidExam = Exam::create(['exam_category_id' => $category->id, 'name' => 'Paid Exam', 'slug' => 'paid-exam']);
        $demo = Test::create(['exam_id' => $demoExam->id, 'title' => 'Free Demo', 'is_demo' => true]);
        Test::create(['exam_id' => $paidExam->id, 'title' => 'Paid Set']);
        Test::create(['exam_id' => $paidExam->id, 'title' => 'Inactive Demo', 'is_demo' => true, 'is_active' => false]);

        $response = $this->actingAs($student)->withSession(['demo_access' => true])->get('/student/tests?category='.$category->id);

        $response->assertViewHas('exams', fn ($exams): bool => $exams->pluck('id')->all() === [$demoExam->id])
            ->assertViewHas('tests', fn ($tests): bool => $tests->pluck('id')->all() === [$demo->id]);
    }
}
