<?php

namespace Tests\Feature\Http\Controllers;

use App\Mail\AccountRecoveryCode;
use App\Models\User;
use App\Services\AccountRecoveryOtp;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class AccountRecoveryControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_recovery_page_offers_password_and_login_email_recovery(): void
    {
        $this->get('/account-recovery')->assertSee('Forgot Password')->assertSee('Forgot Login Email');
    }

    public function test_missing_recovery_input_returns_field_errors_without_sending_mail(): void
    {
        Mail::fake();

        $this->from('/account-recovery')->post('/account-recovery/send', [])
            ->assertRedirect('/account-recovery')
            ->assertSessionHasErrors(['email', 'purpose']);

        $this->assertDatabaseCount('mci_recovery_challenges', 0);
        Mail::assertNothingSent();
    }

    public function test_invalid_email_is_rejected_without_creating_a_challenge(): void
    {
        Mail::fake();

        $this->post('/account-recovery/send', ['email' => 'invalid', 'purpose' => 'password'])
            ->assertSessionHasErrors(['email']);

        $this->assertDatabaseCount('mci_recovery_challenges', 0);
        Mail::assertNothingSent();
    }

    public function test_unknown_email_gets_generic_status_without_an_account_challenge(): void
    {
        Mail::fake();

        $this->post('/account-recovery/send', ['email' => 'unknown@example.com', 'purpose' => 'password'])
            ->assertSessionHas('status', 'If this email is eligible, a recovery code has been sent. Check your inbox and spam folder.');

        $this->assertDatabaseCount('mci_recovery_challenges', 0);
        Mail::assertNothingSent();
    }

    public function test_valid_otp_resets_only_target_password_and_preserves_account_permissions(): void
    {
        $user = User::factory()->create(['role' => 'student', 'is_active' => false]);
        $other = User::factory()->create();
        $otherHash = $other->password;
        [$id, $code, $sessionId] = $this->passwordChallenge($user);

        $this->withCookie(config('session.cookie'), $sessionId)
            ->withSession(['mci_recovery_pending' => ['id' => $id, 'purpose' => 'password']])
            ->from('/account-recovery')->post('/account-recovery/verify', [
                'code' => $code, 'password' => 'NewPassword!2026', 'password_confirmation' => 'NewPassword!2026',
                'role' => 'admin', 'is_active' => true, 'user_id' => $other->id,
            ])->assertRedirect('/account-recovery')->assertSessionHasNoErrors();

        $this->assertTrue(Hash::check('NewPassword!2026', $user->fresh()->password));
        $this->assertSame($otherHash, $other->fresh()->password);
        $this->assertDatabaseHas('users', ['id' => $user->id, 'role' => 'student', 'is_active' => false]);
    }

    public function test_incorrect_otp_does_not_change_password(): void
    {
        $user = User::factory()->create();
        $oldHash = $user->password;
        [$id, $code, $sessionId] = $this->passwordChallenge($user);

        $this->withCookie(config('session.cookie'), $sessionId)
            ->withSession(['mci_recovery_pending' => ['id' => $id, 'purpose' => 'password']])
            ->post('/account-recovery/verify', [
                'code' => $code === '123456' ? '654321' : '123456',
                'password' => 'NewPassword!2026', 'password_confirmation' => 'NewPassword!2026',
            ])->assertSessionHasErrors(['code']);

        $this->assertSame($oldHash, $user->fresh()->password);
    }

    public function test_password_confirmation_error_does_not_consume_the_challenge(): void
    {
        $user = User::factory()->create();
        $oldHash = $user->password;
        [$id, $code, $sessionId] = $this->passwordChallenge($user);

        $this->withCookie(config('session.cookie'), $sessionId)
            ->withSession(['mci_recovery_pending' => ['id' => $id, 'purpose' => 'password']])
            ->post('/account-recovery/verify', [
                'code' => $code, 'password' => 'NewPassword!2026', 'password_confirmation' => 'Different!2026',
            ])->assertSessionHasErrors(['password']);

        $this->assertSame($oldHash, $user->fresh()->password);
        $this->assertDatabaseHas('mci_recovery_challenges', ['id' => $id, 'consumed_at' => null]);
    }

    public function test_secondary_email_settings_require_sign_in(): void
    {
        $this->get('/account/recovery-email')->assertRedirect(route('login'));
    }

    public function test_wrong_current_password_cannot_enroll_a_recovery_email(): void
    {
        $user = User::factory()->create();
        Mail::fake();

        $this->actingAs($user)->post('/account/recovery-email/send', [
            'email' => 'backup@example.com', 'current_password' => 'wrong-password',
        ])->assertSessionHasErrors(['current_password']);

        $this->assertDatabaseCount('mci_recovery_contacts', 0);
        $this->assertDatabaseCount('mci_recovery_challenges', 0);
        Mail::assertNothingSent();
    }

    public function test_recovered_identifiers_are_escaped_in_the_page(): void
    {
        $this->withSession(['recovered_identifiers' => ['<script>alert(1)</script>']])
            ->get('/account-recovery')->assertSee('&lt;script&gt;', false)->assertDontSee('<script>alert(1)</script>', false);
    }

    private function passwordChallenge(User $user): array
    {
        Mail::fake();
        config(['mail.default' => 'smtp']);
        $this->app['session']->start();
        $sessionId = $this->app['session']->getId();
        $id = app(AccountRecoveryOtp::class)->issue($user->email, 'password', [
            'id' => $user->id, 'email' => $user->email, 'password_hash' => $user->password,
        ], $sessionId, '127.0.0.1');
        $code = Mail::sent(AccountRecoveryCode::class)->first()->code;
        Mail::assertSent(AccountRecoveryCode::class, fn (AccountRecoveryCode $mail): bool => $mail->hasTo($user->email));

        return [$id, $code, $sessionId];
    }
}
