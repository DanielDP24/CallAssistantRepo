<?php

namespace App\Http\Controllers;

use EchoLabs\Prism\Enums\Provider;
use EchoLabs\Prism\Prism;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Twilio\TwiML\VoiceResponse;

class EmailController extends Controller
{
    public function processEmail(Request $request)
    {
        $email = $request->input('SpeechResult');
        Log::info('Email recibido:', ['rawEmail' => $email]);

        $processedEmail = $this->checkEmailAi($email);

        Log::info('Email procesado por ai:', ['email' => $processedEmail]);

        $response = new VoiceResponse();
        $gather = $response->gather([
            'input' => 'speech',
            'timeout' => 10,
            'action' => url('/api/ProcessEmail/CheckEmailYON'),
            'method' => 'POST',
            'language' => 'es-ES',
            'speechModel' => 'googlev2_long',
            'bargeIn' => true,
            'speechTimeout' => 2,
            'hints' => 'Inditex, Mercadona, Telefónica, Iberdrola, BBVA, Repsol, Mapfre, Acciona, Endesa, Naturgy, Ferrovial, Aena, Mango, Zara, SEAT, Ford España, Volkswagen España, Samsung España'
        ]);

        $gather->say("Gracias por facilitarnos tu email, " . $processedEmail . ". diga sí o no si es o no correcto", [
            'language' => 'es-ES',
            'voice' => 'Polly.Conchita'
        ]);

        return response($response)->header('Content-Type', 'text/xml');
    }

    public function checkEmailAi($email)
    {

        Log::info('Email recibido:' . $email);
        if (empty($email)) {
            return "Email vacio";
        }

        $prompt = <<<EOT
        You are a transcription proofreader. Users will provide you with small snippets of text which have been generated by a speech-to-text program. 
        Your job is to correct and normalize this text to convey the meaning intended by the speaker.
        
        Be particularly mindful of potential errors in email addresses where something like "ddominguez@airzonecontrol.com" 
        is likely to be transcribed as "de dominguez arroba airzone control punto com". 
        
        Also, the user can give explanations for emails like "double a in the middle", meaning the "a" in the middle is double like "aa". 
        This is just an example, but keep in mind that the user can give instructions.

        You just return the email, nothing else.
        
        The email provided is: "$email" 
        EOT;

        $response = Prism::text()
            ->using(Provider::OpenAI, 'gpt-4o-mini')
            ->withPrompt($prompt)
            ->generate()->text;

        return $response;
    }

    public function CheckEmailYON(Request $request)
    {
        $YON = strtolower($request->input('SpeechResult'));
        Log::info('Datos recibidos en processName:', ['YON' => $YON]);
        $response = new VoiceResponse();
        $name = $request->query('name', '');  

        if ($YON == 'si' || $YON == 'sí') {
            $response->say('Respondiste sí.', ['language' => 'es-ES']);
            $gather = $response->gather([
                'input' => 'speech',
                'timeout' => '10',
                'action' => url('/api/ProcessEmail'). '?name=' . urlencode($name),
                'method' => 'POST',
                'language' => 'es-ES',
                'speechModel' => 'googlev2_long',
                'bargeIn' => true,
                'speechTimeout' => 'auto',
            ]);
            $gather->say('Ahora '.$name.' por favor facilítenos su email', ['language' => 'es-ES']);
    
            } elseif ($YON == 'no') {
            $response->say('Respondiste no. Intentémoslo de nuevo.', ['language' => 'es-ES']);
            $response->redirect(url('/api/ManageCall') . '?_method=GET'); // Volver a preguntar el nombre
        } else {
            $response->say('Por favor, responda únicamente con sí o no.', ['language' => 'es-ES']);

            $response->redirect(url('/api/ProcessName') . '?name=' . urlencode($name)); 
            
        }
        
        return response($response->__toString(), 200)->header('Content-Type', 'text/xml');
        
    }


}


 // // Procesamiento del email
        // $processedEmail = strtolower(trim($rawEmail));
        // // $processedEmail = preg_replace([
        // //     '/ arroba /i', 
        // //     '/ punto /i', 
        // //     '/\s+/', 
        // //     '/ nzone /i', 
        // //     '/ erzone /i', 
        // //     '/ air son /i', 
        // //     '/ airzon /i', 
        // //     '/ arison /i', 
        // //     '/ erzon /i', 
        // //     '/ ayrzone /i', 
        // //     '/ air zone /i', 
        // //     '/ airz one /i', 
        // //     '/ aison /i', 
        // //     '/ aizona /i', 
        // //     '/ airzoné /i'
        // // ], ['@', '.', '', 'airzone', 'airzone', 'airzone', 'airzone', 'airzone', 'airzone', 'airzone', 'airzone', 'airzone', 'airzone', 'airzone', 'airzone'], $processedEmail);
        
        // // // Validación de email
        // // if (!filter_var($processedEmail, FILTER_VALIDATE_EMAIL)) {
        // //     Log::warning('Email inválido:', ['email' => $processedEmail]);
        // //     $processedEmail = 'email inválido';
        // // }