<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\GmailApiService;
use App\Services\OtpService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserApprovalTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Jangan pernah menghubungi Google saat tes.
        $this->mock(GmailApiService::class, fn ($mock) => $mock->shouldReceive('send')->andReturnNull());
    }

    private function pendingUser(array $attrs = []): User
    {
        return User::create(array_merge([
            'name' => 'Calon Staf',
            'email' => 'calon@example.test',
            'password' => 'password123',
            'email_verified_at' => now(),
            'status' => 'pending',
            'role_label' => 'pending',
        ], $attrs));
    }

    public function test_pending_user_is_told_to_wait_and_cannot_login(): void
    {
        $this->pendingUser();

        $this->post('/login', ['email' => 'calon@example.test', 'password' => 'password123'])
            ->assertSessionHasErrors('email');

        $this->assertStringContainsString('menunggu persetujuan', session('errors')->first('email'));
        $this->assertGuest();
    }

    public function test_first_registrant_becomes_active_manager_and_second_waits_for_approval(): void
    {
        $otp = app(OtpService::class);

        foreach (['pertama@example.test', 'kedua@example.test'] as $email) {
            $code = $otp->generate("register:{$email}");
            $this->withSession(['register.pending' => ['name' => 'Orang', 'email' => $email, 'password' => Hash::make('password123')]])
                ->post(route('register.verify'), ['code' => $code]);
            auth()->logout();
        }

        $first = User::where('email', 'pertama@example.test')->first();
        $second = User::where('email', 'kedua@example.test')->first();

        $this->assertSame('active', $first->status);
        $this->assertTrue($first->hasRole('admin'));
        $this->assertSame('pending', $second->status);
        $this->assertFalse($second->roles()->exists());
    }

    public function test_manager_approves_pending_user_with_role_and_specific_permissions(): void
    {
        $admin = $this->makeUser('admin');
        $pending = $this->pendingUser();

        $this->actingAs($admin)->post(route('admin.users.approve', $pending), [
            'role' => 'staff',
            'position' => 'finance',
            'permissions' => ['ar.manage', 'ap.manage'],
            'note' => 'Selamat bergabung',
        ])->assertSessionHasNoErrors();

        $fresh = User::find($pending->id);
        $this->assertSame('active', $fresh->status);
        $this->assertSame('finance', $fresh->position);
        $this->assertTrue($fresh->hasRole('staff'));
        $this->assertTrue($fresh->can('ar.manage'));
        $this->assertFalse($fresh->can('journal.create'));
        $this->assertTrue($fresh->can('reports.view'));
    }

    public function test_rejecting_requires_a_reason(): void
    {
        $admin = $this->makeUser('admin');
        $pending = $this->pendingUser();

        $this->actingAs($admin)->post(route('admin.users.reject', $pending), [])->assertSessionHasErrors('reason');

        $this->actingAs($admin)->post(route('admin.users.reject', $pending), ['reason' => 'Bukan karyawan'])
            ->assertSessionHasNoErrors();
        $this->assertSame('rejected', User::find($pending->id)->status);
    }

    public function test_staff_without_permission_is_forbidden_and_viewer_can_only_read(): void
    {
        $staff = $this->makeUser('staff');            // tanpa hak akses tambahan
        $viewer = $this->makeUser('viewer');

        $this->actingAs($staff)->post(route('journals.store'), [])->assertForbidden();
        $this->actingAs($viewer)->post(route('journals.store'), [])->assertForbidden();
        $this->actingAs($viewer)->get(route('reports.trial-balance'))->assertOk();
    }

    public function test_non_manager_cannot_open_admin_pages(): void
    {
        $staff = $this->makeUser('staff', ['journal.create']);

        $this->actingAs($staff)->get(route('admin.users.index'))->assertForbidden();
        $this->actingAs($staff)->get(route('admin.backup.index'))->assertForbidden();
        $this->actingAs($staff)->get(route('admin.periods.index'))->assertForbidden();
    }

    public function test_deactivated_user_is_kicked_out_on_next_request(): void
    {
        $admin = $this->makeUser('admin');
        $staff = $this->makeUser('staff');

        $this->actingAs($admin)->post(route('admin.users.deactivate', $staff), ['reason' => 'Resign'])->assertSessionHasNoErrors();

        $this->actingAs(User::find($staff->id))->get(route('reports.trial-balance'))->assertRedirect(route('login'));
    }

    public function test_the_only_manager_cannot_demote_themselves(): void
    {
        $admin = $this->makeUser('admin');

        $this->actingAs($admin)->put(route('admin.users.access', $admin), ['role' => 'viewer'])
            ->assertSessionHasErrors('general');

        $this->assertTrue(User::find($admin->id)->hasRole('admin'));
    }
}
