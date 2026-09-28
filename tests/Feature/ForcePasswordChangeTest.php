<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ForcePasswordChangeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_employee_with_initial_password_is_sent_to_change_screen(): void
    {
        $user = User::factory()->create();

        $this->post('/login', [
            'user_code' => $user->user_code,
            'password' => 'password',
        ])->assertRedirect(route('password.change'));

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertRedirect(route('password.change'));
    }

    public function test_admin_with_initial_password_is_sent_to_admin_change_screen(): void
    {
        $admin = User::factory()->admin()->create([
            'user_code' => '001',
        ]);

        $this->post('/login', [
            'user_code' => $admin->user_code,
            'password' => 'password',
        ])->assertRedirect(route('admin.password'));

        $this->actingAs($admin)
            ->get(route('admin.menu'))
            ->assertRedirect(route('admin.password'));
    }

    public function test_employee_can_use_app_after_changing_initial_password(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->from(route('password.change'))
            ->put(route('password.update'), [
                'current_password' => 'password',
                'password' => 'newpass12',
                'password_confirmation' => 'newpass12',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('dashboard'));

        $this->assertTrue(Hash::check('newpass12', $user->refresh()->password));

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk();
    }

    public function test_employee_can_open_password_change_after_initial_change(): void
    {
        $user = User::factory()->create([
            'password' => 'changed-password',
        ]);

        $this->actingAs($user)
            ->get(route('password.change'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Auth/ChangePassword'));
    }

    public function test_initial_password_cannot_be_set_again(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->from(route('password.change'))
            ->put(route('password.update'), [
                'current_password' => 'password',
                'password' => 'password',
                'password_confirmation' => 'password',
            ])
            ->assertSessionHasErrors('password')
            ->assertRedirect(route('password.change'));
    }
}
