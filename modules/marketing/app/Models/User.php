<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

/** Akun dibuat otomatis saat masuk lewat Portal PROFINA (profina/sso-client). */
class User extends Authenticatable
{
    use HasRoles, Notifiable;

    protected $guard_name = 'web';

    protected $fillable = ['name', 'email', 'password', 'status', 'registration_nip'];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return ['email_verified_at' => 'datetime', 'password' => 'hashed', 'last_login_at' => 'datetime'];
    }

    public function isActive(): bool
    {
        return ($this->status ?? 'active') === 'active';
    }
}
