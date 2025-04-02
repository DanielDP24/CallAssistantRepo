<?php

namespace App\Http\Controllers;

use App\Service\TwilioService;
use DateTime;
use HubSpot\Factory;
use Illuminate\Http\Request;
use \HubSpot\Client\Crm\Tickets\Model as TicketModel;
use Illuminate\Support\Facades\Log;
use Twilio\TwiML\VoiceResponse;

class HubSpotController extends Controller
{
    private $client;
    public string $filePath;

    public function __construct(private TwilioService $twilio)
    {
        $this->client = Factory::createWithAccessToken(config('services.hubspot.apikey'));
    }
    public function endCall(Request $request)
    {
        Log::info('llega a end call');
        $name = $request->input('name') ?? 'Vacio, preguntar';
        $email = $request->input('email') ?? 'Vacio, preguntar';
        $company = $request->input('company') ?? 'Vacio, preguntar';
        $caller = $request->input('Caller', '');
        Log::info('datos en END COMPANY', ["\n name" => $name, "\n email" => $email, "\n company" => $company]);

        $this->CreateTicket($email, $name, $caller, $company);
        return $this->RedirectCall($caller);
    }

    public function CreateTicket($email, $name, $caller, $company)
    {
        $name = str_replace('_', ' ', $name);
        $company = str_replace('_', ' ', $company);

        $now = new DateTime();
        try {
            $ticketInput = new TicketModel\SimplePublicObjectInput();
            $ticketInput->setProperties([
                'hs_pipeline' => '0',
                'hs_pipeline_stage' => '1',
                'createdate' => $now,
                'subject' => 'Llamada de Twilio',
                'content' => 'Empresa: ' . $company . ', Nombre: ' . $name . ' , Email: ' . $email . ' , Teléfono: ' . $caller,
            ]);
            $this->client->crm()->tickets()->basicApi()->create($ticketInput);
        } catch (\Exception $e) {
            $errorData = [
                'message' => $e->getMessage(),
                'code' => $e->getCode(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ];

            return response()->json([
                'error' => 'No se pudo crear el ticket:',
                'details' => $errorData
            ], 500);
        }
    }
//Me falta que no dice esto. Flujo si funciona.
    public function RedirectCall($caller)
    {
        $response = new VoiceResponse();
        $response->say( 'Ahora procederemos a almacenar los datos proporcionados, y le pondremos en contacto con uno de nuestros agentes.',[
            'language' => 'es-ES',
            'voice' => 'Polly.Lucia-Neural',
            'rate' => '1'
        ]);

        Log::info('Redirigiendo la llamada de ' . $caller . ' a +34 951 12 53 59');

        $response->say('Estamos transfiriendo su llamada...',[
            'language' => 'es-ES',
            'voice' => 'Polly.Lucia-Neural',
            'rate' => '1'
        ]);

        // Transferimos la llamada en curso al número de destino
        $dial = $response->dial();
        $dial->number('+34951125359');
        Log::info("La response en xmls es esta    $response");
        return response($response)->header('Content-Type', 'text/xml');
    }
    
}
