<?php

namespace App\Http\Controllers;

use App\Models\CallLogInfo;

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
        $value = strtolower($value);
        $allowedFields = ['name', 'email', 'company','callerNum','email_IA_confidence'];
    
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
