<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminUserPasswordResetTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_admin_can_reset_employee_password_to_initial(): void
    {
        $admin = User::factory()->admin()->create([
            'user_code' => '001',
            'password' => 'changed-password',
        ]);
        $employee = User::factory()->create([
            'password' => 'changed-password',
        ]);

        $this->actingAs($admin)
            ->from(route('admin.users.discard'))
            ->post(route('admin.users.reset-password', $employee->id))
            ->assertRedirect(route('admin.users.discard'))
            ->assertSessionHas('success_message')
            ->assertSessionHas('reset_credential');

        $credential = session('reset_credential');
        $employee->refresh();
        $this->assertTrue($employee->usesInitialPassword());
        $this->assertTrue(Hash::check($credential['password'], $employee->password));
        $this->assertNotSame('password', $credential['password']);
        $this->assertFalse(Hash::check('changed-password', $employee->password));
    }

    public function test_admin_cannot_reset_another_admin_password(): void
    {
        $admin = User::factory()->admin()->create([
            'user_code' => '001',
            'password' => 'changed-password',
        ]);
        $otherAdmin = User::factory()->admin()->create([
            'user_code' => '099',
            'password' => 'changed-password',
        ]);

        $this->actingAs($admin)
            ->post(route('admin.users.reset-password', $otherAdmin->id))
            ->assertNotFound();

        $this->assertTrue(Hash::check('changed-password', $otherAdmin->refresh()->password));
    }

    public function test_employee_cannot_reset_another_users_password(): void
    {
        $employee = User::factory()->create([
            'password' => 'changed-password',
        ]);
        $target = User::factory()->create([
            'password' => 'changed-password',
        ]);

        $this->actingAs($employee)
            ->post(route('admin.users.reset-password', $target->id))
            ->assertRedirect(route('dashboard'));
    }
}
