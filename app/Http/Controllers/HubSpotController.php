<?php

namespace App\Http\Controllers;

use App\Models\DataGiven;
use App\Models\DataRecieved;
use App\Service\TwilioService;
use DateTime;
use HubSpot\Factory;
use Illuminate\Http\Request;
use \HubSpot\Client\Crm\Tickets\Model as TicketModel;
use Illuminate\Support\Facades\Log;
use Twilio\TwiML\VoiceResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class HubSpotController extends Controller
{
    private $client;
    public string $filePath;


    public function __construct(private TwilioService $twilio, private DatabaseController $DatabaseController)
    {
        $this->client = Factory::createWithAccessToken(config('services.hubspot.apikey'));
    }
    public function endCall(Request $request)
    {

        $name = urldecode($request->input('name') ?? 'Vacio, preguntar');
        $email = urldecode($request->input('email') ?? 'Vacio, preguntar');
        $company = urldecode($request->input('company') ?? 'Vacio, preguntar');
        $caller = request()->input('Caller');

        $this->DatabaseController->insertField('callerNum', $caller);

        // --- Opcional: acciones adicionales ---
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
    public function RedirectCall($caller)
    {
        $response = new VoiceResponse();
        $response->say('Ahora procederemos a almacenar los datos proporcionados, y le pondremos en contacto con uno de nuestros agentes.', [
            'language' => 'es-ES',
            'voice' => 'Polly.Lucia-Neural',
            'rate' => '1'
        ]);

        
        Log::info('Redirigiendo la llamada de ' . $caller . ' a +34 951 12 53 59');

        $response->say('Estamos transfiriendo su llamada...', [
            'language' => 'es-ES',
            'voice' => 'Polly.Lucia-Neural',
            'rate' => '1'
        ]);

        // Transferimos la llamada en curso al número de destino +34951125359
        //$dial = $response->dial();
        //$dial->number('+34951798775');


        Http::post('http://54.247.29.41:8001/api/repeateCall');
        return response($response)->header('Content-Type', 'text/xml');
    }
}
