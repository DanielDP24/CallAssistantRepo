<?php

namespace App\Service;

use App\Http\Controllers\DatabaseController;
use Prism\Prism\Enums\Provider;
use Prism\Prism\Prism;
use Prism\Prism\Schema\ObjectSchema;
use Prism\Prism\Schema\StringSchema;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Twilio\TwiML\VoiceResponse;

class TwilioService
{
    private readonly Voiceresponse $response;
    private string $uuid;

    public function __construct(private DatabaseController $DatabaseController)
    {
        $this->response = new VoiceResponse;
        $this->uuid = request()->input('uuid', '');
    }

    //NAME
    public function askName(): void
    {
        Log::info('pedimos nombre.');
        $justStarted = $this->getCallData("just_started") ?? '';
        if ($justStarted == '') {
            $this->say(speech: 'Hola, has llamado a Air zo ne.   Le solicitaremos unos datos antes de redirigirle con uno de nuestros agentes.');
        }
        $this->saveCallData("just_started", 'comenzado');

        $this->gather(
            action: url("/api/ProcessName"),
            hints: 'Juan, María, José, Jose, Carmen, Antonio, Ana, Manuel, Laura, Francisco, Lucia, David, Paula, Javier, Elena, Miguel, Sara, Carlos, Patricia, Pedro, Andrea, Luis, Marta, Sergio, Raúl, Rosa, Guillermo, Nuria, Alberto, Irene, Jorge, Beatriz, Ricardo, Cristina, Víctor, Silvia, Alejandro, Mario, Isabel, Diego, Gloria, Fernando, Claudia, Roberto, Teresa, Andrés, Mercedes, Julio, Sonia, Ramón, Inmaculada, Marcos, Concepción, Ángel, Estrella, Mariano, Lourdes, Jaime, Susana, Octavio, Esperanza, Adrián, Benito, Rebeca, Enrique, Soledad, Santiago, Amparo, Armando, Carolina, Eloy, Dolores, Damián, Fátima, Gonzalo, Jacinta, Hilario, Irma, Mauricio, Josefina, Ernesto, Liliana, Federico, Martina, Blanca, Oscar, Clara, Ismael, Juana, Hugo, Pilar, Valentín',
            speech: 'Por favor, dígame su nombre'
        );
    }
    
    public function checkName(string $name): void
    {
        //GUARDAMOS NOMBRE TEMPORALMENTE
        if ($this->getCallData('temp_name') == '') {
            $this->saveCallData('temp_name', $name);
        } else { // Recupera el valor guardado por repetir la pregunta
            $name = $this->getCallData('temp_name') ?? '';
        }

        $silenceNameCounter = (int) $this->getCallData("name_silence_counter") ?? 0;
        $isSilence = $this->isSilence($name);

        //SI ES SILENCIO 3   VECES, CUELGA
        if ($isSilence && $silenceNameCounter >= 2) {
            $this->response->hangup();
            return;
        }

        //PRIMERA VEZ SILENCIO, PASA Y SUMA CONTADOR0
        if ($isSilence) {
            //CREA CONTADOR Y SUMA
            $nameSilenceCounter = (int) $this->getCallData("name_silence_counter") ?? 0;
            $nameSilenceCounter++;
            $this->saveCallData("name_silence_counter", $nameSilenceCounter);

            //REDIRIJE CON UUID YA QUE ES LA MISMA LLAMADA
            $this->say('No escuché su respuesta. Intentémoslo de nuevo. Número de intentos ' . $nameSilenceCounter);
            Log::info('reintentando la pregunta del nombre');
            $this->response->redirect(url('/api/ManageCall') . '?_method=GET' . "&uuid=$this->uuid");
            return;
        }
        //SI NO HAY SILENCIO, LLEVA EL NOMBRE A CONFIRMAR CON YON PREGUNTA.
        $this->saveCallData("name_silence_counter", 0);
        Log::info('Pedir confirmación del nombre');
        $this->gather(
            action: url('/api/ProcessName/CheckNameYON'),
            speech: 'El nombre recibido es ' . $name . ' confirme si es o no correcto'
        );
    }

    public function confirmName(string $yon)
    {
        // Obtener contadores y evaluar si es silencio
        $yonSilenceNameCounter = (int) ($this->getCallData("yon_silence_name_counter") ?? 0);
        $isSilence = $this->isSilence($yon);

        // Si hay silencio dos veces, pasamos a email
        if ($isSilence) {
            if ($yonSilenceNameCounter >= 2) {
                Log::info('\n --- Por silencio en confirm name preguntamos email ---- \n');
                $this->response->redirect(url("/api/ProcessEmail/AskEmail?uuid=$this->uuid"));
                return;
            }

            // Aumentar el contador de silencio y reintentar la confirmación del nombre
            $yonSilenceNameCounter++;
            $this->saveCallData("yon_silence_name_counter", $yonSilenceNameCounter);
            Log::info('\n --- Reintentando confirm name ---- \n');
            $this->say('no escuchamos su respuesta, lo intentamos de nuevo');

            $name = $this->getCallData('temp_name');
            $this->checkName($name);
            return;
        }

        // Resetear el contador de silencio ya que no hubo silencio
        $this->saveCallData("yon_silence_name_counter", 0);

        // Obtener contador de "no" en confirmación
        $yonNameCounter = (int) ($this->getCallData("yon_name_counter") ?? 0);

        // Si se rechaza el nombre dos veces, pasamos a email
        if (!$this->isAConfirm($yon)) {
            if ($yonNameCounter >= 2) {
                Log::info('\n ---Pasamos a preguntar por el email---- \n');
                $this->response->redirect(url("/api/ProcessEmail/AskEmail?uuid=$this->uuid"));
                return;
            }

            // Aumentar el contador y redirigir para otro intento
            $yonNameCounter++;
            $this->saveCallData('temp_name', '');
            $this->saveCallData("yon_name_counter", $yonNameCounter);
            $this->response->redirect(url("/api/ManageCall") . '?_method=GET' . "&uuid=$this->uuid");
            return;
        }

        // Si el usuario confirma el nombre, reiniciamos contadores y continuamos
        $this->saveCallData("yon_name_counter", 0);

        // Guardar el nombre definitivo y continuar
        $name = $this->getCallData('temp_name');
        $this->saveCallData('name', $name);
        $this->DatabaseController->insertField('name', $name);
        $this->response->redirect(url("/api/ProcessEmail/AskEmail?uuid=$this->uuid"));
    }

    //EMAIL
    public function askEmail(): void
    {
        $name = $this->getCallData("name") ?? '';

        $this->gather(
            action: url("/api/ProcessEmail"),
            speech: "Ahora por favor $name, facilítenos su email"
        );

        Log::info('Pedimos el email.');
    }

    public function checkEmail(string $email)
{
    Log::info("\n llegamos a comprobar su email \n");

    if ($this->getCallData('temp_email') == '') {
        $this->saveCallData('temp_email', $email);
    } else {
        $email = $this->getCallData('temp_email') ?? '';
    }

    $silenceEmailCounter = (int) $this->getCallData("email_silence_counter") ?? 0;
    $isSilence = $this->isSilence($email);

    if ($isSilence && $silenceEmailCounter >= 1) {
        $this->response->redirect(url("/api/ProcessCompany/AskCompany?uuid=$this->uuid"));
        return;
    }

    if ($isSilence) {
    $emailSilenceCounter = (int) $this->getCallData("email_silence_counter") ?? 0;
        $emailSilenceCounter++;
$this->saveCallData("email_silence_counter", $emailSilenceCounter);

        $this->say('No escuché su respuesta. Intentémoslo de nuevo. Número de intentos ' . $emailSilenceCounter);
        Log::info('reintentando EMAIL');
        $this->askEmail();
        return;
    }

    // PROMPT
    $prompt = <<<EOT
You are an advanced email transcription proofreader. Users provide snippets of text generated by a speech-to-text program.
Your job is to correct and normalize these snippets into properly formatted email addresses.

**Step 1: Correct the Email Address**
- Its VERY important to literally correct it at the order given by the user.
- Do not add, delete, or assume any information beyond what was provided.
- Its VERY IMPORTANT not to create any word to complete the email, just correct the bad word given or not completed, but NEVER invent ANY WORD not given by the user.
- Identify and correct common transcription mistakes in email addresses.
- Replace spoken words with their correct symbols:
  - "arroba" → "@"
  - "punto" → "."
  - "punto com" → ".com"
  - "punto es" → ".es"
  - "punto org" → ".org"
  - "punto net" → ".net"
  - "jimeil punto com" → "gmail.com"
  - "ese hache" → "sh"
  - If "airsoft" appears in the domain, replace it with "airzonecontrol".
  - If "control" appears after "@", replace it with "airzonecontrol".
- Ensure the email format follows "username@domain.tld".
- These are all valid gTLDs for the email: '.com', '.net', '.org', '.info', '.biz', '.name', '.pro', '.co', '.me', '.io', '.email', '.tech', '.dev', '.app', '.shop', '.store', '.blog', '.online', '.site', '.website', '.company', '.agency', '.solutions', '.digital', '.live', '.media', '.studio', '.es', '.mx', '.ar', '.cl', '.pe', '.us', '.uk', '.fr', '.de', '.it', '.br', '.ca', '.au', '.cn', '.jp', '.ru', '.edu', '.gov', '.mil', '.int', '.aero', '.jobs', '.museum', '.travel'

**Step 2: Generate a Readable Version for TTS**
- Convert the corrected email into a version optimized for text-to-speech (TTS):
  - The "@" symbol should be spoken as "arroba".
  - The ".com" should be spoken as "punto com".
  - The username and domain should be spaced clearly to enhance pronunciation.

**Example Output:**
- Email: "ddominguez@airzonecontrol.org"
- Readable Email: "de dominguez arroba airzone control punto org"

**Your Task:**
- Return a response with two fields in JSON:
  {
    "email": "ddominguez@airzonecontrol.org",
    "readable_email": "de dominguez arroba airzone control punto org"
  }

The provided email snippet: "$email"
EOT;

    try {
        $payload = [
            'model' => 'gpt-4o',
            'logprobs' => true,
            'temperature' => 0,
            'messages' => [
                ['role' => 'system', 'content' => 'You return email JSON, and can be evaluated for confidence.'],
                ['role' => 'user', 'content' => $prompt],
            ]
        ];

        $client = new \GuzzleHttp\Client();
        $apiResponse = $client->post('https://api.openai.com/v1/chat/completions', [
            'headers' => [
                'Authorization' => 'Bearer ' . env('OPENAI_API_KEY'),
                'Content-Type' => 'application/json',
            ],
            'body' => json_encode($payload),
        ]);

        $data = json_decode($apiResponse->getBody(), true);

        $responseText = $data['choices'][0]['message']['content'] ?? '';
        $tokens = $data['choices'][0]['logprobs']['content'] ?? [];
        
        Log::info("Respuesta cruda de OpenAI: " . $responseText);
        
        // Extraer JSON embebido del contenido
        preg_match('/\{.*\}/s', $responseText, $matches);
        $jsonExtracted = $matches[0] ?? '{}';
        
        // Cálculo de confidence
        $confidences = array_map(fn($token) => exp($token['logprob']), $tokens);
        $averageConfidence = count($confidences) > 0 ? array_sum($confidences) / count($confidences) : 0.0;
        $formattedConfidence = number_format($averageConfidence * 100, 2, '.', ''); 
        $this->saveCallData('email_confidence', $formattedConfidence);
        
        Log::info("Confianza del modelo sobre email: $averageConfidence");
        
        // Parsear JSON extraído
        $parsed = json_decode($jsonExtracted, true);
        $email = $parsed['email'] ?? 'Email Vacio IA';
        $emailLeer = $parsed['readable_email'] ?? 'Email Vacio IA';
        

    } catch (\Exception $e) {
        Log::error("Error procesando respuesta de OpenAI: " . $e->getMessage());
        $email = "Email Vacio IA";
        $emailLeer = "Email Vacio IA";
    }

    // GUARDAR Y CONTINUAR FLUJO
    $this->saveCallData('temp_email', $email);
    $this->saveCallData('temp_email_leer', $emailLeer);
    $this->saveCallData("email_silence_counter", 0);

    $this->gather(
        action: url("/api/ProcessEmail/CheckEmailYON"),
        speech: 'El email recibido es ' . $emailLeer . ' confirme si es o no correcto'
    );
}

    public function confirmEmail(string $yon)
    {
        // Obtener contadores y evaluar si es silencio
        $yonSilenceEmailCounter = (int) ($this->getCallData("yon_silence_email_counter") ?? 0);
        $isSilence = $this->isSilence($yon);
        // Si hay silencio dos veces, pasamos a company
        if ($isSilence) {
            if ($yonSilenceEmailCounter >= 1) {
                Log::info('\n --- Por silencio en confirm email preguntamos company ---- \n');
                $this->response->redirect(url("/api/ProcessCompany/AskCompany?uuid=$this->uuid"));
                return;
            }

            // Aumentar el contador de silencio y reintentar la confirmación del email
            $yonSilenceEmailCounter++;
            $this->saveCallData("yon_silence_email_counter", $yonSilenceEmailCounter);
            Log::info('\n --- Reintentando confirm email ---- \n');
            $this->say('no escuchamos su respuesta, lo intentamos de nuevo');
            $email = $this->getCallData('temp_email');
            $this->checkEmail($email);
            return;
        }

        // Resetear el contador de silencio ya que no hubo silencio
        $this->saveCallData("yon_silence_email_counter", 0);

        // Obtener contador de "no" en confirmación
        $yonEmailCounter = (int) ($this->getCallData("yon_email_counter") ?? 0);

        // Si se rechaza el email dos veces, pasamos a company
        if (!$this->isAConfirm($yon)) {
            if ($yonEmailCounter >= 1) {
                Log::info('\n --- NO es correcto el email 2 veces ---- \n');
                $this->response->redirect(url("/api/ProcessCompany/AskCompany?uuid=$this->uuid"));
                return;
            }

            // Aumentar el contador y redirigir para otro intento
            $yonEmailCounter++;
            $this->saveCallData('temp_email', '');
            $this->saveCallData("yon_email_counter", $yonEmailCounter);
            $this->response->redirect(url("/api/ProcessEmail/AskEmail?uuid=$this->uuid"));
            return;
        }

        // Si el usuario confirma el email, reiniciamos contadores y continuamos
        $this->saveCallData("yon_email_counter", 0);

        // Guardar el email definitivo y continuar
        $email = $this->getCallData('temp_email');
        Log::info('\n --- SI, es correcto el EMAIL ----');
        $this->saveCallData('email', $email);
        $this->DatabaseController->insertField('email', $email);

        $this->response->redirect(url("/api/ProcessCompany/AskCompany?uuid=$this->uuid"));
    }

    //COMPANY
    public function askCompany(): void
    {

        $name = $this->getCallData("name") ?? '';

        $this->gather(
            action: url("/api/ProcessCompany"),
            speech: "Ahora por favor $name, facilítenos el nombre de su empresa"
        );
        Log::info('Pedimos el nombre de su empresa.');
    }
    public function checkCompany(string $company)
    {
        //GUARDAMOS company TEMPORALMENTE
        if ($this->getCallData('temp_company') == '') {
            $this->saveCallData('temp_company', $company);
        } else {
            $company = $this->getCallData('temp_company') ?? '';
            // Recupera el valor guardado
        }

        $silenceCompanyCounter = (int) $this->getCallData("company_silence_counter") ?? 0;
        $isSilence = $this->isSilence($company);

        //SI ES SILENCIO 3   VECES, CUELGA
        if ($isSilence && $silenceCompanyCounter >= 2) {
            $this->response->hangup();
            return;
        }

        //PRIMERA VEZ SILENCIO, PASA Y SUMA CONTADOR
        if ($isSilence) {
            //CREA CONTADOR Y SUMA
            $companySilenceCounter = (int) $this->getCallData("company_silence_counter") ?? 0;
            $companySilenceCounter++;
            $this->saveCallData("company_silence_counter", $companySilenceCounter);

            //REDIRIJE CON UUID YA QUE ES LA MISMA LLAMADA
            $this->say('No escuché su respuesta. Intentémoslo de nuevo. Número de intentos ' . $companySilenceCounter);
            $this->askCompany();
            return;
        }
        //SI NO HAY SILENCIO, LLEVA company A CONFIRMAR CON YON PREGUNTA.
        $this->saveCallData("company_silence_counter", 0);
        $this->gather(
            action: url("/api/ProcessCompany/CheckCompanyYON"),
            speech: 'El nombre de la empresa recibido es ' . $company . ' , confirme si es o no correcto'
        );
    }
    public function confirmCompany(string $yon)
    {
        // Obtener contadores y evaluar si es silencio
        $yonSilenceCompanyCounter = (int) ($this->getCallData("yon_silence_company_counter") ?? 0);
        $isSilence = $this->isSilence($yon);

        // Si hay silencio tres veces, colgamos la llamada
        if ($isSilence) {
            if ($yonSilenceCompanyCounter >= 2) {
                Log::info('\n --- si silencio 3 veces en confirmación, colgamos llamada ---- \n');
                $this->response->hangup();
                return;
            }

            // Aumentar el contador de silencio y reintentar la confirmación del company
            $yonSilenceCompanyCounter++;
            $this->saveCallData("yon_silence_company_counter", $yonSilenceCompanyCounter);


            $company = $this->getCallData('temp_company');
            Log::info("\n --- Reintentando confirm company  $company  ---- \n");
            $this->checkCompany($company);
            return;
        }

        // Resetear el contador de silencio ya que no hubo silencio
        $this->saveCallData("yon_silence_company_counter", 0);

        // Obtener contador de "no" en confirmación
        $yonCompanyCounter = (int) ($this->getCallData("yon_company_counter") ?? 0);


        //Cogemos los datos y los en enviamos todos por url hacia hubspot
        $company = $this->getCallData('temp_company');
        $this->saveCallData('company', $company);
        $this->DatabaseController->insertField('company', $company);

        $name = rawurlencode($this->getCallData('name') ?? '');
        $nameGiven = rawurlencode($this->getCallData('name_given') ?? '');
        $email = rawurlencode($this->getCallData('email') ?? '');
        $emailGiven = rawurlencode($this->getCallData('email_given') ?? '');
        $company = rawurlencode($this->getCallData('company') ?? '');
        $companyGiven = rawurlencode($this->getCallData('company_given') ?? '');


        // Si se rechaza el company dos veces, pasamos a hubspot y redirigimos
        if (!$this->isAConfirm($yon)) {
            if ($yonCompanyCounter >= 2) {
                Log::info('\n --- NO es correcto 3 veces ---- \n');

                $this->response->redirect(url()->query(path: "/api/EndCall", query: [
                    'uuid' => $this->uuid,
                    'name' => $name,
                    'name_given' => $nameGiven,
                    'email' => $email,
                    'email_given' => $emailGiven,
                    'company' => $company,
                    'company_given' => $companyGiven
                ]));
                return;
            }
            Log::info('\n --- NO es correcto ' . $yonCompanyCounter . ' veces ---- \n');
            // Aumentar el contador y redirigir para otro intento
            $yonCompanyCounter++;
            $this->saveCallData('temp_company', '');
            $this->saveCallData("yon_company_counter", $yonCompanyCounter);
            $this->response->redirect(url("/api/ProcessCompany/AskCompany?uuid=$this->uuid"));
            return;
        }

        // Si el usuario confirma el nombre de la empresa, reiniciamos contadores y continuamos
        $this->saveCallData("yon_company_counter", 0);
        Log::info('\n --- SI, es correcto company ---- \n');

        $this->response->redirect(url()->query(path: "/api/EndCall", query: [
            'uuid' => $this->uuid,
            'name' => $name,
            'email' => $email,
            'company' => $company
        ]));
        return;
    }


    //LLAMADA AJUSTES Y DEVUELTA DE DATOS
    public function response(): VoiceResponse
    {
        return $this->response;
    }
    public function laravelResponse()
    {
        return response($this->response->__toString(), 200)->header('Content-Type', 'text/xml');;
    }
    public function gather(string $action, string $hints = '', ?string $speech = null): void
    {
        if (empty($this->uuid)) {
            throw new \Exception("Uuid is not set");
        }

        Log::info('uuid es : ' . $this->uuid);
        Log::info('action es : ' . $action);

        $gather = $this->response->gather([
            'input' => 'speech',
            'timeout' => '15',
            'action' => $action . "?uuid=$this->uuid",
            'method' => 'POST',
            'language' => 'es-ES',
            'speechModel' => 'googlev2_short',
            'speechTimeout' => '1',
            'actionOnEmptyResult' => true,
            'hints' => $hints,
        ]);

        if ($speech !== null) {
            $gather->say($speech, $this->voiceConfig());
        }
    }
    public function say(string $speech)
    {
        $this->response->say($speech, $this->voiceConfig());
    }
    private function voiceConfig(): array
    {
        return [
            'language' => 'es-ES',
            'voice' => 'Polly.Lucia-Neural',
            'rate' => '1'
        ];
    }

    //DATOS PRUEBAS
    private function isSilence(string $param): bool
    {
        $isParamValid = !($param == 'null' || empty($param));

        Log::info('is param valid ' . $isParamValid ? 'true' : 'false');
        if (!$isParamValid) {
            return true;
        }

        return false;
    }
    private function isAConfirm(string $yon): bool
    {

        Log::info('YON recibido:' . $yon);

        $schema = new ObjectSchema(
            name: 'yon_transcription',
            description: 'afirmative or negative answer',
            properties: [
                new StringSchema('yon', 'Detects if the answer is afirmative or negative'),
            ],
            requiredFields: ['yon']
        );
        $prompt = <<<EOT
        You are an expert intent-classification assistant whose accuracy is paramount.  
        Your sole job is to inspect the user’s reply (variable \$yon) and output exactly one of:
        
          — “si” if the user’s intent can reasonably be interpreted as affirmative, positive, or consenting.  
          — “no” if the user clearly rejects, negates, doubts, corrects, asks to repeat, or expresses any negative or non‑consenting intent.  
        
        Key rules:
        
         1. **Lean positive whenever possible.**  
            - If there is any plausible indication of acceptance, agreement, or approval—even subtle or implied—choose “si.”  
            - Only choose “no” when the user explicitly or unmistakably expresses negation, refusal, correction, or asks to repeat.
        
         2. **Don’t treat ambiguous neutrality as negative.**  
            - If the user simply says “ok,” “vale,” “adelante,” or anything that could count as assent, that is “si.”  
            - Only “no,” “no es así,” “está mal,” “quiero repetir,” “prefiero que no,” etc., are “no.”
        
         3. **Ignore isolated keywords.**  
            - Always interpret the full meaning and context.  
            - “confirmo” by itself counts as “si” (positive confirmation).
        
        4. **No extra text:** output exactly “si” or “no”.
        
        Examples:
          • “sí”, “vale”, “confirmo”, “adelante” → si  
          • “no”, “no es así”, “está mal”, “quiero repetir” → no  
        
        USER INPUT: $yon
        EOT;
        

        $response = Prism::structured()
            ->using(Provider::OpenAI, 'gpt-4o-mini')
            ->withSchema($schema)
            ->withPrompt($prompt)
            ->asStructured()->text;

        $data = json_decode($response, true);
        $response = $data['yon'];

        Log::info('YON PASADO IA:' . $response);

        if ($response == 'si' || $response == 'sí') {
            return true;
        }
        return false;
    }
    public function isOutOfTries(): bool
    {
        $tries = 1;
        $nameSilenceCounter = (int) $this->getCallData("name_silence_counter") ?? 0;
        $yonSilenceNameCounter = (int) $this->getCallData("yon_silence_name_counter") ?? 0;
        $yonNameCounter = (int) $this->getCallData("yon_name_counter") ?? 0;

        if ($nameSilenceCounter >= $tries) {
            return true;
        }

        if ($yonSilenceNameCounter >= $tries) {
            return true;
        }
        if ($yonNameCounter >= $tries) {
            return true;
        }

        return false;
    }

    public function endCall(): VoiceResponse
    {
        $this->response->hangup();
        return $this->response();
    }
    public function createUuid(): void
    {
        $this->uuid = uuid_create();
    }


    //METODOS DATOS
    public function getCallData(string $key): ?string
    {
        $dataAsJson = Cache::get("twilio_call_$this->uuid", '{}');
        $data = json_decode($dataAsJson, true);

        return $data[$key] ?? null;
    }

    public function externSaveCallData($key, $value)
    {
        $this->saveCallData($key, $value);
    }

    private function saveCallData(string $key, string $value): void
    {
        $dataAsJson = Cache::get("twilio_call_$this->uuid", '{}');
        $data = json_decode(json: $dataAsJson, associative: true);
        Log::info("Guardamos $key --- $value");

        $data[$key] = $value;
        $dataAsJson = json_encode(value: $data);

        Cache::put("twilio_call_$this->uuid", $dataAsJson);
    }
}
