<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class RecurrentCustomer extends Authenticatable
{
    use HasApiTokens, Notifiable;

    protected $fillable = [
        'name', 'company', 'customer_id_document', 'email', 'password', 'phone', 
        'location', 'address_full', 'city_dept_country', 'zip_code', 
        'location_reference', 'notes'
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'verification_code_hash',
        'verification_expires_at',
        'verification_attempts',
        // El cupón personal solo lo ve su dueño (CustomerAuthController::me)
        'welcome_coupon',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'verification_expires_at' => 'datetime',
        'welcome_coupon_used_at' => 'datetime',
    ];

    public function isVerified(): bool
    {
        return $this->email_verified_at !== null;
    }
}
