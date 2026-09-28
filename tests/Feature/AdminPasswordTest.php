<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminPasswordTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_admin_password_page_is_displayed(): void
    {
        $admin = User::factory()->admin()->create([
            'user_code' => '001',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.password'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Admin/Password'));
    }

    public function test_admin_profile_redirects_to_admin_password_page(): void
    {
        $admin = User::factory()->admin()->create([
            'user_code' => '001',
        ]);

        $this->actingAs($admin)
            ->get(route('profile.edit'))
            ->assertRedirect(route('admin.password'));
    }

    public function test_regular_user_cannot_open_admin_password_page(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('admin.password'))
            ->assertRedirect(route('password.change'));
    }

    public function test_admin_can_update_password_from_admin_page(): void
    {
        $admin = User::factory()->admin()->create([
            'user_code' => '001',
        ]);

        $this->actingAs($admin)
            ->from(route('admin.password'))
            ->put(route('password.update'), [
                'current_password' => 'password',
                'password' => 'newpass12',
                'password_confirmation' => 'newpass12',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('admin.menu'));

        $this->assertTrue(Hash::check('newpass12', $admin->refresh()->password));
    }
}
