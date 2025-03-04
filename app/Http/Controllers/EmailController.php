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
        $response = new VoiceResponse();
        $email = $request->input('SpeechResult');
        $name = $request->query('name', '');
        $email2 = $request->query('email', '');
    
        if (!empty($email2)) {
            $email = $email2;
        }
        if (empty($email)) {
            Log::info('El usuario no respondió al email. Repetimos la pregunta.');
            $response->redirect(url('/api/ProcessEmail/AskEmail') . '?name=' . urlencode($name));
            return response($response)->header('Content-Type', 'text/xml');
        }
    
        Log::info('Email recibido:', ['rawEmail' => $email]);
    
        $processedEmail = $this->checkEmailAi($email);
    
        Log::info('Email procesado por ai:', ['email' => $processedEmail]);
    
        $gather = $response->gather([
            'input'               => 'dtmf speech',
            'timeout'             => 5,
            'action'              => url('/api/ProcessEmail/CheckEmailYON') . '?name=' . urlencode($name) . '&email=' . urlencode($processedEmail),
            'method'              => 'POST',
            'language'            => 'es-ES',
            'speechModel'         => 'googlev2_short',
            'bargeIn'             => true,
            'speechTimeout' => '2',
            'hints'               => 'Inditex, Mercadona, Telefónica, Iberdrola, BBVA, Repsol, Mapfre, Acciona, Endesa, Naturgy, Ferrovial, Aena, Mango, Zara, SEAT, Ford España, Volkswagen España, Samsung España',
            'actionOnEmptyResult' => true
        ]);
    
        $gather->say("El email facilitado es, " . $processedEmail . ", ¿Es correcto?, pulse uno si es correcto o dos si no lo es", [
            'language' => 'es-ES',
            'voice'    => 'Polly.Conchita',
            'rate'     => '1.3'
        ]);
    
        return response($response)->header('Content-Type', 'text/xml');
    }
    
    public function CheckEmailYON(Request $request)
    {
        $YON = strtolower($request->input('SpeechResult'));
        Log::info('El usuario YON', ['YON EMAIL' => $YON]);
        //COMPROBAMOS SI RESPUESTA NEGATIVA O POSITIVA

        $digits = $request->input('Digits'); // Respuesta por teclado

        // Si el usuario usó el teclado, convertir 1 en "sí" y 2 en "no"
        if ($digits == "1") {
            $YON = "sí";
        } elseif ($digits == "2") {
            $YON = "no";
        }

        $YON = $this->checkAnswerYONAi($YON);

        $name = $request->query('name', '');
        $email = $request->query('email', '');
    
        $response = new VoiceResponse();
    
        // Se elimina la segunda condición redundante
        if (empty($YON)) {
            Log::info('El usuario no respondió al si o no del email. Repetimos la pregunta.');
            $response->say('No le hemos escuchado.', [
                'language' => 'es-ES',
                'voice'    => 'Polly.Conchita',
                'rate'     => '1.2'
            ]);
            $response->redirect(url('/api/ProcessEmail') . '?name=' . urlencode($name) . '&email=' . urlencode($email));
            return response($response)->header('Content-Type', 'text/xml');
        }
    
        Log::info('Datos recibidos en processName:', ['YON' => $YON]);
    
        if ($YON == 'si' || $YON == 'sí') {
            $response->say('Respondiste sí.', [
                'language' => 'es-ES',
                'voice'    => 'Polly.Conchita',
                'rate'     => '1.2'
            ]);
            return $this->AskCompany($request);
        } elseif ($YON == 'no') {
            $response->say('Respondiste no. Intentémoslo de nuevo.', [
                'language' => 'es-ES',
                'voice'    => 'Polly.Conchita',
                'rate'     => '1.2'
            ]);
            $response->redirect(url('/api/ProcessEmail/AskEmail') . '?name=' . urlencode($name));
        } else {
            $response->say('Por favor, responda únicamente con sí o no.', [
                'language' => 'es-ES',
                'voice'    => 'Polly.Conchita',
                'rate'     => '1.2'
            ]);
            $response->redirect(url('/api/ProcessEmail') . '?name=' . urlencode($name) . '&email=' . urlencode($email));
        }
    
        return response($response->__toString(), 200)->header('Content-Type', 'text/xml');
    }
    
    public function AskCompany(Request $request)
    {
        $response = new VoiceResponse();
        $name = $request->query('name', '');
        $email = $request->query('email', '');
        $gather = $response->gather([
            'input' => 'speech',
            'timeout' => '8',
            'action' => url('/api/ProcessCompany') . '?name=' . urlencode($name) . '&email=' . urlencode($email),
            'method' => 'POST',
            'language' => 'es-ES',
            'speechModel' => 'googlev2_short',
            'speechTimeout' => 'auto',
            'actionOnEmptyResult' => true
        ]);
        $gather->say('Ahora ' . $name . ' por favor facilítenos el nombre de su empresa', [
            'language' => 'es-ES',
            'voice' => 'Polly.Conchita',
            'rate' => '1.2'
        ]);
        return $response;
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
        This is just an example, but keep in mind that the user can give instructions: 
        
        Example: "my email is Jose Maria all joined arroba Airzone Control punto com" so you will return josemaria@airzonecontrol.com
        
        If provided is "airsoft"m, or something that contains control behind the @, please swap it for airzonecontrol 

        You just return the email, nothing else.
        
        The email provided is: "$email" 
        EOT;

        $response = Prism::text()
            ->using(Provider::OpenAI, 'gpt-4o-mini')
            ->withPrompt($prompt)
            ->generate()->text;

        return $response;
    }

    public function checkAnswerYONAi($YON)
    {
        Log::info('YON recibido:' . $YON);
        if (empty($YON)) {
            return "Email vacio";
        }

        $prompt = <<<EOT
        You are a professional conversational assistant. 
        Your task is to listen to and analyze user responses that may include phrases such as 
        "si", "no", "está bien", "es correcto", "está mal", "quiero repetir", or "no es así".
         Using your advanced natural language understanding and contextual analysis, deduce whether the 
         response is positive (affirmative) or negative. If the response is positive, simply output "si". 
         If it is negative or non-affirmative, output "no" any other case just answer no. 
         No other possibility than the answers "si" or "no". Ensure your decision is based on all the 
         nuances present in the user's input."$YON" 

         Remember, 1 is yes, and 2 is no
        EOT;

        $response = Prism::text()
            ->using(Provider::OpenAI, 'gpt-4o-mini')
            ->withPrompt($prompt)
            ->generate()->text;

        return $response;
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