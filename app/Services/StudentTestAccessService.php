<?php

namespace App\Services;

use App\Models\StudentProfile;
use App\Models\Test;
use Illuminate\Support\Facades\DB;

class StudentTestAccessService
{
    public function activeEnrollment(StudentProfile $student): ?object
    {
        return DB::table('student_enrollments as se')
            ->leftJoin('packages as p', 'p.id', '=', 'se.package_id')
            ->where('se.student_profile_id', $student->id)
            ->where('se.status', 'active')
            ->where(fn ($q) => $q->whereNull('se.starts_at')->orWhere('se.starts_at', '<=', now()))
            ->where(fn ($q) => $q->whereNull('se.expires_at')->orWhere('se.expires_at', '>=', now()))
            ->orderByDesc('se.id')
            ->select('se.*', 'p.name as package_name', 'p.exam_id as package_exam_id')
            ->first();
    }

    public function allowedTestIds(object $enrollment): ?array
    {
        if ($enrollment->test_limit === null) return null;

        return DB::transaction(function () use ($enrollment): array {
            $locked = DB::table('student_enrollments')->where('id', $enrollment->id)->lockForUpdate()->first();
            $limit = max(0, (int) $locked->test_limit);
            if ($limit === 0) return [];

            $existing = DB::table('student_enrollment_tests')->where('student_enrollment_id', $locked->id)
                ->orderBy('id')->pluck('test_id')->map(fn ($id) => (int) $id)->all();

            if (count($existing) < $limit) {
                $attempted = DB::table('test_attempts')->where('student_profile_id', $locked->student_profile_id)
                    ->distinct()->pluck('test_id')->map(fn ($id) => (int) $id)->all();
                $candidateQuery = Test::query()->where('is_active', true);
                if ($enrollment->package_exam_id) $candidateQuery->where('exam_id', $enrollment->package_exam_id);
                $candidates = $candidateQuery->orderByDesc('id')->pluck('id')->map(fn ($id) => (int) $id)->all();
                $selected = array_slice(array_values(array_unique(array_merge($attempted, $existing, $candidates))), 0, $limit);
                foreach ($selected as $testId) {
                    DB::table('student_enrollment_tests')->insertOrIgnore([
                        'student_enrollment_id' => $locked->id,
                        'test_id' => $testId,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
                $existing = $selected;
            }

            return array_slice($existing, 0, $limit);
        });
    }

    public function allows(object $enrollment, Test $test): bool
    {
        if (! $test->is_active) return false;
        if ($enrollment->package_exam_id && (int) $test->exam_id !== (int) $enrollment->package_exam_id) return false;
        $ids = $this->allowedTestIds($enrollment);
        return $ids === null || in_array((int) $test->id, $ids, true);
    }

    public function syncUsage(object $enrollment): void
    {
        $attempts = DB::table('test_attempts as ta')->where('ta.student_profile_id', $enrollment->student_profile_id);
        if ($enrollment->test_limit === null) {
            if ($enrollment->package_exam_id) {
                $attempts->join('tests as t', 't.id', '=', 'ta.test_id')->where('t.exam_id', $enrollment->package_exam_id);
            }
        } else {
            $attempts->whereIn('ta.test_id', DB::table('student_enrollment_tests')->where('student_enrollment_id', $enrollment->id)->select('test_id'));
        }
        $used = $attempts->distinct()->count('ta.test_id');
        DB::table('student_enrollments')->where('id', $enrollment->id)->update(['tests_used' => $used, 'updated_at' => now()]);
    }
}
