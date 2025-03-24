<?php

namespace App\Http\Controllers;

use EchoLabs\Prism\Enums\Provider;
use EchoLabs\Prism\Prism;
use EchoLabs\Prism\Schema\ObjectSchema;
use EchoLabs\Prism\Schema\StringSchema;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Twilio\TwiML\VoiceResponse;

class EmailController extends Controller
{

    public string $filePath;

    public function __construct()
    {
        $this->filePath = '/home/ddominguez/projects/Results.txt';
    }

    public function processEmail(Request $request)
    {
        $response = new VoiceResponse();
        $email = $request->input('SpeechResult');
        $name = $request->query('name', 'Nom');
        $email2 = $request->query('email', '');
        $contadorEmail = (int) $request->query('contadorEmail', 0);

        while ($contadorEmail < 3) {
            if (!empty($email2)) {
                $email = $email2;
            }
            if (empty($email)) {
                $contadorEmail = $contadorEmail + 1;
                Log::info('El usuario no respondió al email. Repetimos la pregunta.');
                $response->redirect(url('/api/ProcessEmail/AskEmail') . '?name=' . urlencode($name) . '&contadorEmail=' . urlencode($contadorEmail));
                return response($response)->header('Content-Type', 'text/xml');
            }

            $processedEmail = $this->checkEmailAi($email);
            $emailLeer = $processedEmail['emailLeer'] ?? "Email Vacio";  // Evita error si falta la clave
            $email = $processedEmail['email'] ?? "Email Vacio";  // Lo mismo para la clave 'email'
            
            Log::info('Email procesado por AI y leído:', ['email' => $email, 'emailLeer' => $emailLeer]);
            
            $gather = $response->gather([
                'input'               => 'speech',
                'timeout'             => 13,
                'action'              => url('/api/ProcessEmail/CheckEmailYON') . '?name=' . urlencode($name) . '&email=' . urlencode($email)  . '&contadorEmail=' . urlencode($contadorEmail),
                'method'              => 'POST',
                'language'            => 'es-ES',
                'speechModel'         => 'googlev2_short',
                'speechTimeout' => '2',
                'hints'               => 'Inditex, Mercadona, Telefónica, Iberdrola, BBVA, Repsol, Mapfre, Acciona, Endesa, Naturgy, Ferrovial, Aena, Mango, Zara, SEAT, Ford España, Volkswagen España, Samsung España',
                'actionOnEmptyResult' => true
            ]);

            // Utilizamos la variable `emailLeer` para lo que se dirá en el sistema TTS.
            $gather->say("El email facilitado es, " . $emailLeer . ",  confirme si es o no correcto", [
                'language' => 'es-ES',
                'voice' => 'Polly.Lucia-Neural',
                'rate' => '1.1'
            ]);

            return response($response)->header('Content-Type', 'text/xml');
        }
        $response->say('Pasemos a la siguiente pregunta', [
            'language' => 'es-ES',
            'voice' => 'Polly.Lucia-Neural',
            'rate' => '1.1'
        ]);
        return $this->AskCompany($request);
    }

    public function CheckEmailYON(Request $request)
    {
        $contadorEmail = (int) $request->query('contadorEmail', 0);

        $contadorEmail = $contadorEmail + 1;
        $YON = strtolower($request->input('SpeechResult'));
        Log::info('El usuario YON', ['YON EMAIL' => $YON, 'contadorEmail' => $contadorEmail]);

        $YON = $this->checkAnswerYONAi($YON);

        $name = $request->query('name', '');
        $email = $request->query('email', '');

        $response = new VoiceResponse();

        // Se elimina la segunda condición redundante
        if (empty($YON)) {
            Log::info('El usuario no respondió al si o no del email. Repetimos la pregunta.');
            $response->say('No le hemos escuchado.', [
                'language' => 'es-ES',
                'voice' => 'Polly.Lucia-Neural',
                'rate' => '1.1'
            ]);
            $response->redirect(url('/api/ProcessEmail') . '?name=' . urlencode($name) . '&email=' . urlencode($email));
            return response($response)->header('Content-Type', 'text/xml');
        }


        if ($YON == 'si' || $YON == 'sí') {
            $response->say('Respondiste sí.', [
                'language' => 'es-ES',
                'voice' => 'Polly.Lucia-Neural',
                'rate' => '1.1'
            ]);
            return $this->AskCompany($request);
        } elseif ($YON == 'no') {
            $response->say('Respondiste no. Intentémoslo de nuevo.', [
                'language' => 'es-ES',
                'voice' => 'Polly.Lucia-Neural',
                'rate' => '1.1'
            ]);
            $response->redirect(url('/api/ProcessEmail/AskEmail') . '?name=' . urlencode($name));
        } else {
            $response->say('Por favor, responda únicamente con sí o no.', [
                'language' => 'es-ES',
                'voice' => 'Polly.Lucia-Neural',
                'rate' => '1.1'
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
            'timeout' => '13',
            'action' => url('/api/ProcessCompany') . '?name=' . urlencode($name) . '&email=' . urlencode($email),
            'method' => 'POST',
            'language' => 'es-ES',
            'speechModel' => 'googlev2_short',
            'speechTimeout' => '1',
            'actionOnEmptyResult' => true
        ]);
        $gather->say('Ahora ' . $name . ' por favor facilítenos el nombre de su empresa', [
            'language' => 'es-ES',
            'voice' => 'Polly.Lucia-Neural',
            'rate' => '1.1'
        ]);
        return $response;
    }
    public function checkEmailAi($email)
    {
        Log::info('Email recibido: ' . $email);
        
        if (empty($email)) {
            return [
                "emailLeer" => "Esto no es un email válido",
                "email" => "Esto no es un email válido"
            ];
        }
    
        $schema = new ObjectSchema(
            name: 'email_transcription',
            description: 'Structured email transcription',
            properties: [
                new StringSchema('email', 'Processed email address for internal use'),
                new StringSchema('readable_email', 'Email formatted for text-to-speech readability')
            ],
            requiredFields: ['email', 'readable_email']
        );
    
        $prompt = <<<EOT
        You are an advanced email transcription proofreader. Users provide snippets of text generated by a speech-to-text program.
        Your job is to correct and normalize these snippets into properly formatted email addresses.
        
        **Step 1: Correct the Email Address**
        - Its VERY important to literally correct it at the order given by the user.
        - Its VERY IMPORTANT not to create any word to complete the email, just correct the bad word given or not completed, but NEVER invent ANY WORD not given
        - Identify and correct common transcription mistakes in email addresses.
        - Replace spoken words with their correct symbols:
          - "arroba" → "@"
          - "punto com" → ".com"
        - Correct misspelled domains or misplaced words:
          - If "airsoft" appears in the domain, replace it with "airzonecontrol".
          - If "control" appears after "@", replace it with "airzonecontrol".
        - Ensure the email format follows "username@domain.tld".
        - If no valid email can be formed, return "Esto no es un email válido".
        
        **Step 2: Generate a Readable Version for TTS**
        - Convert the corrected email into a version optimized for text-to-speech (TTS):
          - The "@" symbol should be spoken as "arroba".
          - The ".com" should be spoken as "punto com".
          - The username and domain should be spaced clearly to enhance pronunciation.
        
        **Example Output:**
        - Email: "ddominguez@airzonecontrol.org"
        - Readable Email: "de dominguez arroba airzone control punto org"
        
        **Your Task:**
        - Return a structured JSON response with two fields:
          1. "email": The correctly formatted email address.
          2. "readable_email": The TTS-friendly version of the corrected email.
        - If no valid email can be formed, return "Esto no es un email válido" for both fields.
        
        The provided email snippet: "$email"
        EOT;
    
        $response = Prism::structured()
            ->using(Provider::OpenAI, 'gpt-4o-mini')
            ->withSchema($schema)
            ->withPrompt($prompt)
            ->generate()->structured;
    
        return [
            "email" => $response['email'] ?? "Email Vacio IA",
            "emailLeer" => $response['readable_email'] ?? "Email Vacio IA"
        ];
    }
    


    public function checkAnswerYONAi($YON)
    {
        Log::info('YON recibido:' . $YON);
        if (empty($YON)) {
            return "";
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

    public function giveCompany(): string
    {
        $filePath = storage_path('app/public/Empresas.txt');

        if (!file_exists($filePath)) {
            return "Archivo no encontrado";
        }

        $content = file_get_contents($filePath);
        $companies = array_map('trim', explode(',', $content));

        if (empty($companies)) {
            return "No hay empresas en el archivo";
        }

        return $companies[array_rand($companies)];
    }
    
}
