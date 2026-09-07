<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\EmailOtpService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class AdminPasswordResetController extends Controller
{
    public function create() { return view('auth.admin-password-reset'); }

    public function sendOtp(Request $request, EmailOtpService $otpService)
    {
        $data = $request->validate(['email' => ['required','email','max:255']]);
        $admin = User::where('email', $data['email'])->where('role', 'admin')->where('is_active', true)->first();
        if (! $admin) return back()->withInput()->withErrors(['email' => 'No active administrator account was found for this email.']);
        $otpService->send($admin->email, 'admin-password-reset', $request->ip(), $request->userAgent());
        $request->session()->put('admin_reset_email', $admin->email);
        return back()->withInput()->with('otp_sent', true)->with('success', 'Password reset OTP has been sent.');
    }

    public function verifyOtp(Request $request, EmailOtpService $otpService)
    {
        $data = $request->validate(['email' => ['required','email'], 'otp' => ['required','digits:6']]);
        if ($request->session()->get('admin_reset_email') !== $data['email'] || ! $otpService->verify($data['email'], $data['otp'], 'admin-password-reset')) {
            return back()->withInput()->withErrors(['otp' => 'Invalid or expired OTP.']);
        }
        $request->session()->put('admin_reset_verified_at', now()->timestamp);
        return back()->withInput()->with('otp_verified', true)->with('success', 'OTP verified. Set your new password.');
    }

    public function reset(Request $request)
    {
        $data = $request->validate(['password' => ['required','confirmed',Password::min(12)->letters()->mixedCase()->numbers()]]);
        $email = $request->session()->get('admin_reset_email');
        $verifiedAt = (int) $request->session()->get('admin_reset_verified_at', 0);
        abort_unless($email && $verifiedAt && now()->timestamp - $verifiedAt <= 600, 403, 'Reset verification expired.');
        $admin = User::where('email', $email)->where('role', 'admin')->where('is_active', true)->firstOrFail();
        $admin->forceFill(['password' => Hash::make($data['password'])])->save();
        $request->session()->forget(['admin_reset_email','admin_reset_verified_at']);
        return redirect()->route('login')->with('success', 'Admin password reset successfully. You can now log in.');
    }
}
