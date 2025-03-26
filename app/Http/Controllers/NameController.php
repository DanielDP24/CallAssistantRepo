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
    public function checkName(Request $request)
    {
        $name = $request->input('SpeechResult') ?? '';
        Log::info('el nombre recibido es ' . $name); 
        $this->twilio->checkName($name);

        return $this->twilio->laravelResponse();
    }


    public function confirmName(Request $request): VoiceResponse
    {
        //COJE LA RESPUESTA DEL YON
        $yon = $request->input('SpeechResult') ?? '';

        $this->twilio->confirmName($yon);

        return $this->twilio->response();
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
