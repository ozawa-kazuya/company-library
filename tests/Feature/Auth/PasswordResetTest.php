<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Notifications\ResetPassword as ResetPasswordNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_forgot_password_screen_can_be_rendered(): void
    {
        $this->get('/forgot-password')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Auth/ForgotPassword'));
    }

    public function test_employee_receives_password_reset_email(): void
    {
        Notification::fake();

        $user = User::factory()->create([
            'password' => 'changed-password',
        ]);

        $this->from('/forgot-password')
            ->post('/forgot-password', ['email' => $user->email])
            ->assertRedirect('/forgot-password')
            ->assertSessionHas('status');

        Notification::assertSentTo($user, ResetPasswordNotification::class);
    }

    public function test_admin_cannot_request_password_reset_email(): void
    {
        Notification::fake();

        $admin = User::factory()->admin()->create([
            'user_code' => '001',
            'password' => 'changed-password',
        ]);

        $this->from('/forgot-password')
            ->post('/forgot-password', ['email' => $admin->email])
            ->assertRedirect('/forgot-password')
            ->assertSessionHasErrors('email');

        Notification::assertNothingSent();
    }

    public function test_employee_can_reset_password_with_valid_token(): void
    {
        Notification::fake();

        $user = User::factory()->create([
            'password' => 'changed-password',
        ]);

        $this->post('/forgot-password', ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPasswordNotification::class, function ($notification) use ($user) {
            $this->post('/reset-password', [
                'token' => $notification->token,
                'email' => $user->email,
                'password' => 'newpass12',
                'password_confirmation' => 'newpass12',
            ])
                ->assertSessionHasNoErrors()
                ->assertRedirect(route('login'));

            $this->assertTrue(Hash::check('newpass12', $user->refresh()->password));

            return true;
        });
    }
}
