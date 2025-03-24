<?php

namespace App\Service;

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

        Log::info("tries:$tries");
        Log::info("uuid:$this->uuid");

        Log::info("nameSilenceCounter:$nameSilenceCounter");

        if ($nameSilenceCounter >= $tries) {
            return true;
        }
        Log::info('intento');

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
        $nameSilenceCounter = (int) $this->getCallData("name_silence_counter") ?? 0;
        if ($nameSilenceCounter == 0) {
            $this->say(speech: 'Hola, has llamado a Air zo ne. Le solicitaremos unos datos antes de redirigirle con uno de nuestros agentes. ');
        }

        $this->gather(
            action: url("/api/ProcessName"),
            hints: 'Juan, María, José, Jose, Carmen, Antonio, Ana, Manuel, Laura, Francisco, Lucia, David, Paula, Javier, Elena, Miguel, Sara, Carlos, Patricia, Pedro, Andrea, Luis, Marta, Sergio, Raúl, Rosa, Guillermo, Nuria, Alberto, Irene, Jorge, Beatriz, Ricardo, Cristina, Víctor, Silvia, Alejandro, Mario, Isabel, Diego, Gloria, Fernando, Claudia, Roberto, Teresa, Andrés, Mercedes, Julio, Sonia, Ramón, Inmaculada, Marcos, Concepción, Ángel, Estrella, Mariano, Lourdes, Jaime, Susana, Octavio, Esperanza, Adrián, Benito, Rebeca, Enrique, Soledad, Santiago, Amparo, Armando, Carolina, Eloy, Dolores, Damián, Fátima, Gonzalo, Jacinta, Hilario, Irma, Mauricio, Josefina, Ernesto, Liliana, Federico, Martina, Blanca, Oscar, Clara, Ismael, Juana, Hugo, Pilar, Valentín',
            speech: 'Por favor, dígame su nombre'
        );

        Log::info('pedimos nombre.');
    }

    public function saveName(string $name): void   
    {
        $this->saveCallData("temp_name", $name);

        if ($this->hasToRepeatAskName($name)) {
            $nameSilenceCounter = (int) $this->getCallData("name_silence_counter") ?? 0;
            $nameSilenceCounter++;
            $this->saveCallData("name_silence_counter", $nameSilenceCounter);

            $this->say('No escuché su respuesta. Intentémoslo de nuevo. Número de intentos ' . $nameSilenceCounter);
            Log::info('reintentando');
            $this->response->redirect(url('/api/ManageCall') . '?_method=GET' . "&uuid=$this->uuid");
            return;
        }

        $this->gather(
            action: url('/api/ProcessName/CheckNameYON'),
            speech: 'El nombre recibido es ' . $name . ' confirme si es o no correcto'
        );
    }

    public function response(): VoiceResponse   
    {
        return $this->response;
    }

    private function gather(string $action, string $hints = '', ?string $speech = null): void
    {
        if (empty($this->uuid)) {
            throw new \Exception("Uuid is not set");
        }

        Log::info('uuid es : ' . $this->uuid);
        Log::info('action es : ' . $action);

        $gather = $this->response->gather([
            'input'         => 'speech',
            'timeout'       => '6',
            'action'        => $action . "?uuid=$this->uuid",
            'method'        => 'POST',
            'language'      => 'es-ES',
            'speechModel'   => 'googlev2_short',
            'speechTimeout' => '1',
            'actionOnEmptyResult' => true,
            'hints'   => $hints,
        ]);

        if ($speech !== null) {
            $gather->say($speech, $this->voiceConfig());
        }
    }

    private function say(string $speech) {
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

    private function hasToRepeatAskName(string $name): bool
    {
        $isNameValid = !($name == 'null' || empty($name));

        Log::info($isNameValid ? 'true' : 'false');
        if (!$isNameValid) {
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
