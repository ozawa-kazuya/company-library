<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_can_be_rendered(): void
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
    }

    public function test_users_can_authenticate_using_the_login_screen(): void
    {
        $user = User::factory()->create();

        $response = $this->post('/login', [
            'user_code' => $user->user_code,
            'password' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('password.change', absolute: false));
    }

    public function test_users_can_not_authenticate_with_invalid_password(): void
    {
        $user = User::factory()->create();

        $this->post('/login', [
            'user_code' => $user->user_code,
            'password' => 'wrongpass1',
        ]);

        $this->assertGuest();
    }

    public function test_user_code_rejects_non_half_width_digits(): void
    {
        $this->from('/login')->post('/login', [
            'user_code' => 'abc',
            'password' => 'password',
        ])->assertSessionHasErrors([
            'user_code' => '社員番号は半角数字で入力してください。',
        ]);

        $this->assertGuest();
    }

    public function test_user_code_rejects_more_than_three_digits(): void
    {
        $this->from('/login')->post('/login', [
            'user_code' => '1234',
            'password' => 'password',
        ])->assertSessionHasErrors([
            'user_code' => '社員番号は3桁以内で入力してください。',
        ]);

        $this->assertGuest();
    }

    public function test_password_rejects_non_half_width_alphanumeric(): void
    {
        $user = User::factory()->create();

        $this->from('/login')->post('/login', [
            'user_code' => $user->user_code,
            'password' => '１２３',
        ])->assertSessionHasErrors([
            'password' => 'パスワードは半角英数字で入力してください。',
        ]);

        $this->assertGuest();
    }

    public function test_password_rejects_more_than_ten_characters(): void
    {
        $user = User::factory()->create();

        $this->from('/login')->post('/login', [
            'user_code' => $user->user_code,
            'password' => '12345678901',
        ])->assertSessionHasErrors([
            'password' => 'パスワードは10文字以内で入力してください。',
        ]);

        $this->assertGuest();
    }

    public function test_users_can_logout(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/logout');

        $this->assertGuest();
        $response->assertRedirect('/');
    }
}
