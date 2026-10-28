<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PasswordHashingTest extends TestCase
{
    use RefreshDatabase;

    public function test_driver_is_argon2id_and_new_passwords_are_argon2id(): void
    {
        $this->assertSame('argon2id', config('hashing.driver'));

        $user = User::create([
            'name' => 'Baru', 'email' => 'baru@example.test', 'password' => 'password123',
            'email_verified_at' => now(), 'status' => 'active', 'role_label' => 'staff',
        ]);

        $this->assertStringStartsWith('$argon2id$', $user->fresh()->password);
        $this->assertTrue(Hash::check('password123', $user->fresh()->password));
    }

    public function test_legacy_bcrypt_user_can_still_login_and_hash_is_upgraded_to_argon2id(): void
    {
        $user = User::create([
            'name' => 'Lama', 'email' => 'lama@example.test', 'password' => 'password123',
            'email_verified_at' => now(), 'status' => 'pending', 'role_label' => 'pending',
        ]);

        // Simulasikan akun lama: hash bcrypt di database (melewati cast "hashed").
        $bcrypt = password_hash('password123', PASSWORD_BCRYPT);
        DB::table('users')->where('id', $user->id)->update(['password' => $bcrypt]);

        // Status "pending": tahap cek password (dan rehash) terjadi sebelum OTP, jadi tes tidak butuh email.
        $this->post('/login', ['email' => 'lama@example.test', 'password' => 'password123']);

        $stored = DB::table('users')->where('id', $user->id)->value('password');
        $this->assertStringStartsWith('$argon2id$', $stored);
        $this->assertTrue(Hash::check('password123', $stored));
    }

    public function test_wrong_password_does_not_change_legacy_hash(): void
    {
        $user = User::create([
            'name' => 'Lama', 'email' => 'lama2@example.test', 'password' => 'password123',
            'email_verified_at' => now(), 'status' => 'active', 'role_label' => 'staff',
        ]);
        $bcrypt = password_hash('password123', PASSWORD_BCRYPT);
        DB::table('users')->where('id', $user->id)->update(['password' => $bcrypt]);

        $this->post('/login', ['email' => 'lama2@example.test', 'password' => 'salah-total'])
            ->assertSessionHasErrors('email');

        $this->assertSame($bcrypt, DB::table('users')->where('id', $user->id)->value('password'));
    }
}
