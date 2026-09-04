<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdmissionApplication;
use App\Models\Package;
use App\Models\StudentEnrollment;
use App\Services\AdmissionApprovalService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Throwable;

class AdmissionController extends Controller
{
    public function index()
    {
        $applications = AdmissionApplication::latest()->paginate(25);
        return view('admin.admissions.index', compact('applications'));
    }

    public function show(AdmissionApplication $application)
    {
        $packages = Package::where('is_active', true)->orderBy('name')->get();
        return view('admin.admissions.show', compact('application', 'packages'));
    }

    public function approve(Request $request, AdmissionApplication $application, AdmissionApprovalService $service)
    {
        $validated = $request->validate([
            'package_id' => ['nullable','integer','exists:packages,id'],
            'fee_amount' => ['nullable','numeric','min:0'],
            'discount_amount' => ['nullable','numeric','min:0'],
            'paid_amount' => ['nullable','numeric','min:0'],
            'payment_status' => ['required','in:unpaid,partial,paid,waived'],
        ]);

        $result = $service->approve($application, auth()->user());
        $enrollment = null;

        if (! empty($validated['package_id'])) {
            $package = Package::whereKey($validated['package_id'])->where('is_active', true)->firstOrFail();
            $active = $request->boolean('activate_access');
            $startsAt = $active ? now() : null;
            $enrollment = StudentEnrollment::create([
                'student_profile_id' => $result['profile']->id,
                'package_id' => $package->id,
                'fee_amount' => $validated['fee_amount'] ?? $package->price,
                'discount_amount' => $validated['discount_amount'] ?? 0,
                'paid_amount' => $validated['paid_amount'] ?? 0,
                'test_limit' => $package->test_limit,
                'tests_used' => 0,
                'starts_at' => $startsAt,
                'expires_at' => $active && $package->validity_days ? $startsAt->copy()->addDays($package->validity_days) : null,
                'payment_status' => $validated['payment_status'],
                'status' => $active ? 'active' : 'pending',
            ]);
        }

        $mailSent = false;
        try {
            Mail::raw(
                "Welcome to MCI Test Series.\n\nStudent ID: {$result['profile']->student_code}\nLogin Email: {$result['user']->email}\nTemporary Password: {$result['temporary_password']}\nLogin: ".route('login')."\n\nPlease change your password after first login.",
                function ($message) use ($result) {
                    $message->to($result['user']->email, $result['user']->name)->subject('Your MCI Test Series Student Account');
                }
            );
            $mailSent = true;
        } catch (Throwable $exception) {
            report($exception);
        }

        $message = 'Admission approved. Student ID: '.$result['profile']->student_code.' | Temporary Password: '.$result['temporary_password'];
        if ($enrollment) {
            $message .= ' | Package access: '.($enrollment->status === 'active' ? 'Active' : 'Pending');
        }
        $message .= $mailSent ? ' | Credentials emailed successfully.' : ' | Email delivery failed; share the credentials securely.';
        return back()->with('success', $message);
    }

    public function reject(Request $request, AdmissionApplication $application)
    {
        $validated = $request->validate(['admin_notes' => ['nullable','string','max:2000']]);
        $application->update([
            'status' => 'rejected',
            'admin_notes' => $validated['admin_notes'] ?? null,
            'reviewed_by' => auth()->id(),
            'reviewed_at' => now(),
        ]);
        return back()->with('success', 'Application rejected.');
    }
}
