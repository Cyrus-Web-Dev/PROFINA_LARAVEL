<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
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

    protected $hidden = ['password', 'remember_token'];

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

    public function assignments(): HasMany
    {
        return $this->hasMany(ModuleAssignment::class);
    }

    public function ssoSessions(): HasMany
    {
        return $this->hasMany(SsoSession::class);
    }

    public function isActive(): bool
    {
        return ($this->status ?? 'active') === 'active';
    }

    /** Punya peran di Portal itu sendiri (pintu "Inti")? */
    public function hasCoreAccess(): bool
    {
        return $this->roles()->exists();
    }

    public function getAvatarUrlAttribute(): ?string
    {
        if (! $this->avatar_path) {
            return null;
        }

        return route('profile.photo', ['user' => $this->id, 'v' => $this->updated_at?->timestamp], false);
    }
}
