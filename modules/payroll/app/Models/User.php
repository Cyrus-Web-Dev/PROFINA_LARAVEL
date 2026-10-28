<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasRoles, Notifiable;

    protected $guard_name = 'web';

    protected $fillable = [
        'name', 'email', 'registration_nip', 'password', 'status', 'avatar_path',
        'must_change_password', 'approved_by', 'approved_at', 'status_note',
    ];

    protected $hidden = [
        'password', 'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'must_change_password' => 'boolean',
            'approved_at' => 'datetime',
            'last_login_at' => 'datetime',
        ];
    }

    public function employee(): HasOne
    {
        return $this->hasOne(Employee::class);
    }

    /** Akun lama tanpa kolom status dianggap aktif. */
    public function isActive(): bool
    {
        return ($this->status ?? 'active') === 'active';
    }

    /**
     * URL foto profil. Parameter ?v= mengganti alamat setiap foto diperbarui
     * supaya browser tidak menampilkan versi lama dari cache.
     */
    public function getAvatarUrlAttribute(): ?string
    {
        if (! $this->avatar_path) {
            return null;
        }

        return route('profile.photo', ['user' => $this->id, 'v' => $this->updated_at?->timestamp], false);
    }
}
