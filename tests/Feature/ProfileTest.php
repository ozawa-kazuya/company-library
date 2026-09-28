<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    private function userWithChangedPassword(): User
    {
        return User::factory()->create([
            'password' => 'changed-password',
        ]);
    }

    public function test_employee_profile_redirects_to_password_change(): void
    {
        $user = $this->userWithChangedPassword();

        $this->actingAs($user)
            ->get('/profile')
            ->assertRedirect(route('password.change'));
    }

    public function test_profile_information_can_be_updated(): void
    {
        $user = $this->userWithChangedPassword();

        $response = $this
            ->actingAs($user)
            ->patch('/profile', [
                'name' => 'Test User',
                'email' => 'test@example.com',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/profile');

        $user->refresh();

        $this->assertSame('Test User', $user->name);
        $this->assertSame('test@example.com', $user->email);
        $this->assertNull($user->email_verified_at);
    }

    public function test_email_verification_status_is_unchanged_when_the_email_address_is_unchanged(): void
    {
        $user = $this->userWithChangedPassword();

        $response = $this
            ->actingAs($user)
            ->patch('/profile', [
                'name' => 'Test User',
                'email' => $user->email,
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/profile');

        $this->assertNotNull($user->refresh()->email_verified_at);
    }

    public function test_user_cannot_delete_their_account(): void
    {
        $user = $this->userWithChangedPassword();

        $this->actingAs($user)
            ->delete('/profile', [
                'password' => 'changed-password',
            ])
            ->assertForbidden();

        $this->assertAuthenticated();
        $this->assertNotNull($user->fresh());
    }
}
