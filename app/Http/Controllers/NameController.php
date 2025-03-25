<?php

namespace App\Http\Controllers;

use App\Service\TwilioService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Twilio\TwiML\VoiceResponse;

class NameController extends Controller
{

    public string $filePath;

    public function __construct(private TwilioService $twilio)
    {
        $this->filePath = '/home/ddominguez/projects/Results.txt';
    }
    public function checkName (Request $request)
    {
        $name = $request->input('SpeechResult') ?? '';

        $this->twilio->checkName($name);

        return $this->twilio->laravelResponse();
    }
   
    
    public function confirmName (Request $request)
    {
        $yon = $request->input('SpeechResult') ?? '';

        $this->twilio->confirmName($yon);

        return $this->twilio->response();
    }

    public function processName(Request $request)
    {
   
        $response = new VoiceResponse();
        $name2 = $request->query('name2', '');
        $name = $request->input('SpeechResult') ?? $name2;
        $contadorName = (int) $request->query('contadorName', 0);
        $contadorYon = (int) $request->query('contadorYon', 0);

        Log::info('Antes if Process name', [
            'contadorName' => $contadorName,
            'name' => $name,
            'name2' => $name2,
            'contadorYon' => $contadorYon
        ]);

        if (!empty($name2)) {
            Log::info('Name ahora es name2 ' . $contadorName);
            $name = $name2;
        }

        if ($name == 'vacio' || $name == 'null' || $name  == '') {
            if ($contadorName >= 1) {
                Log::info('entra' . $contadorName);
                return $this->finishCall();
            }
            $contadorName = $contadorName + 1;
            $response->say('No escuché su respuesta. Intentémoslo de nuevo. Número de intentos ' . $contadorName, [
                'language' => 'es-ES',
                'voice' => 'Polly.Lucia-Neural',
                'rate' => '1.1'
            ]);
            $response->redirect(url(path: '/api/ManageCall') . '?_method=GET' . "&contador=$contadorName");
            return response($response)->header('Content-Type', 'text/xml');
        }
        $gather = $response->gather([
            'input' => 'dtmf speech',
            'timeout' => '13',
            'action' => url('/api/ProcessName/CheckNameYON') . '?name=' . urlencode($name) . '&contador=' . urlencode($contadorName) . '&contadorYon=' . urlencode($contadorYon),
            'method' => 'POST',
            'language' => 'es-ES',
            'speechModel' => 'googlev2_short',
            'speechTimeout' => '2',
            'actionOnEmptyResult' => true
        ]);
        $gather->say('El nombre recibido es ' . $name . ' confirme si es o no correcto', [
            'language' => 'es-ES',
            'voice' => 'Polly.Lucia-Neural',
            'rate' => '1.1'
        ]);

        return response($response->__toString(), 200)->header('Content-Type', 'text/xml');

        // Log::info('Datos recibidos en processName:', ['name' => $name, 'contador' => $contador]);
        // return $this->AskEmail($request);
    }

    public function giveEmail(): string
    {
        $filePath = storage_path('app/public/Emails.txt');

        if (!file_exists($filePath)) {
            return "Archivo no encontrado";
        }

        $content = file_get_contents($filePath);
        $emails = array_map('trim', explode(',', $content));

        if (empty($emails)) {
            return "No hay emails en el archivo";
        }

        return $emails[array_rand($emails)];
    }

    public function finishCall()
    {
        $response = new VoiceResponse();
        $response->hangup();
        return $response;
    }
}
