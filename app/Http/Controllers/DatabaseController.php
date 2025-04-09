<?php

namespace App\Http\Controllers;

use App\Models\CallLogInfo;
use Illuminate\Http\Request;

class DatabaseController extends Controller
{
    public function insertCallSid($callSid)
    {
        CallLogInfo::create([
            'callSid' => $callSid,
        ]);
    }
    public function insertField(string $field, $value)
    {
        $allowedFields = ['name', 'email', 'company','callerNum'];
    
        if (!in_array($field, $allowedFields)) {
            return; // o lanza excepción si prefieres
        }
    
        $lastRow = CallLogInfo::orderByDesc('id')->first();
    
        if ($lastRow) {
            $lastRow->$field = $value;
            $lastRow->save();
        }
    }
    
}
