<?php

namespace App\Models;
// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use PHPOpenSourceSaver\JWTAuth\Contracts\JWTSubject;

class User extends Authenticatable implements JWTSubject
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    public const STATUS_FREE_TRIAL = 'free_trial';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_BLOCKED = 'blocked';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'device_id',
        'device_token',
        'status',
        'trial_ends_at',
        'subscription_starts_at',
        'subscription_ends_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'trial_ends_at' => 'datetime',
            'subscription_starts_at' => 'datetime',
            'subscription_ends_at' => 'datetime',
        ];
    }

    public function isFreeTrial(): bool
    {
        return $this->status === self::STATUS_FREE_TRIAL;
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function isBlocked(): bool
    {
        return $this->status === self::STATUS_BLOCKED;
    }

    public function hasValidAccessPeriod(): bool
    {
        if ($this->status === self::STATUS_ACTIVE) {
            if (!$this->subscription_starts_at || !$this->subscription_ends_at) {
                return false;
            }

            return now()->between($this->subscription_starts_at, $this->subscription_ends_at);
        }

        if ($this->status === self::STATUS_FREE_TRIAL) {
            return $this->trial_ends_at ? now()->lt($this->trial_ends_at) : false;
        }

        return false;
    }

    public function evaluateStatus(): string
    {
        if ($this->status === self::STATUS_BLOCKED) {
            return self::STATUS_BLOCKED;
        }

        if ($this->status === self::STATUS_ACTIVE && $this->hasValidAccessPeriod()) {
            return self::STATUS_ACTIVE;
        }

        if ($this->status === self::STATUS_FREE_TRIAL && $this->trial_ends_at && now()->lt($this->trial_ends_at)) {
            return self::STATUS_FREE_TRIAL;
        }

        return self::STATUS_BLOCKED;
    }

    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims()
    {
        return [];
    }
}
