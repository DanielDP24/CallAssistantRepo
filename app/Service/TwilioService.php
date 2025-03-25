<?php

namespace App\Service;

use EchoLabs\Prism\Enums\Provider;
use EchoLabs\Prism\Prism;
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

    public function askName(): void
    {
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

        Log::info('pedimos nombre.');
    }


    public function askEmail(): void
    {
        $name = $this->getCallData("name") ?? '';

        $this->gather(
            action: url("/api/ProcessEmail"),
            speech: "Por favor, $name, Ahora diganos su email"
        );

        Log::info('Pedimos el email.');
    }


    public function checkName(string $name): void
    {
        $this->saveCallData('temp_name', $name);
        $silenceNameCounter = (int) $this->getCallData("name_silence_counter") ?? 0;
        $isSilence = $this->isSilence($name);

        if ($isSilence && $silenceNameCounter >= 2) {
            $this->response->hangup();
            return;
        }

        if ($isSilence) {
            $nameSilenceCounter = (int) $this->getCallData("name_silence_counter") ?? 0;
            $nameSilenceCounter++;
            $this->saveCallData("name_silence_counter", $nameSilenceCounter);

            $this->say('No escuché su respuesta. Intentémoslo de nuevo. Número de intentos ' . $nameSilenceCounter);
            Log::info('reintentando');
            $this->response->redirect(url('/api/ManageCall') . '?_method=GET' . "&uuid=$this->uuid");
            return;
        }
        $this->saveCallData("name_silence_counter", 0);
        $this->gather(
            action: url('/api/ProcessName/CheckNameYON'),
            speech: 'El nombre recibido es ' . $name . ' confirme si es o no correcto'
        );
    }

    public function checkEmail(string $email): void
    {
        $this->saveCallData('temp_email', $email);
        $silenceEmailCounter = (int) $this->getCallData("email_silence_counter") ?? 0;
        $isSilence = $this->isSilence($email);

        if ($isSilence && $silenceEmailCounter >= 2) {
            //PASAR A PEDIR EL NOMBRE DE LA COMPAÑÍA
            //$this->response->hangup();
            return;
        }

        if ($isSilence) {
            $emailSilenceCounter = (int) $this->getCallData("email_silence_counter") ?? 0;
            $emailSilenceCounter++;
            $this->saveCallData("email_silence_counter", $emailSilenceCounter);

            $this->say('No escuché su respuesta. Intentémoslo de nuevo. Número de intentos ' . $emailSilenceCounter);
            Log::info('reintentando');
            $this->response->redirect(url("/api/ManageCall?uuid=$this->uuid"));
            return;
        }
        $this->saveCallData("email_silence_counter", 0);
        $this->gather(
            action: url('/api/ProcessEmail/CheckEmailYON'),
            speech: 'El email recibido es ' . $email . ' confirme si es o no correcto'
        );
    }

    public function confirmName(string $yon): void
    {
        $yonSilenceNameCounter = (int) $this->getCallData("yon_silence_name_counter") ?? 0;

        if ($this->isSilence($yon) && $yonSilenceNameCounter < 2) {
            $yonSilenceNameCounter++;
            $this->saveCallData("yon_silence_name_counter", $yonSilenceNameCounter);

            $this->gather(
                action: url('/api/ProcessName'),
                hints: "si, no",
                speech: 'Intentémoslo de nuevo. Número de intentos ' . $yonSilenceNameCounter
            );
            Log::info('\n --- reintentando confirm name ---- \n');

            return;
        }
        $this->saveCallData("yon_silence_name_counter", 0);

        if (!$this->isAConfirm($yon)) {
            $yonNameCounter = (int) $this->getCallData("yon_name_counter") ?? 0;
            $yonNameCounter++;
            $this->saveCallData("yon_name_counter", $yonNameCounter);
            $this->response->redirect(url('/api/ManageCall') . '?_method=GET' . "&uuid=$this->uuid");
            return;
        }
        $this->saveCallData("yon_name_counter", 0);


        $name = $this->getCallData('temp_name');
        $this->saveCallData('name', $name);
        $this->response->redirect(url('/api/AskEmail') . "?uuid=$this->uuid");
        return;
    }

    public function response(): VoiceResponse
    {
        return $this->response;
    }

    public function laravelResponse()
    {
        return response($this->response->__toString(), 200)->header('Content-Type', 'text/xml');;
    }

    private function gather(string $action, string $hints = '', ?string $speech = null): void
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

    private function say(string $speech)
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

    private function isSilence(string $param): bool
    {
        $isParamValid = !($param == 'null' || empty($param));

        Log::info($isParamValid ? 'true' : 'false');
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


    private function getCallData(string $key): ?string
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
