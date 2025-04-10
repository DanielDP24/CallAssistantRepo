<?php

namespace App\Http\Controllers;

use App\Service\TwilioService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class Incoming extends Controller
{

    public function __construct(private TwilioService $twilio, private DatabaseController $DatabaseController)
    {
    }
    public function askName(Request $request)
    {
        
        $callSid = request()->get('CallSid');
        $this->DatabaseController->insertCallSid($callSid);

        $uuid = $request->input("uuid", '');
        if (empty($uuid)) {
            $this->twilio->createUuid();
        }

        $this->twilio->askName();

        return $this->twilio->laravelResponse();
    }
    
}