<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Twilio\TwiML\VoiceResponse;

class NameController extends Controller
{
    public function processName(Request $request)
    {
        $name = $request->input('SpeechResult');
        Log::info('Datos recibidos en processName:', ['name' => $name]);
        $response = new VoiceResponse();
        $gather = $response->gather([
            'input' => 'speech',
            'timeout' => '10',
            'action' => url('/api/ProcessEmail'),
            'method' => 'POST',
            'language' => 'es-ES',
            'speechModel' => 'googlev2_long',
            'bargeIn' => true,          
            'speechTimeout' => 2,
            'hints' => 'arroba,airzonecontrol,punto,hotmail,yahoo,com,net,org'
        ]);

        $gather->say("Gracias, $name. Ahora por favor, facilíteme su email.", [
            'language' => 'es-ES',
            'voice' => 'Polly.Conchita', 
            'rate' => '1.2' 
        ]);

        return response($response->__toString(), 200)->header('Content-Type', 'text/xml');
    }
}
