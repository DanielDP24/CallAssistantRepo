<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Twilio\TwiML\VoiceResponse;

class CompanyController extends Controller
{
    public function processCompany(Request $request)
    {
        $company  = $request->input('SpeechResult');
        $processedCompany = preg_replace([
            '/ punto /i', 
            '/ nzone /i', 
            '/ erzone /i', 
            '/ air son /i', 
            '/ airzon /i', 
            '/ arison /i', 
            '/ erzon /i', 
            '/ ayrzone /i', 
            '/ air zone /i', 
            '/ airz one /i', 
            '/ aison /i', 
            '/ aizona /i', 
            '/ en zona /i', 
            '/ airzoné /i'
        ], [ '.', 'Airzone', 'Airzone', 'Airzone', 'Airzone', 'Airzone', 'Airzone', 'Airzone', 'Airzone', 'Airzone', 'Airzone', 'Airzone', 'Airzone', 'Airzone', 'Airzone','Airzone'], $company);
        
        Log::info('Datos recibidos en processCompany:', ['company' => $processedCompany]);

        $response = new VoiceResponse();
        $gather = $response->gather([
            'input'         => 'speech',
            'timeout'       => 10,
            'action'        => url('/api/ProcessCompany'),
            'method'        => 'POST',        
            'language'      => 'es-ES',
            'speechModel'   => 'googlev2_long',
            'bargeIn'       => true,
            'speechTimeout' => 2
            
        ]);
        $gather->say('Por favor, dígame el nombre de la empresa.', [
            'language' => 'es-ES', 
            'voice'    => 'Polly.Conchita',
            'rate'     => '1.2'
        ]);
        
        $gather->say("Gracias por facilitarnos tu empresa " . $company, [
            'language' => 'es-ES',
            'voice' => 'Polly.Conchita', 
            'rate' => '1.2'
        ]);
        return response($response->__toString(), 200)->header('Content-Type', 'text/xml');
    }
}
