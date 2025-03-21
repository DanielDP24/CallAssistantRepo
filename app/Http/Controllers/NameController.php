<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Twilio\TwiML\VoiceResponse;

class NameController extends Controller
{

    public string $filePath;

    public function __construct()
    {
        $this->filePath = '/home/ddominguez/projects/Results.txt';
    }

    //RECIBIMOS nombre Y PREGUNTAMOS SI ES CORRECTO O NO
    public function processName(Request $request)
    {
        $response = new VoiceResponse();
        $name = $request->input('SpeechResult'); //esto recibe pepe
        $id = $request->input('CallSid'); 
        Log::info('El id de la llamada=' . json_encode($id));
        $contador = (int) $request->query('contador', 0);
        

        while ($contador < 3) {
            $contador = $contador + 1;

            if ($name == '' || $name  == null) {
                $name = $request->query('name', '');
            }
            if (empty($name)) {
                Log::info(message: 'El usuario no respondió. Repetimos la pregunta.');

                $response->say('No escuché su respuesta. Intentémoslo de nuevo.', [
                    'language' => 'es-ES',
                    'voice' => 'Polly.Lucia-Neural',
                    'rate' => '1.1'
                ]);
                $response->redirect(url('/api/ManageCall') . '?_method=GET');
                return response($response)->header('Content-Type', 'text/xml');
            }

            $gather = $response->gather([
                'input' => 'dtmf speech',
                'timeout' => '13',
                'action' => url('/api/ProcessName/CheckNameYON') . '?name=' . urlencode($name) . '&contador=' . urlencode($contador),
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
        }
        Log::info('Datos recibidos en processName:', ['name' => $name, 'contador' => $contador]);
        return $this->AskEmail($request);
    }

    public function CheckNameYON(Request $request)
    {
        $response = new VoiceResponse();
        $emailController = new EmailController();


        $contador = (int) $request->query('contador', 0);


        $YON = strtolower($request->input('SpeechResult'));

        $digits = $request->input('Digits'); // Respuesta por teclado

        // Si el usuario usó el teclado, convertir 1 en "sí" y 2 en "no"
        if ($digits == "1") {
            $YON = "sí";
        } elseif ($digits == "2") {
            $YON = "no";
        }

        $YON =  $emailController->checkAnswerYONAI($YON);

        Log::info('Datos recibidos en processName:', ['YON' => $YON]);
        $name = $request->query('name', '');

        if (empty($YON)) {
            Log::info('El usuario no respondió al si o no. Repetimos la pregunta.');
            $response->say('No escuché su respuesta. Intentémoslo de nuevo.', [
                'language' => 'es-ES',
                'voice' => 'Polly.Lucia-Neural',
                'rate' => '1.1'
            ]);
            $response->redirect(url('/api/ProcessName') . '?name=' . urlencode($name) . '&contador=' . urlencode($contador));
            return response($response)->header('Content-Type', 'text/xml');
        }

        if ($YON == 'si' || $YON == 'sí') {
            $response->say('Respondiste sí.', [
                'language' => 'es-ES',
                'voice' => 'Polly.Lucia-Neural',
                'rate' => '1.1'
            ]);
            return $this->AskEmail($request);
        } elseif ($YON == 'no') {
            $response->say('Respondiste no. Intentémoslo de nuevo.', [
                'language' => 'es-ES',
                'voice' => 'Polly.Lucia-Neural',
                'rate' => '1.1'
            ]);
            $response->redirect(url('/api/ManageCall') . '?_method=GET' . '&contador=' . urlencode($contador)); // Volver a preguntar el nombre
        } else {
            $response->say('Por favor, solo indique si es correcto o no es correcto.', [
                'language' => 'es-ES',
                'voice' => 'Polly.Lucia-Neural',
                'rate' => '1.1'
            ]);

            $response->redirect(url('/api/ProcessName') . '?name=' . urlencode($name) . '&contador=' . urlencode($contador));
        }

        return response($response->__toString(), 200)->header('Content-Type', 'text/xml');
    }

    public function AskEmail(Request $request)
    {
        $response = new VoiceResponse();
        $name = $request->query('name', '');
        $contadorEmail = (int) $request->query('contadorEmail', 0);

        $gather = $response->gather([
            'input' => 'speech',
            'timeout' => '10',
            'action' => url('/api/ProcessEmail') . '?name=' . urlencode($name) . '&contadorEmail=' . urlencode($contadorEmail),
            'method' => 'POST',
            'language' => 'es-ES',
            'speechModel' => 'googlev2_short',
            'speechTimeout' => '1',
            'actionOnEmptyResult' => true
        ]);
        $gather->say('Ahora ' . $name . ' por favor facilítenos su email', [
            'language' => 'es-ES',
            'voice' => 'Polly.Lucia-Neural',
            'rate' => '1.1'
        ]);

        return $response;
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
    

}
