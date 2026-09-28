<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminUserCreateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_admin_can_register_user_with_email(): void
    {
        $admin = User::factory()->admin()->create([
            'user_code' => '001',
            'password' => 'changed-password',
        ]);

        $this->actingAs($admin)
            ->post(route('admin.users.store'), [
                'users' => [
                    [
                        'emp_id' => '010',
                        'name' => '田中 太郎',
                        'email' => 'taro.tanaka@example.co.jp',
                    ],
                ],
            ])
            ->assertRedirect(route('admin.users.create'))
            ->assertSessionHas('success_message')
            ->assertSessionHas('created_credentials');

        $credentials = session('created_credentials');
        $this->assertIsArray($credentials);
        $this->assertCount(1, $credentials);
        $this->assertSame('田中 太郎', $credentials[0]['name']);
        $this->assertSame('010', $credentials[0]['user_code']);
        $this->assertNotSame('', $credentials[0]['password']);
        $this->assertNotSame(config('library.initial_password'), $credentials[0]['password']);

        $created = User::where('user_code', '010')->first();
        $this->assertNotNull($created);
        $this->assertTrue($created->must_change_password);
        $this->assertTrue(\Illuminate\Support\Facades\Hash::check($credentials[0]['password'], $created->password));

        $this->actingAs($admin)
            ->get(route('admin.users.create'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/UserCreate')
                ->has('flash.created_credentials', 1)
                ->where('flash.created_credentials.0.user_code', '010')
                ->where('flash.created_credentials.0.password', $credentials[0]['password']));

        $this->assertDatabaseHas('users', [
            'user_code' => '010',
            'name' => '田中 太郎',
            'email' => 'taro.tanaka@example.co.jp',
            'role' => 'user',
        ]);
    }

    public function test_admin_can_register_two_users_with_different_passwords(): void
    {
        $admin = User::factory()->admin()->create([
            'user_code' => '001',
            'password' => 'changed-password',
        ]);

        $this->actingAs($admin)
            ->post(route('admin.users.store'), [
                'users' => [
                    [
                        'emp_id' => '010',
                        'name' => '田中 太郎',
                        'email' => 'taro.tanaka@example.co.jp',
                    ],
                    [
                        'emp_id' => '011',
                        'name' => '佐藤 花子',
                        'email' => 'hanako.sato@example.co.jp',
                    ],
                ],
            ])
            ->assertRedirect(route('admin.users.create'))
            ->assertSessionHas('created_credentials');

        $credentials = session('created_credentials');
        $this->assertCount(2, $credentials);
        $this->assertNotSame($credentials[0]['password'], $credentials[1]['password']);
    }

    public function test_email_is_required_with_name_and_employee_id(): void
    {
        $admin = User::factory()->admin()->create([
            'user_code' => '001',
            'password' => 'changed-password',
        ]);

        $this->actingAs($admin)
            ->from(route('admin.users.create'))
            ->post(route('admin.users.store'), [
                'users' => [
                    [
                        'emp_id' => '010',
                        'name' => '田中 太郎',
                        'email' => '',
                    ],
                ],
            ])
            ->assertRedirect(route('admin.users.create'))
            ->assertSessionHasErrors('users');

        $this->assertDatabaseMissing('users', [
            'user_code' => '010',
        ]);
    }

    public function test_duplicate_email_is_rejected(): void
    {
        $admin = User::factory()->admin()->create([
            'user_code' => '001',
            'password' => 'changed-password',
        ]);
        User::factory()->create([
            'user_code' => '020',
            'email' => 'taken@example.co.jp',
            'password' => 'changed-password',
        ]);

        $this->actingAs($admin)
            ->from(route('admin.users.create'))
            ->post(route('admin.users.store'), [
                'users' => [
                    [
                        'emp_id' => '030',
                        'name' => '佐藤 花子',
                        'email' => 'taken@example.co.jp',
                    ],
                ],
            ])
            ->assertRedirect(route('admin.users.create'))
            ->assertSessionHasErrors('users');
    }
}
