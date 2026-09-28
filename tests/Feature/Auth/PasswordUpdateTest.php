<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PasswordUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_password_can_be_updated(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->from('/password/change')
            ->put('/password', [
                'current_password' => 'password',
                'password' => 'newpass12',
                'password_confirmation' => 'newpass12',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('dashboard', absolute: false));

        $this->assertTrue(Hash::check('newpass12', $user->refresh()->password));
    }

    public function test_correct_password_must_be_provided_to_update_password(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->from('/password/change')
            ->put('/password', [
                'current_password' => 'wrong-password',
                'password' => 'newpass12',
                'password_confirmation' => 'newpass12',
            ]);

        $response
            ->assertSessionHasErrors('current_password')
            ->assertRedirect('/password/change');
    }
}
