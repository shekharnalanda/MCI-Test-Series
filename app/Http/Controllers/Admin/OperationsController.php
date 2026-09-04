<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class OperationsController extends Controller
{
    public function index(): View
    {
        $students = DB::table('student_profiles as sp')
            ->join('users as u', 'u.id', '=', 'sp.user_id')
            ->leftJoin('student_enrollments as se', function ($join) {
                $join->on('se.student_profile_id', '=', 'sp.id')->where('se.status', '=', 'active');
            })
            ->leftJoin('packages as p', 'p.id', '=', 'se.package_id')
            ->select('sp.id as profile_id', 'sp.student_code', 'sp.phone', 'sp.status as profile_status',
                'u.id as user_id', 'u.name', 'u.email', 'u.is_active', 'p.name as package_name',
                'se.tests_used', 'se.test_limit', 'se.expires_at')
            ->orderByDesc('u.id')->paginate(20, ['*'], 'students_page');

        return view('admin.operations.index', [
            'students' => $students,
            'packages' => DB::table('packages')->orderByDesc('is_active')->orderBy('name')->get(),
            'exams' => DB::table('exams')->orderByDesc('is_active')->orderBy('name')->get(),
            'categories' => DB::table('exam_categories')->orderBy('name')->get(),
        ]);
    }

    public function storeStudent(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['required', 'string', 'max:20', 'unique:student_profiles,phone'],
            'password' => ['required', Password::min(12)->letters()->mixedCase()->numbers()->symbols()],
            'package_id' => ['nullable', 'integer', 'exists:packages,id'],
        ]);

        DB::transaction(function () use ($data): void {
            $userId = DB::table('users')->insertGetId([
                'name' => $data['name'],
                'email' => Str::lower($data['email']),
                'password' => Hash::make($data['password']),
                'role' => 'student',
                'is_active' => true,
                'email_verified_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $profileId = DB::table('student_profiles')->insertGetId([
                'user_id' => $userId,
                'student_code' => 'MCI'.now()->format('ymd').str_pad((string) $userId, 5, '0', STR_PAD_LEFT),
                'phone' => $data['phone'],
                'email_verified_at' => now(),
                'admission_approved_at' => now(),
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            if (!empty($data['package_id'])) {
                $this->createEnrollment($profileId, (int) $data['package_id']);
            }
        });

        return back()->with('success', 'Student account created successfully.');
    }

    public function toggleStudent(int $user): RedirectResponse
    {
        $record = DB::table('users')->where('id', $user)->where('role', 'student')->first();
        abort_unless($record, 404);

        DB::table('users')->where('id', $user)->update([
            'is_active' => !$record->is_active,
            'updated_at' => now(),
        ]);

        return back()->with('success', 'Student access updated.');
    }

    public function assignPackage(Request $request, int $profile): RedirectResponse
    {
        $data = $request->validate(['package_id' => ['required', 'integer', 'exists:packages,id']]);
        abort_unless(DB::table('student_profiles')->where('id', $profile)->exists(), 404);

        DB::table('student_enrollments')
            ->where('student_profile_id', $profile)
            ->where('status', 'active')
            ->update(['status' => 'expired', 'updated_at' => now()]);

        $this->createEnrollment($profile, (int) $data['package_id']);

        return back()->with('success', 'Package assigned successfully.');
    }

    public function storePackage(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'exam_id' => ['nullable', 'integer', 'exists:exams,id'],
            'name' => ['required', 'string', 'max:255'],
            'name_hi' => ['nullable', 'string', 'max:255'],
            'price' => ['required', 'numeric', 'min:0'],
            'test_limit' => ['required', 'integer', 'min:1'],
            'validity_days' => ['required', 'integer', 'min:1'],
        ]);

        DB::table('packages')->insert([
            ...$data,
            'slug' => Str::slug($data['name']).'-'.Str::lower(Str::random(5)),
            'description' => null,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return back()->with('success', 'Package created successfully.');
    }

    public function togglePackage(int $package): RedirectResponse
    {
        $record = DB::table('packages')->where('id', $package)->first();
        abort_unless($record, 404);
        DB::table('packages')->where('id', $package)->update(['is_active' => !$record->is_active, 'updated_at' => now()]);

        return back()->with('success', 'Package status updated.');
    }

    public function storeExam(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'exam_category_id' => ['required', 'integer', 'exists:exam_categories,id'],
            'name' => ['required', 'string', 'max:255'],
            'name_hi' => ['nullable', 'string', 'max:255'],
            'conducting_body' => ['nullable', 'string', 'max:255'],
        ]);

        DB::table('exams')->insert([
            ...$data,
            'slug' => Str::slug($data['name']).'-'.Str::lower(Str::random(5)),
            'description' => null,
            'official_url' => null,
            'pattern' => null,
            'syllabus' => null,
            'is_featured' => false,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return back()->with('success', 'Exam created successfully.');
    }

    public function toggleExam(int $exam): RedirectResponse
    {
        $record = DB::table('exams')->where('id', $exam)->first();
        abort_unless($record, 404);
        DB::table('exams')->where('id', $exam)->update(['is_active' => !$record->is_active, 'updated_at' => now()]);

        return back()->with('success', 'Exam status updated.');
    }

    private function createEnrollment(int $profileId, int $packageId): void
    {
        $package = DB::table('packages')->where('id', $packageId)->where('is_active', true)->first();
        abort_unless($package, 422, 'Selected package is inactive.');

        DB::table('student_enrollments')->insert([
            'student_profile_id' => $profileId,
            'package_id' => $package->id,
            'fee_amount' => $package->price,
            'discount_amount' => 0,
            'paid_amount' => 0,
            'test_limit' => $package->test_limit,
            'tests_used' => 0,
            'starts_at' => now(),
            'expires_at' => now()->addDays($package->validity_days),
            'payment_status' => 'unpaid',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
