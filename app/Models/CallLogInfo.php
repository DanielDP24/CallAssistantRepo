<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CallLogInfo extends Model
{
    public $timestamps = false;
    public $table = 'CallLogInfo';
    protected $fillable = [
        'id',
        'callSid',
        'name',
        'name_given',
        'email',
        'email_given',
        'company',
        'company_given',
        'callerNum',
    ];
}
