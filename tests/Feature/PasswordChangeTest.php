<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PasswordChangeTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_from_password_page(): void
    {
        $this->get(route('password.edit'))->assertRedirect(route('login'));
    }

    public function test_authenticated_user_can_view_password_page(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('password.edit'))
            ->assertOk()
            ->assertSee('Change Password');
    }

    public function test_user_can_change_password_with_current_password(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('OldPassword!123'),
        ]);

        $this->actingAs($user)
            ->put(route('password.update'), [
                'current_password' => 'OldPassword!123',
                'password' => 'NewSecurePassword!456',
                'password_confirmation' => 'NewSecurePassword!456',
            ])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success');

        $this->assertTrue(Hash::check('NewSecurePassword!456', $user->fresh()->password));
    }

    public function test_wrong_current_password_is_rejected(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('OldPassword!123'),
        ]);

        $this->actingAs($user)
            ->from(route('password.edit'))
            ->put(route('password.update'), [
                'current_password' => 'WrongPassword!123',
                'password' => 'NewSecurePassword!456',
                'password_confirmation' => 'NewSecurePassword!456',
            ])
            ->assertRedirect(route('password.edit'))
            ->assertSessionHasErrors('current_password');

        $this->assertTrue(Hash::check('OldPassword!123', $user->fresh()->password));
    }
}
