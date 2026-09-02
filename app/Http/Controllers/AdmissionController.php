<?php

namespace App\Http\Controllers;

use App\Models\AdmissionApplication;
use App\Services\EmailOtpService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AdmissionController extends Controller
{
    public function create()
    {
        return view('admission.create');
    }

    public function sendOtp(Request $request, EmailOtpService $otpService)
    {
        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255'],
        ]);

        $otpService->send(
            $validated['email'],
            'admission',
            $request->ip(),
            $request->userAgent()
        );

        return back()
            ->withInput()
            ->with('otp_sent', true)
            ->with('success', 'OTP has been sent to your email.');
    }

    public function verifyOtp(Request $request, EmailOtpService $otpService)
    {
        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'otp' => ['required', 'digits:6'],
        ]);

        if (!$otpService->verify(
            $validated['email'],
            $validated['otp'],
            'admission'
        )) {
            return back()
                ->withInput()
                ->withErrors([
                    'otp' => 'Invalid or expired OTP.',
                ]);
        }

        session([
            'admission_verified_email' => $validated['email'],
        ]);

        return back()
            ->withInput()
            ->with('success', 'Email verified successfully.');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'father_name' => ['nullable', 'string', 'max:255'],
            'mother_name' => ['nullable', 'string', 'max:255'],

            'email' => ['required', 'email', 'max:255'],
            'phone' => ['required', 'string', 'max:20'],

            'date_of_birth' => ['required', 'date'],
            'gender' => ['required', 'string', 'max:20'],

            'address' => ['required', 'string', 'max:1000'],
            'city' => ['required', 'string', 'max:255'],
            'district' => ['required', 'string', 'max:255'],
            'state' => ['required', 'string', 'max:255'],
            'pincode' => ['required', 'string', 'max:10'],

            'photo' => [
                'required',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:2048',
            ],
        ]);

        if (session('admission_verified_email') !== $validated['email']) {
            return back()
                ->withInput()
                ->withErrors([
                    'email' => 'Please verify this email using OTP before submitting.',
                ]);
        }

        $photoPath = $request->file('photo')
            ->store('admission-photos', 'public');

        $application = AdmissionApplication::create([
            'application_no' => $this->applicationNumber(),
            'name' => $validated['name'],
            'father_name' => $validated['father_name'] ?? null,
            'mother_name' => $validated['mother_name'] ?? null,
            'email' => $validated['email'],
            'phone' => $validated['phone'],
            'date_of_birth' => $validated['date_of_birth'],
            'gender' => $validated['gender'],
            'address' => $validated['address'],
            'city' => $validated['city'],
            'district' => $validated['district'],
            'state' => $validated['state'],
            'pincode' => $validated['pincode'],
            'photo_path' => $photoPath,
            'email_verified_at' => now(),
            'status' => 'submitted',
        ]);

        session()->forget('admission_verified_email');

        return redirect()
            ->route('admission.success', $application);
    }

    public function success(AdmissionApplication $application)
    {
        return view('admission.success', compact('application'));
    }

    private function applicationNumber(): string
    {
        do {
            $number = 'MCI-APP-'.now()->format('Ymd').'-'.Str::upper(Str::random(6));
        } while (
            AdmissionApplication::where('application_no', $number)->exists()
        );

        return $number;
    }
}
