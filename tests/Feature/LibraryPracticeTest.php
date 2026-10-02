<?php

namespace Tests\Feature;

use App\Models\Test;
use App\Models\TestAttempt;
use App\Models\User;
use App\Services\LibraryPracticeBridge;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class LibraryPracticeTest extends TestCase
{
    use RefreshDatabase;

    private string $privatePath;

    private object $identity;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setTimezone('Asia/Kolkata')->setDate(2026, 10, 5)->startOfDay());
        $this->seed(DatabaseSeeder::class);
        $this->privatePath = sys_get_temp_dir().'/library-practice-test-'.bin2hex(random_bytes(6));
        mkdir($this->privatePath, 0700);
        mkdir($this->privatePath.'/tickets', 0700);
        touch($this->privatePath.'/source.sqlite');
        $connection = ['driver' => 'sqlite', 'database' => $this->privatePath.'/source.sqlite', 'prefix' => '', 'foreign_key_constraints' => true];
        config(['library-practice.bridge_path' => $this->privatePath.'/config.json', 'database.connections.library_practice_source' => $connection]);
        $schema = Schema::connection('library_practice_source');
        $schema->create('library_student_sessions', function ($t) {
            $t->unsignedBigInteger('student_id')->primary();
            $t->string('token_hash');
            $t->timestamp('expires_at');
            $t->timestamp('last_seen_at');
            $t->string('practice_session_hash')->nullable();
            $t->timestamp('practice_expires_at')->nullable();
            $t->timestamps();
        });
        $schema->create('users', function ($t) {
            $t->id();
            $t->string('role');
            $t->boolean('status');
        });
        $schema->create('students', function ($t) {
            $t->id();
            $t->unsignedBigInteger('user_id');
            $t->string('student_code');
            $t->string('name');
            $t->unsignedBigInteger('branch_id');
            $t->string('status');
        });
        $schema->create('student_memberships', function ($t) {
            $t->id();
            $t->unsignedBigInteger('student_id');
            $t->date('start_date');
            $t->date('expiry_date');
            $t->decimal('final_fee');
            $t->string('status');
        });
        $schema->create('payments', function ($t) {
            $t->id();
            $t->unsignedBigInteger('student_membership_id');
            $t->decimal('amount');
            $t->string('payment_status');
        });
        $schema->create('payment_adjustments', function ($t) {
            $t->id();
            $t->unsignedBigInteger('payment_id');
            $t->decimal('amount');
        });
        file_put_contents($this->privatePath.'/config.json', json_encode(['bridge_id' => str_repeat('a', 32), 'secret' => str_repeat('b', 64), 'test_url' => 'https://test.mciedu.com', 'library_connection' => $connection]));
        chmod($this->privatePath.'/config.json', 0600);
        $db = DB::connection('library_practice_source');
        $db->table('library_student_sessions')->insert(['student_id' => 1, 'token_hash' => str_repeat('c', 64), 'expires_at' => now()->addMinutes(120), 'last_seen_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
        $db->table('users')->insert(['id' => 1, 'role' => 'student', 'status' => true]);
        $db->table('students')->insert(['id' => 1, 'user_id' => 1, 'student_code' => 'CNET-001', 'name' => 'Amit Kumar', 'branch_id' => 1, 'status' => 'active']);
        $db->table('student_memberships')->insert(['id' => 1, 'student_id' => 1, 'start_date' => '2026-10-01', 'expiry_date' => '2026-12-31', 'final_fee' => 300, 'status' => 'active']);
        $db->table('payments')->insert(['id' => 1, 'student_membership_id' => 1, 'amount' => 300, 'payment_status' => 'paid']);
        $this->identity = app(LibraryPracticeBridge::class)->identity(1, 'CNET-001', 1);
    }

    protected function tearDown(): void
    {
        DB::purge('library_practice_source');
        File::deleteDirectory($this->privatePath);
        parent::tearDown();
    }

    private function loginLibrary(): object
    {
        $url = app(LibraryPracticeBridge::class)->issue($this->identity, str_repeat('c', 64));
        $this->get('/library-practice/enter?'.parse_url($url, PHP_URL_QUERY))->assertRedirect(route('library-practice.index'));

        return DB::table('library_practice_accounts')->first();
    }

    private function availableTest(string $title = 'Practice'): Test
    {
        $source = Test::where('is_demo', true)->firstOrFail();
        $test = $source->replicate();
        $test->title = $title;
        $test->is_demo = false;
        $test->save();
        foreach ($source->questions as $question) {
            $test->questions()->attach($question->id, ['sort_order' => 1, 'marks' => 1, 'negative_marks' => 0]);
        }

        return $test;
    }

    public function test_catalog_filters_remain_fresh_and_server_timing_is_exposed(): void
    {
        $test = $this->availableTest('Visible free practice');
        $this->loginLibrary();
        $this->get('/library-practice')->assertOk()->assertSee('Visible free practice')->assertHeader('Server-Timing');
        $test->update(['is_active' => false]);
        $this->get('/library-practice')->assertOk()->assertDontSee('Visible free practice');
        $test->update(['is_active' => true, 'available_from' => now()->addDay()]);
        $this->get('/library-practice')->assertOk()->assertDontSee('Visible free practice');
        $test->update(['available_from' => null]);
        $this->get('/library-practice?exam='.$test->exam_id)->assertOk()->assertSee('Visible free practice');
    }

    public function test_library_login_is_single_use_and_does_not_replace_existing_paid_or_admin_login(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin);
        $url = app(LibraryPracticeBridge::class)->issue($this->identity, str_repeat('c', 64));
        $path = '/library-practice/enter?'.parse_url($url, PHP_URL_QUERY);
        $this->get($path)->assertRedirect(route('library-practice.index'));
        $this->assertAuthenticatedAs($admin);
        $this->get('/library-practice')->assertOk()->assertSee('Amit Kumar')->assertSee('₹0');
        $this->get($path)->assertForbidden();
        $this->assertSame(1, DB::table('library_practice_accounts')->count());
        $this->assertSame(0, DB::table('student_enrollments')->count());
        $this->assertSame(0, DB::table('payments')->count());
    }

    public function test_expired_or_tampered_login_cannot_be_redeemed(): void
    {
        $url = app(LibraryPracticeBridge::class)->issue($this->identity, str_repeat('c', 64));
        $this->travel(91)->seconds();
        $this->get('/library-practice/enter?'.parse_url($url, PHP_URL_QUERY))->assertForbidden();
        $this->travelBack();
        $url = app(LibraryPracticeBridge::class)->issue($this->identity, str_repeat('c', 64));
        parse_str(parse_url($url, PHP_URL_QUERY), $query);
        $path = $this->privatePath.'/tickets/'.hash('sha256', $query['ticket']).'.json';
        $ticket = json_decode(file_get_contents($path), true);
        $ticket['payload'] = str_replace('CNET-001', 'OTHER-01', $ticket['payload']);
        file_put_contents($path, json_encode($ticket));
        $this->get('/library-practice/enter?'.parse_url($url, PHP_URL_QUERY))->assertForbidden();
        $this->assertSame(0, DB::table('library_practice_accounts')->count());
    }

    public function test_ten_selection_limit_duplicate_selection_and_safe_replacement(): void
    {
        $account = $this->loginLibrary();
        $tests = [];
        for ($i = 0; $i < 11; $i++) {
            $tests[] = $this->availableTest('Set '.$i);
        }
        foreach (array_slice($tests, 0, 10) as $test) {
            $this->post('/library-practice/tests/'.$test->id.'/select')->assertSessionHasNoErrors();
        }
        $this->post('/library-practice/tests/'.$tests[0]->id.'/select')->assertSessionHasNoErrors();
        $this->post('/library-practice/tests/'.$tests[10]->id.'/select')->assertSessionHasErrors('practice');
        $this->assertSame(10, DB::table('library_practice_selections')->count());
        $this->delete('/library-practice/tests/'.$tests[0]->id.'/select')->assertSessionHasNoErrors();
        $this->post('/library-practice/tests/'.$tests[10]->id.'/select')->assertSessionHasNoErrors();
        $this->assertSame(10, DB::table('library_practice_selections')->count());
    }

    public function test_started_set_cannot_free_quota_and_duplicate_start_reuses_attempt(): void
    {
        $this->loginLibrary();
        $test = $this->availableTest();
        $this->post('/library-practice/tests/'.$test->id.'/start')->assertSessionHasErrors('practice');
        $this->post('/library-practice/tests/'.$test->id.'/select')->assertSessionHasNoErrors();
        $this->post('/library-practice/tests/'.$test->id.'/start')->assertRedirect();
        $attempt = TestAttempt::latest('id')->firstOrFail();
        $this->post('/library-practice/tests/'.$test->id.'/start')->assertRedirect(route('library-practice.attempts.show', $attempt));
        $this->assertSame(1, TestAttempt::count());
        $this->delete('/library-practice/tests/'.$test->id.'/select')->assertSessionHasErrors('practice');
        $this->assertSame(1, DB::table('library_practice_selections')->count());
        $this->get('/library-practice/attempts/'.$attempt->id)->assertOk();
    }

    public function test_monthly_reset_preserves_results_and_can_choose_same_test_in_new_month(): void
    {
        $this->loginLibrary();
        $test = $this->availableTest();
        $this->post('/library-practice/tests/'.$test->id.'/select');
        $this->post('/library-practice/tests/'.$test->id.'/start');
        $old = TestAttempt::latest('id')->firstOrFail();
        $this->post('/library-practice/attempts/'.$old->id.'/submit')->assertRedirect();
        $this->travelTo(now()->setDate(2026, 11, 1));
        DB::connection('library_practice_source')->table('library_student_sessions')->update(['expires_at' => now()->addMinutes(120), 'practice_expires_at' => now()->addMinutes(120)]);
        $this->get('/library-practice')->assertOk()->assertSee('2026-11');
        $this->post('/library-practice/tests/'.$test->id.'/select')->assertSessionHasNoErrors();
        $this->post('/library-practice/tests/'.$test->id.'/start')->assertRedirect();
        $this->assertSame(2, TestAttempt::count());
        $this->assertSame(2, DB::table('library_practice_months')->count());
        $this->get('/library-practice/attempts/'.$old->id.'/result')->assertOk();
    }

    public function test_expired_membership_blocks_new_tests_but_allows_existing_attempt_and_result(): void
    {
        $this->loginLibrary();
        $test = $this->availableTest();
        $this->post('/library-practice/tests/'.$test->id.'/select');
        $this->post('/library-practice/tests/'.$test->id.'/start');
        $attempt = TestAttempt::latest('id')->firstOrFail();
        DB::connection('library_practice_source')->table('student_memberships')->update(['status' => 'expired']);
        $this->get('/library-practice')->assertOk()->assertSee('सदस्यता अभी सक्रिय नहीं है');
        $this->post('/library-practice/tests/'.$this->availableTest('Extra')->id.'/select')->assertSessionHasErrors('practice');
        $this->post('/library-practice/tests/'.$test->id.'/start')->assertSessionHasErrors('practice');
        $this->get('/library-practice/attempts/'.$attempt->id)->assertOk();
        $this->post('/library-practice/attempts/'.$attempt->id.'/submit')->assertRedirect();
        $this->get('/library-practice/attempts/'.$attempt->id.'/result')->assertOk();
    }

    public function test_unpaid_after_tenth_and_refunds_remove_new_test_eligibility(): void
    {
        $bridge = app(LibraryPracticeBridge::class);
        DB::connection('library_practice_source')->table('payments')->update(['amount' => 100]);
        $this->travelTo(now('Asia/Kolkata')->setDate(2026, 10, 10)->endOfDay());
        $this->assertTrue($bridge->eligible($this->identity));
        $this->travelTo(now('Asia/Kolkata')->setDate(2026, 10, 11)->startOfDay());
        $this->assertFalse($bridge->eligible($this->identity));
        DB::connection('library_practice_source')->table('payments')->update(['amount' => 300]);
        $this->assertTrue($bridge->eligible($this->identity));
        DB::connection('library_practice_source')->table('payment_adjustments')->insert(['payment_id' => 1, 'amount' => 1]);
        $this->assertFalse($bridge->eligible($this->identity));
    }

    public function test_future_inactive_or_empty_test_cannot_be_selected(): void
    {
        $this->loginLibrary();
        foreach ([['is_active' => false], ['available_from' => now()->addDay()], ['available_until' => now()->subDay()]] as $values) {
            $test = $this->availableTest();
            $test->update($values);
            $this->post('/library-practice/tests/'.$test->id.'/select')->assertSessionHasErrors('practice');
        }
        $empty = Test::create(['title' => 'Empty', 'is_active' => true]);
        $this->post('/library-practice/tests/'.$empty->id.'/select')->assertSessionHasErrors('practice');
        $this->assertSame(0, DB::table('library_practice_selections')->count());
    }

    public function test_another_student_cannot_read_or_submit_library_attempt_and_admin_report_is_protected(): void
    {
        $account = $this->loginLibrary();
        $test = $this->availableTest();
        $this->post('/library-practice/tests/'.$test->id.'/select');
        $this->post('/library-practice/tests/'.$test->id.'/start');
        $attempt = TestAttempt::latest('id')->firstOrFail();
        $db = DB::connection('library_practice_source');
        $db->table('users')->insert(['id' => 2, 'role' => 'student', 'status' => true]);
        $db->table('students')->insert(['id' => 2, 'user_id' => 2, 'student_code' => 'MCI-002', 'name' => 'Priya Kumari', 'branch_id' => 2, 'status' => 'active']);
        $db->table('student_memberships')->insert(['student_id' => 2, 'start_date' => '2026-10-01', 'expiry_date' => '2026-12-31', 'final_fee' => 0, 'status' => 'active']);
        $identity = app(LibraryPracticeBridge::class)->identity(2, 'MCI-002', 2);
        $db->table('library_student_sessions')->insert(['student_id' => 2, 'token_hash' => str_repeat('d', 64), 'expires_at' => now()->addMinutes(120), 'last_seen_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
        $url = app(LibraryPracticeBridge::class)->issue($identity, str_repeat('d', 64));
        $this->get('/library-practice/enter?'.parse_url($url, PHP_URL_QUERY))->assertRedirect();
        $this->get('/library-practice/attempts/'.$attempt->id)->assertForbidden();
        $this->post('/library-practice/attempts/'.$attempt->id.'/submit')->assertForbidden();
        $this->get('/admin/library-practice')->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create(['role' => 'admin', 'is_active' => true]))->get('/admin/library-practice')->assertOk()->assertSee('Amit Kumar');
    }

    public function test_scheduled_answers_remain_private_and_answer_submission_uses_existing_engine(): void
    {
        $this->loginLibrary();
        $test = $this->availableTest();
        $test->update(['answer_visibility' => 'scheduled', 'answers_available_at' => now()->addDay()]);
        $this->post('/library-practice/tests/'.$test->id.'/select');
        $this->post('/library-practice/tests/'.$test->id.'/start');
        $attempt = TestAttempt::latest('id')->firstOrFail();
        $snapshot = $attempt->attemptQuestions()->with('question.options')->first();
        $option = $snapshot->question->options->firstWhere('is_correct', true);
        $this->postJson('/library-practice/attempts/'.$attempt->id.'/answer', ['question_id' => $snapshot->question_id, 'selected_option_id' => $option->id])->assertOk()->assertJson(['saved' => true]);
        $this->post('/library-practice/attempts/'.$attempt->id.'/submit')->assertRedirect();
        $this->get('/library-practice/attempts/'.$attempt->id.'/result')->assertOk()->assertSee('Answer review is not available yet')->assertDontSee('Question-wise Review');
        $this->assertSame(1, $attempt->fresh()->correct_answers);
    }

    public function test_second_practice_browser_is_blocked_and_logout_frees_only_practice_binding(): void
    {
        $this->loginLibrary();
        $hash = DB::connection('library_practice_source')->table('library_student_sessions')->value('practice_session_hash');
        $saved = $this->app['session']->driver()->all();
        $this->flushSession();
        $url = app(LibraryPracticeBridge::class)->issue($this->identity, str_repeat('c', 64));
        $this->get('/library-practice/enter?'.parse_url($url, PHP_URL_QUERY))->assertForbidden();
        $this->assertSame($hash, DB::connection('library_practice_source')->table('library_student_sessions')->value('practice_session_hash'));
        $this->withSession($saved)->post('/library-practice/logout')->assertRedirect();
        $this->assertNull(DB::connection('library_practice_source')->table('library_student_sessions')->value('practice_session_hash'));
        $this->assertSame(str_repeat('c', 64), DB::connection('library_practice_source')->table('library_student_sessions')->value('token_hash'));
    }

    public function test_closing_library_session_blocks_old_test_read_answer_and_submit_requests(): void
    {
        $this->loginLibrary();
        $test = $this->availableTest();
        $this->post('/library-practice/tests/'.$test->id.'/select')->assertRedirect();
        $this->post('/library-practice/tests/'.$test->id.'/start')->assertRedirect();
        $attempt = TestAttempt::latest('id')->firstOrFail();
        DB::connection('library_practice_source')->table('library_student_sessions')->delete();
        $this->get('/library-practice/attempts/'.$attempt->id)->assertForbidden();
        $this->post('/library-practice/attempts/'.$attempt->id.'/submit')->assertForbidden();
        $this->get('/library-practice')->assertForbidden();
        $this->assertSame('started', $attempt->fresh()->status);
    }

    public function test_old_library_ticket_cannot_be_used_after_device_lease_changes(): void
    {
        $url = app(LibraryPracticeBridge::class)->issue($this->identity, str_repeat('c', 64));
        DB::connection('library_practice_source')->table('library_student_sessions')->update(['token_hash' => str_repeat('d', 64)]);
        $this->get('/library-practice/enter?'.parse_url($url, PHP_URL_QUERY))->assertForbidden();
        $this->assertSame(0, DB::table('library_practice_accounts')->count());
    }
}
