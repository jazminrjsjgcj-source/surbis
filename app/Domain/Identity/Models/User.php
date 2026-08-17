<?php

declare(strict_types=1);

namespace App\Domain\Identity\Models;

use App\Domain\Identity\Enums\UserStatus;
use App\Domain\Shared\Concerns\HasPublicUlid;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory;

    use HasPublicUlid;
    use Notifiable;

    /** @var list<string> */
    protected $fillable = [
        'name',
        'email',
        'password',
        'status',
        'is_platform_admin',
    ];

    /** @var list<string> */
    protected $hidden = [
        'password',
        'remember_token',
        'mfa_secret',
    ];

    /** @return HasMany<Membership, $this> */
    public function memberships(): HasMany
    {
        return $this->hasMany(Membership::class);
    }

    /**
     * Las autorizaciones para ver identidades confidenciales.
     *
     * RF-AUT-016. Quien la pide no es quien la aprueba, caduca sola y queda
     * auditada: `granted_by` es distinto de `user_id`, y esa separacion es
     * la proteccion —una sola persona no puede autorizarse a si misma a ver
     * quien escribio una queja anonima—.
     *
     * @return HasMany<ConfidentialAccessGrant, $this>
     */
    public function confidentialAccessGrants(): HasMany
    {
        return $this->hasMany(ConfidentialAccessGrant::class);
    }

    /** @return HasMany<MfaRecoveryCode, $this> */
    public function mfaRecoveryCodes(): HasMany
    {
        return $this->hasMany(MfaRecoveryCode::class);
    }

    public function isActive(): bool
    {
        return $this->status === UserStatus::Active;
    }

    public function isPlatformAdmin(): bool
    {
        return $this->is_platform_admin;
    }

    public function hasMfaEnabled(): bool
    {
        return $this->mfa_confirmed_at !== null;
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'immutable_datetime',
            'mfa_confirmed_at' => 'immutable_datetime',
            'password' => 'hashed',
            'password_set_by_other_at' => 'datetime',
            'status' => UserStatus::class,
            'is_platform_admin' => 'boolean',
            'mfa_secret' => 'encrypted',
        ];
    }

    protected static function newFactory(): Factory
    {
        return UserFactory::new();
    }
}
