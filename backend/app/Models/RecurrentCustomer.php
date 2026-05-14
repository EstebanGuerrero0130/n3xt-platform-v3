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
    ];
}
