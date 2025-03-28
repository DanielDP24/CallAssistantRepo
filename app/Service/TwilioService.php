<?php

namespace App\Service;

use EchoLabs\Prism\Enums\Provider;
use EchoLabs\Prism\Prism;
use EchoLabs\Prism\Schema\ObjectSchema;
use EchoLabs\Prism\Schema\StringSchema;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Twilio\TwiML\Voice\Redirect;
use Twilio\TwiML\VoiceResponse;

class TwilioService
{
    private readonly Voiceresponse $response;
    private string $uuid;

    public function __construct()
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
            $this->say(speech: 'Hola, has llamado a Air zo ne. Le solicitaremos unos datos antes de redirigirle con uno de nuestros agentes.');
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
            Log::info('no hay temp name');

            $this->saveCallData('temp_name', $name);
        } else {
            Log::info('entra en lleno');
            $name = $this->getCallData('temp_name') ?? '';
            // Recupera el valor guardado
        }

        $silenceNameCounter = (int) $this->getCallData("name_silence_counter") ?? 0;
        $isSilence = $this->isSilence($name);

        //SI ES SILENCIO 3   VECES, CUELGA
        if ($isSilence && $silenceNameCounter >= 2) {
            $this->response->hangup();
            return;
        }

        //PRIMERA VEZ SILENCIO, PASA Y SUMA CONTADOR
        if ($isSilence) {
            //CREA CONTADOR Y SUMA
            $nameSilenceCounter = (int) $this->getCallData("name_silence_counter") ?? 0;
            $nameSilenceCounter++;
            $this->saveCallData("name_silence_counter", $nameSilenceCounter);

            //REDIRIJE CON UUID YA QUE ES LA MISMA LLAMADA
            $this->say('No escuché su respuesta. Intentémoslo de nuevo. Número de intentos ' . $nameSilenceCounter);
            Log::info('reintentando');
            $this->response->redirect(url('/api/ManageCall') . '?_method=GET' . "&uuid=$this->uuid");
            return;
        }
        //SI NO HAY SILENCIO, LLEVA EL NOMBRE A CONFIRMAR CON YON PREGUNTA.
        $this->saveCallData("name_silence_counter", 0);
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
                Log::info('\n --- NO es correcto 3 veces ---- \n');
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
        Log::info('\n --- SI, es correcto ---- \n');

        // Guardar el nombre definitivo y continuar
        $name = $this->getCallData('temp_name');
        $this->saveCallData('name', $name);
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
            Log::info('no hay temp name');

            $this->saveCallData('temp_email', $email);
        } else {
            Log::info('entra en lleno');
            $email = $this->getCallData('temp_email') ?? '';
            // Recupera el valor guardado
        }
        $silenceEmailCounter = (int) $this->getCallData("email_silence_counter") ?? 0;
        $isSilence = $this->isSilence($email);

        //SI ES SILENCIO 2 VECES, PREGUNTA COMPANY
        if ($isSilence && $silenceEmailCounter >= 1) {
            $this->response->redirect(url("/api/ProcessCompany/AskCompany?uuid=$this->uuid"));
            return;
        }

        //PRIMERA VEZ SILENCIO, PASA Y SUMA CONTADOR
        if ($isSilence) {
            //CREA CONTADOR Y SUMA
            $emailSilenceCounter = (int) $this->getCallData("email_silence_counter") ?? 0;
            $emailSilenceCounter++;
            $this->saveCallData("email_silence_counter", $emailSilenceCounter);

            $this->say('No escuché su respuesta. Intentémoslo de nuevo. Número de intentos ' . $emailSilenceCounter);
            Log::info('reintentando EMAIL');
            //REDIRIJE A PREGUNTAR EL EMAIL DE NUEVO
            $this->askEmail();
            return;
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

        //LÓGICA DE CORECCIÓN DE EMAIL AI
        $prompt = <<<EOT
        You are an expert proofreader for email transcriptions. Your task is to correct and normalize email addresses from speech-to-text snippets.
        
        ### Correction Rules:
        1. **Preserve Order & No Invention** → Correct errors but do not change word order or invent missing parts.
        2. **Fix Common Transcription Mistakes**:
           - "arroba" → "@"
           - "roba" → "@"
           - "punto com" → ".com"
        3. **Domain Corrections**:
           - Replace "airsoft" or "control" (after "@") with "airzonecontrol".
        4. **Validation**:
           - A valid email must contain "@" or "arroba".
           - If missing, return `"Esto no es un email válido"` for both fields.
        
        ### TTS Conversion Rules:
        - Convert the corrected email into a text-to-speech (TTS) friendly format:
          - Replace "@" with "arroba".
          - Replace "." with "punto".
          - Separate words clearly for better pronunciation.
        
        ### Output Format:
        - Return a structured JSON response with two fields:
          1. "email": The correctly formatted email address.
          2. "readable_email": The TTS-friendly version of the corrected email.
        - If no @ or arroba provided, valid email can't be formed, return "Esto no es un email válido" for both fields.
        
        The provided email snippet: "$email"
        EOT;

        $response = Prism::structured()
            ->using(Provider::OpenAI, 'gpt-4o-mini')   //esto para cambiar el tipo de open AI.
            ->withSchema($schema)
            ->withPrompt($prompt)
            ->generate()->structured;


        $email = $response['email'] ?? "Email Vacio IA";
        $emailLeer = $response['readable_email'] ?? "Email Vacio IA";

        //GUARDA TEMPORALMENTE LOS EMAILS, TANTO EL QUE SE GUARDA COMO EL QUE SE LEE
        $this->saveCallData('temp_email', $email);
        $this->saveCallData('temp_email_leer', $emailLeer);

        Log::info('Llega email y debería preguntar confirmación', ['uuid' => $this->uuid, "email" => $email]);

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
        Log::info('es silencio en _>_>_>_>_>_>_X' . $isSilence);
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
        Log::info('\n --- SI, es correcto el EMAIL ---- \n', ["email en confirmación" => $email]);
        $this->saveCallData('email', $email);
        $this->response->redirect(url("/api/ProcessCompany/AskCompany?uuid=$this->uuid"));
    }

    //COMPANY
    public function askCompany(): void
    {

        $name = $this->getCallData("name") ?? '';
        Log::info("Pedimos el nombre de su empresa. junto al nombre de la persona $name");

        $this->gather(
            action: url("/api/ProcessCompany?uuid=$this->uuid"),
            speech: "Ahora por favor $name, facilítenos el nombre de su empresa"
        );
        Log::info('Pedimos el nombre de su empresa.');
    }
    public function checkCompany(string $company)
    {
        //GUARDAMOS company TEMPORALMENTE
        if ($this->getCallData('temp_company') == '') {
            Log::info('no hay temp company');

            $this->saveCallData('temp_company', $company);
        } else {
            Log::info('entra en lleno');
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
            Log::info('reintentando company');
            $this->response->redirect(url("/ProcessCompany/AskCompany?uuid=$this->uuid"));
            return;
        }
        //SI NO HAY SILENCIO, LLEVA company A CONFIRMAR CON YON PREGUNTA.
        $this->saveCallData("company_silence_counter", 0);
        $this->gather(
            action: url("/api/ProcessCompany/CheckCompanyYON?uuid=$this->uuid"),
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

        // Si se rechaza el company dos veces, pasamos a hubspot y redirigimos
        if (!$this->isAConfirm($yon)) {
            if ($yonCompanyCounter >= 2) {
                Log::info('\n --- NO es correcto 3 veces ---- \n');
                $this->response->redirect(url("/api/EndCall?uuid=$this->uuid"));
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
        $company = $this->getCallData('temp_company');
        $this->saveCallData('company', $company);

        //Cogemos los datos y los en enviamos todos por url hacia hubspot
        $name = $this->getCallData('name');
        $email = $this->getCallData('email');
        $company = $this->getCallData('company');

        Log::info('datos finales', ["\n name" => $name, "\n email" => $email, "\n company" => $company]);
        $this->response->redirect(url("/api/EndCall?uuid=$this->uuid&name=$name&email=$email&company=$company"));
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
            'timeout' => '6',
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

        $prompt = <<<EOT
        You are a professional conversational assistant. 
        Your task is to listen to and analyze user responses that may include phrases such as 
        "si", "no", "está bien", "es correcto", "está mal", "quiero repetir", or "no es así".
         Using your advanced natural language understanding and contextual analysis, deduce whether the 
         response is positive (affirmative) or negative. If the response is positive, simply output "si". 
         If it is negative or non-affirmative, output "no" any other case just answer no. 
         No other possibility than the answers "si" or "no". Ensure your decision is based on all the 
         nuances present in the user's input.
         
         USER INPUT: $yon
        EOT;
        $response = Prism::text()
            ->using(Provider::OpenAI, 'gpt-4o-mini')
            ->withPrompt($prompt)
            ->generate()->text;


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
