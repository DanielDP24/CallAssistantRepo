<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Twilio\TwiML\VoiceResponse;

class NameController extends Controller
{

    //RECIBIMOS EMAIL Y PREGUNTAMOS SI ES CORRECTO O NO
    public function processName(Request $request)
    {
        $response = new VoiceResponse();
        $name = $request->input('SpeechResult');//esto recibe pepe
        Log::info('Datos recibidos en processName:', ['name' => $name]);

        if ($name == '' || $name  == null) {
            $name = $request->query('name', '');  
            Log::info('Datos recibidos en segunda vez:', ['name' => $name]);
      
        }
        
        $gather = $response->gather([
            'input' => 'speech',
            'timeout' => '10',
            'action' => url('/api/ProcessName/CheckNameYON') . '?name=' . urlencode($name),
            'method' => 'POST',
            'language' => 'es-ES',
            'speechModel' => 'googlev2_long',
            'bargeIn' => true,
            'speechTimeout' => 'auto',
        ]);
        $gather->say('El nombre recibido es ' . $name . ', ¿Es correcto?, responda; si, o ,no.', ['language' => 'es-ES']);

        return response($response->__toString(), 200)->header('Content-Type', 'text/xml');
    }

    public function CheckNameYON(Request $request)
    {
        $YON = strtolower($request->input('SpeechResult'));
        Log::info('Datos recibidos en processName:', ['YON' => $YON]);
        $response = new VoiceResponse();

        if ($YON == 'si' || $YON == 'sí') {
            $response->say('Respondiste sí.', ['language' => 'es-ES']);
            $gather = $response->gather([
                'input' => 'speech',
                'timeout' => '10',
                'action' => url('/api/ProcessEmail'),
                'method' => 'POST',
                'language' => 'es-ES',
                'speechModel' => 'googlev2_long',
                'bargeIn' => true,
                'speechTimeout' => 'auto',
            ]);
            $gather->say('Ahora por favor facilítenos su email', ['language' => 'es-ES']);
    
            } elseif ($YON == 'no') {
            $response->say('Respondiste no. Intentémoslo de nuevo.', ['language' => 'es-ES']);
            $response->redirect(url('/api/ManageCall') . '?_method=GET'); // Volver a preguntar el nombre
        } else {
            $response->say('Por favor, responda únicamente con sí o no.', ['language' => 'es-ES']);

            $name = $request->query('name', '');  
            $response->redirect(url('/api/ProcessName') . '?name=' . urlencode($name)); 
            
        }
        
        return response($response->__toString(), 200)->header('Content-Type', 'text/xml');
        
    }
}
