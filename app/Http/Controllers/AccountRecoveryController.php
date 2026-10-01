<?php
namespace App\Http\Controllers;

use App\Models\User;
use App\Services\AccountRecoveryOtp;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class AccountRecoveryController extends Controller
{
    public function show(Request $request): View {
        return view('recovery.account', ['applicationPortal' => class_exists(\App\Support\AdmissionStore::class)]);
    }

    public function send(Request $request, AccountRecoveryOtp $otp): RedirectResponse {
        $data = $request->validate(['email' => ['required','email','max:254'], 'purpose' => ['required','in:password,identifier,application']]);
        $email = Str::lower(trim($data['email']));
        $purpose = $data['purpose']; $payload = [];
        if ($purpose === 'password') {
            $user = User::whereRaw('LOWER(email) = ?', [$email])->first();
            if ($user) $payload = ['id' => $user->id, 'email' => $user->email, 'password_hash' => $user->password];
        } elseif ($purpose === 'identifier') {
            $ids = DB::table('mci_recovery_contacts')->where('email', $email)->pluck('user_id')->all();
            if ($ids) $payload = ['ids' => $ids];
        } elseif (class_exists(\App\Support\AdmissionStore::class)) {
            $ids = collect(\App\Support\AdmissionStore::all())->filter(fn ($row) =>
                Str::lower(trim((string) ($row['email'] ?? ''))) === $email && ($row['status'] ?? '') === 'admitted')->pluck('id')->all();
            if ($ids) $payload = ['ids' => $ids];
        }
        try {
            $id = $payload ? $otp->issue($email, $purpose, $payload, $request->session()->getId(), $request->ip()) : (string) Str::uuid();
        } catch (\RuntimeException $e) {
            $id = (string) Str::uuid();
            $pending = $request->session()->get('mci_recovery_pending');
            if (is_array($pending) && ($pending['purpose'] ?? null) === $purpose && is_string($pending['id'] ?? null)) {
                $usable = DB::table('mci_recovery_challenges')->where('id', $pending['id'])
                    ->where('email', $email)->where('purpose', $purpose)
                    ->where('session_hash', hash('sha256', $request->session()->getId()))
                    ->where('expires_at', '>=', time())->where('attempts', '<', 5)
                    ->whereNull('consumed_at')->exists();
                if ($usable) {
                    $id = $pending['id'];
                }
            }
        }
        $request->session()->put('mci_recovery_pending', ['id' => $id, 'purpose' => $purpose]);
        return back()->with('status', 'If this email is eligible, a recovery code has been sent. Check your inbox and spam folder.');
    }

    public function verify(Request $request, AccountRecoveryOtp $otp): RedirectResponse {
        $pending = $request->session()->get('mci_recovery_pending');
        abort_unless(is_array($pending), 422);
        $rules = ['code' => ['required','regex:/^[0-9]{6}$/']];
        if ($pending['purpose'] === 'password') $rules['password'] = ['required','confirmed',Password::min(12)->mixedCase()->numbers()->symbols()];
        $data = $request->validate($rules);
        try {
            $resetUserId = null;
            $identifiers = $otp->consume($pending['id'], $data['code'], $pending['purpose'], $request->session()->getId(), function (array $payload, string $email) use ($pending, $data, &$resetUserId) {
                if ($pending['purpose'] === 'password') {
                    $user = User::whereKey($payload['id'])->lockForUpdate()->first();
                    if (! $user || ! hash_equals($payload['email'], $user->email) || ! hash_equals($payload['password_hash'], $user->password))
                        throw new \RuntimeException('Account details have changed. Request a new code.');
                    $update = ['password' => Hash::make($data['password'])];
                    if (Schema::hasColumn($user->getTable(), 'remember_token')) $update['remember_token'] = Str::random(60);
                    $user->forceFill($update)->save();
                    if (Schema::hasTable('sessions') && Schema::hasColumn('sessions','user_id')) DB::table('sessions')->where('user_id', $user->id)->delete();
                    foreach (['mobile_api_tokens','personal_access_tokens'] as $table) {
                        if (Schema::hasTable($table) && Schema::hasColumn($table,'user_id')) DB::table($table)->where('user_id',$user->id)->delete();
                    }
                    if (Schema::hasTable('personal_access_tokens') && Schema::hasColumn('personal_access_tokens','tokenable_id'))
                        DB::table('personal_access_tokens')->where('tokenable_id',$user->id)->where('tokenable_type',$user->getMorphClass())->delete();
                    app(\App\Services\RecoverySessionRevoker::class)->revokeFileSessions($user->id);
                    $resetUserId = $user->id;
                    return [];
                }
                if ($pending['purpose'] === 'identifier') {
                    $ids = DB::table('mci_recovery_contacts')->whereIn('user_id',$payload['ids'])->where('email',$email)->pluck('user_id');
                    return User::whereIn('id',$ids)->pluck('email')->all();
                }
                return collect(\App\Support\AdmissionStore::all())->filter(fn ($row) =>
                    in_array($row['id'] ?? null, $payload['ids'], true) && Str::lower(trim((string)($row['email'] ?? ''))) === $email
                    && ($row['status'] ?? '') === 'admitted')->pluck('application_no')->all();
            });
        } catch (\RuntimeException $e) { return back()->withErrors(['code' => $e->getMessage()]); }
        if ($resetUserId !== null && $request->user() && $request->user()->getAuthIdentifier() == $resetUserId) {
            Auth::guard()->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }
        $request->session()->forget('mci_recovery_pending');
        $request->session()->regenerate();
        return back()->with('status', $pending['purpose'] === 'password' ? 'Password updated. Please sign in with your new password.' : 'Your verified login identifiers are shown below.')
            ->with('recovered_identifiers', $identifiers);
    }

    public function contact(Request $request): View { return view('recovery.contact'); }

    public function sendContact(Request $request, AccountRecoveryOtp $otp): RedirectResponse {
        $data = $request->validate(['email' => ['required','email','max:254'], 'current_password' => ['required','string']]);
        $user = $request->user();
        if (! Hash::check($data['current_password'], $user->password)) return back()->withErrors(['current_password' => 'Current password is incorrect.']);
        $email = Str::lower(trim($data['email']));
        if ($email === Str::lower($user->email)) return back()->withErrors(['email' => 'Use a different recovery email.']);
        try {
            $id = $otp->issue($email, 'contact', ['id' => $user->id,'password_hash' => $user->password], $request->session()->getId(), $request->ip());
        } catch (\RuntimeException $e) { return back()->withErrors(['email' => $e->getMessage()]); }
        $request->session()->put('mci_contact_pending', $id);
        return back()->with('status', 'A verification code has been sent to your recovery email.');
    }

    public function verifyContact(Request $request, AccountRecoveryOtp $otp): RedirectResponse {
        $data = $request->validate(['code' => ['required','regex:/^[0-9]{6}$/']]);
        $id = $request->session()->get('mci_contact_pending'); abort_unless(is_string($id),422);
        try {
            $otp->consume($id,$data['code'],'contact',$request->session()->getId(),function (array $payload, string $email) use ($request) {
                $user = $request->user()->fresh();
                if ($payload['id'] != $user->id || ! hash_equals($payload['password_hash'],$user->password))
                    throw new \RuntimeException('Account details have changed. Request a new code.');
                DB::table('mci_recovery_contacts')->updateOrInsert(['user_id' => $user->id], ['email' => $email,'verified_at' => now()]);
            });
        } catch (\RuntimeException $e) { return back()->withErrors(['code' => $e->getMessage()]); }
        $request->session()->forget('mci_contact_pending');
        return back()->with('status','Recovery email verified and saved.');
    }
}
