<?php

namespace App\Http\Controllers;

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

    public function __construct()
    {
        $this->client = Factory::createWithAccessToken(config('services.hubspot.apikey'));
        $this->filePath = '/home/ddominguez/projects/Results.txt';
    }
    public function endCall(Request $request)
    {
        $response = new VoiceResponse();
        $firstname = $request->input('name', '');
        $email = $request->input('email', '');
        $company = $request->input('company', '');
        $caller = $request->input('Caller', '');
        file_put_contents(
            $this->filePath,
            "Datos recibidos CallAssistant\n" .
            " - " . $firstname . "\n" .
            " - " . $email . "\n" .
            " - " . $company . "\n" .
            "LLAMADA TERMINADA\n",
            FILE_APPEND
        );
        
        $this->CreateTicket($email, $firstname, $caller, $company);

        $response->say('Ahora procederemos a almacenar los datos proporcionados, y le pondremos en contacto con uno de nuestros agentes.', [
            'language' => 'es-ES',
            'voice' => 'Polly.Lucia-Neural',
            'rate' => '1.1'
        ]);
        $response->redirect(url('/api/redirectCall'));

        return $response;
    }

    public function CreateTicket($email, $firstname, $caller, $company)
    {

        $now = new DateTime();
        try {
            $ticketInput = new TicketModel\SimplePublicObjectInput();
            $ticketInput->setProperties([
                'hs_pipeline' => '0',
                'hs_pipeline_stage' => '1',
                'createdate' => $now,
                'subject' => 'Llamada de Twilio',
                'content' => 'Empresa: ' . $company . ', Nombre: ' . $firstname . ' , Email: ' . $email . ' , Teléfono: ' . $caller,
            ]);
            $quepasa = $this->client->crm()->tickets()->basicApi()->create($ticketInput);
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

    public function RedirectCall(Request $request)
    {
        $response = new VoiceResponse();

        $caller = $request->input('Caller');
        Log::info('Redirigiendo la llamada de ' . $caller . ' a +34 951 12 53 59');

        $response->say('Estamos transfiriendo su llamada...', [
            'language' => 'es-ES',
            'voice' => 'Polly.Lucia-Neural',
            'rate' => '1.1'
        ]);

        // Transferimos la llamada en curso al número de destino
        $dial = $response->dial('answerOnBridge="true"');
        $dial->number('+34951125359');

        return response($response)->header('Content-Type', 'text/xml');
    }
}
