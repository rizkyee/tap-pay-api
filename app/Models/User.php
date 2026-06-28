<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens;
    use HasFactory;
    use Notifiable;

    public const ROLE_ADMIN = 'admin';
    public const ROLE_CUSTOMER = 'customer';
    public const ROLE_MERCHANT = 'merchant';

    protected $fillable = [
        'name',
        'email',
        'phone',
        'password',
        'transaction_pin',
        'biometric_enabled',
        'role',
        'is_active',
        'last_login_at',
        'email_verified_at',
        'phone_verified_at',
    ];

    protected $hidden = [
        'password',
        'transaction_pin',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'phone_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'is_active' => 'boolean',
            'biometric_enabled' => 'boolean',
            'password' => 'hashed',
            'transaction_pin' => 'hashed',
        ];
    }

    public function wallet(): HasOne
    {
        return $this->hasOne(Wallet::class);
    }

    public function merchantProfile(): HasOne
    {
        return $this->hasOne(MerchantProfile::class);
    }

    public function nfcSessions(): HasMany
    {
        return $this->hasMany(NfcSession::class);
    }

    public function statements(): HasMany
    {
        return $this->hasMany(Statement::class);
    }

    public function hasTransactionPin(): bool
    {
        return ! empty($this->transaction_pin);
    }

    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }

    public function isCustomer(): bool
    {
        return $this->role === self::ROLE_CUSTOMER;
    }

    public function isMerchant(): bool
    {
        return $this->role === self::ROLE_MERCHANT;
    }
}
