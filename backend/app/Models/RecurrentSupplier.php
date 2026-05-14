<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RecurrentSupplier extends Model
{
    protected $fillable = ['name', 'contact_person', 'phone', 'specialty'];
}
