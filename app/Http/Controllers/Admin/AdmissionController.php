<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdmissionApplication;
use App\Services\AdmissionApprovalService;
use Illuminate\Http\Request;

class AdmissionController extends Controller
{
    public function index()
    {
        $applications = AdmissionApplication::latest()->paginate(25);

        return view('admin.admissions.index', compact('applications'));
    }

    public function show(AdmissionApplication $application)
    {
        return view('admin.admissions.show', compact('application'));
    }

    public function approve(
        AdmissionApplication $application,
        AdmissionApprovalService $service
    ) {
        $result = $service->approve($application, auth()->user());

        return back()->with(
            'success',
            'Admission approved. Student ID: '.
            $result['profile']->student_code.
            ' | Temporary Password: '.
            $result['temporary_password']
        );
    }

    public function reject(
        Request $request,
        AdmissionApplication $application
    ) {
        $validated = $request->validate([
            'admin_notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $application->update([
            'status' => 'rejected',
            'admin_notes' => $validated['admin_notes'] ?? null,
            'reviewed_by' => auth()->id(),
            'reviewed_at' => now(),
        ]);

        return back()->with('success', 'Application rejected.');
    }
}
