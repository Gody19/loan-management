<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected $fillable = [
        'fullname',
        'username',
        'email',
        'phone',
        'nida_number',
        'date_of_birth',
        'gender',
        'role',
        'is_active',
        'settings',
        'password',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'date_of_birth' => 'date',
            'password' => 'hashed',
            'settings' => 'array',
        ];
    }

    public function loans()
    {
        return $this->hasMany(Loan::class);
    }

    public function processedLoans()
    {
        return $this->hasMany(Loan::class, 'processed_by');
    }

    public function portfolios()
    {
        return $this->hasMany(Portfolio::class);
    }

    public function budgets()
    {
        return $this->hasMany(Budget::class);
    }

    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }
}
