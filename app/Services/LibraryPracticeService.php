<?php

namespace App\Services;

use App\Models\StudentProfile;
use App\Models\Test;
use App\Models\TestAttempt;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class LibraryPracticeService
{
    public const LIMIT = 10;

    public const TERMS_VERSION = 'library-monthly-10-v1';

    public function linkIdentity(object $identity, LibraryPracticeBridge $bridge): object
    {
        $bridgeId = $bridge->configuration()['bridge_id'];

        return DB::transaction(function () use ($identity, $bridgeId) {
            $account = DB::table('library_practice_accounts')->where('bridge_id', $bridgeId)
                ->where('library_student_id', $identity->id)->lockForUpdate()->first();
            if ($account) {
                if ((int) $account->library_user_id !== (int) $identity->user_id || $account->student_code !== $identity->student_code) {
                    throw new RuntimeException('Library account identity changed. Please contact the library desk.');
                }
                DB::table('library_practice_accounts')->where('id', $account->id)
                    ->update(['student_name' => $identity->name, 'branch_id' => $identity->branch_id, 'updated_at' => now()]);

                return DB::table('library_practice_accounts')->find($account->id);
            }
            $key = hash('sha256', $bridgeId.':'.$identity->id.':'.$identity->user_id);
            $user = User::create(['name' => $identity->name, 'email' => 'library-'.$key.'@benefits.invalid',
                'password' => Str::random(64), 'role' => 'student', 'is_active' => true]);
            $profile = StudentProfile::create(['user_id' => $user->id, 'student_code' => 'LIB-'.substr($key, 0, 40),
                'status' => 'active', 'admission_approved_at' => now()]);
            $id = DB::table('library_practice_accounts')->insertGetId([
                'bridge_id' => $bridgeId, 'library_student_id' => $identity->id, 'library_user_id' => $identity->user_id,
                'student_code' => $identity->student_code, 'student_name' => $identity->name,
                'branch_id' => $identity->branch_id, 'student_profile_id' => $profile->id,
                'created_at' => now(), 'updated_at' => now(),
            ]);

            return DB::table('library_practice_accounts')->find($id);
        }, 3);
    }

    public function account(Request $request, LibraryPracticeBridge $bridge): object
    {
        $account = DB::table('library_practice_accounts')->find((int) $request->session()->get('library_practice_account_id'));
        abort_unless($account && $account->bridge_id === $bridge->configuration()['bridge_id'], 403, 'Open practice from your library panel.');
        $profile = StudentProfile::with('user')->find($account->student_profile_id);
        abort_unless($profile && $profile->status === 'active' && $profile->user?->is_active, 403, 'Practice account is inactive.');
        $identity = $bridge->identity((int) $account->library_student_id, $account->student_code, (int) $account->library_user_id);
        abort_unless($identity, 403, 'Library account is inactive.');

        return $account;
    }

    public function assertMembership(object $account, LibraryPracticeBridge $bridge): void
    {
        $identity = $bridge->identity((int) $account->library_student_id, $account->student_code, (int) $account->library_user_id);
        if (! $identity || ! $bridge->eligible($identity)) {
            throw new RuntimeException('नया मुफ्त टेस्ट शुरू करने के लिए सक्रिय लाइब्रेरी सदस्यता जरूरी है।');
        }
    }

    public function month(object $account): object
    {
        $period = CarbonImmutable::now('Asia/Kolkata')->format('Y-m');
        DB::table('library_practice_months')->insertOrIgnore([
            'library_practice_account_id' => $account->id, 'period' => $period,
            'terms_version' => self::TERMS_VERSION, 'terms_accepted_at' => now(),
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return DB::table('library_practice_months')->where('library_practice_account_id', $account->id)->where('period', $period)->first();
    }

    public function available(Test $test): bool
    {
        return $test->is_active && (! $test->available_from || $test->available_from->lessThanOrEqualTo(now()))
            && (! $test->available_until || $test->available_until->greaterThanOrEqualTo(now()))
            && $test->questions()->where('questions.is_active', true)->where('questions.is_published', true)->exists();
    }

    public function select(object $month, Test $test): void
    {
        if (! $this->available($test)) {
            throw new RuntimeException('यह टेस्ट अभी उपलब्ध नहीं है।');
        }
        DB::transaction(function () use ($month, $test) {
            DB::table('library_practice_months')->where('id', $month->id)->lockForUpdate()->first();
            $selected = DB::table('library_practice_selections')->where('library_practice_month_id', $month->id);
            if ((clone $selected)->where('test_id', $test->id)->exists()) {
                return;
            }
            if ((clone $selected)->count() >= self::LIMIT) {
                throw new RuntimeException('इस महीने के सभी 10 सेट चुन लिए गए हैं।');
            }
            $selected->insert(['library_practice_month_id' => $month->id, 'test_id' => $test->id,
                'created_at' => now(), 'updated_at' => now()]);
        }, 3);
    }

    public function unselect(object $month, Test $test): void
    {
        DB::transaction(function () use ($month, $test) {
            DB::table('library_practice_months')->where('id', $month->id)->lockForUpdate()->first();
            $query = DB::table('library_practice_selections')->where('library_practice_month_id', $month->id)->where('test_id', $test->id);
            $selection = (clone $query)->first();
            if ($selection?->test_attempt_id) {
                throw new RuntimeException('शुरू किया हुआ सेट महीने की सीमा से नहीं हटाया जा सकता।');
            }
            $query->delete();
        }, 3);
    }

    public function start(object $account, object $month, Test $test, ExamEngineService $engine): TestAttempt
    {
        return DB::transaction(function () use ($account, $month, $test, $engine) {
            DB::table('library_practice_months')->where('id', $month->id)->lockForUpdate()->first();
            $selection = DB::table('library_practice_selections')->where('library_practice_month_id', $month->id)->where('test_id', $test->id)->first();
            if (! $selection) {
                throw new RuntimeException('टेस्ट शुरू करने से पहले अपने मासिक सेट में चुनें।');
            }
            if ($selection->test_attempt_id) {
                return TestAttempt::findOrFail($selection->test_attempt_id);
            }
            if (! $this->available($test)) {
                throw new RuntimeException('यह टेस्ट अभी उपलब्ध नहीं है।');
            }
            $profile = StudentProfile::findOrFail($account->student_profile_id);
            $attempt = $engine->start($test, $profile);
            DB::table('library_practice_selections')->where('id', $selection->id)
                ->update(['test_attempt_id' => $attempt->id, 'updated_at' => now()]);

            return $attempt;
        }, 3);
    }

    public function authorizeAttempt(object $account, TestAttempt $attempt): void
    {
        abort_unless((int) $attempt->student_profile_id === (int) $account->student_profile_id
            && DB::table('library_practice_selections as s')->join('library_practice_months as m', 'm.id', '=', 's.library_practice_month_id')
                ->where('m.library_practice_account_id', $account->id)->where('s.test_attempt_id', $attempt->id)->exists(), 403);
    }
}
