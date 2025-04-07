<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DataGiven extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'id',
        'uuid',
        'name_given',
        'email_given',
        'company_given',
        'callerNum',
    ];
    
}
