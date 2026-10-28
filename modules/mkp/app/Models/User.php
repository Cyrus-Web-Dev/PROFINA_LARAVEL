<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasRoles, Notifiable;

    protected $fillable = [
        'name', 'email', 'password', 'role_label', 'email_verified_at',
        'username', 'phone', 'employee_id', 'job_title', 'department',
        'birth_date', 'address', 'bio', 'education', 'avatar_path',
        'status', 'position', 'approved_by', 'approved_at', 'status_note',
    ];

    protected $hidden = [
        'password', 'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'birth_date' => 'date',
            'education' => 'array',
            'approved_at' => 'datetime',
        ];
    }

    public const ROLE_LABELS = ['admin' => 'Manager', 'staff' => 'Staff', 'viewer' => 'Viewer'];

    public function roleName(): ?string
    {
        return $this->getRoleNames()->first();
    }

    public function roleLabel(): string
    {
        $role = $this->roleName();

        return $role ? (self::ROLE_LABELS[$role] ?? $role) : 'Belum ada role';
    }

    public function positionLabel(): ?string
    {
        return $this->position ? (config("mkp.positions.{$this->position}.label") ?? $this->position) : null;
    }

    /** Akun lama (sebelum kolom status ada) dianggap aktif. */
    public function isActive(): bool
    {
        return ($this->status ?? 'active') === 'active';
    }

    /**
     * URL foto profil (path relatif, aman untuk host mana pun). Parameter ?v= mengubah alamat
     * setiap foto diganti supaya browser tidak menampilkan foto lama dari cache.
     */
    public function getAvatarUrlAttribute(): ?string
    {
        if (! $this->avatar_path) {
            return null;
        }

        return route('profile.photo', ['user' => $this->id, 'v' => $this->updated_at?->timestamp], false);
    }
}
