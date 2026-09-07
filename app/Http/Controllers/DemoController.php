<?php

namespace App\Http\Controllers;

use App\Models\StudentProfile;
use App\Models\User;
use App\Services\EmailOtpService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DemoController extends Controller
{
    public function create()
    {
        return view('demo.create');
    }

    public function sendOtp(Request $request, EmailOtpService $otpService)
    {
        $validated = $request->validate(['email' => ['required', 'email', 'max:255']]);
        $otpService->send($validated['email'], 'demo', $request->ip(), $request->userAgent());

        return back()->withInput()->with('otp_sent', true)
            ->with('success', 'OTP has been sent to your email.');
    }

    public function verifyOtp(Request $request, EmailOtpService $otpService)
    {
        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'otp' => ['required', 'digits:6'],
        ]);

        if (! $otpService->verify($validated['email'], $validated['otp'], 'demo')) {
            return back()->withInput()->withErrors(['otp' => 'Invalid or expired OTP.']);
        }

        $user = DB::transaction(function () use ($validated) {
            // A demo OTP must never authenticate an existing student or admin.
            $normalizedEmail = Str::lower(trim($validated['email']));
            $demoEmail = 'demo+'.substr(hash('sha256', $normalizedEmail), 0, 24).'@test.mciedu.local';

            $user = User::firstOrCreate(
                ['email' => $demoEmail],
                [
                    'name' => 'Demo Student',
                    'password' => Hash::make(Str::random(40)),
                    'role' => 'student',
                    'is_active' => true,
                    'email_verified_at' => now(),
                ]
            );

            $user->forceFill([
                'email_verified_at' => $user->email_verified_at ?: now(),
                'role' => 'student',
                'is_active' => true,
            ])->save();

            StudentProfile::firstOrCreate(
                ['user_id' => $user->id],
                [
                    'student_code' => 'DEMO-'.str_pad((string) $user->id, 6, '0', STR_PAD_LEFT),
                    'email_verified_at' => now(),
                    'status' => 'active',
                ]
            );

            return $user;
        });

        Auth::login($user, true);
        $request->session()->regenerate();
        $request->session()->put('demo_access', true);

        return redirect()->route('student.tests.index')
            ->with('success', 'Email verified. Your free demo is ready.');
    }
}
