<?php

namespace App\Http\Controllers;

use DateTime;
use HubSpot\Factory;
use Illuminate\Http\Request;
use \HubSpot\Client\Crm\Tickets\Model as TicketModel;
use Twilio\TwiML\VoiceResponse;

class HubSpotController extends Controller
{
    private $client;

    public function __construct()
    {
        $this->client = Factory::createWithAccessToken(config('services.hubspot.apikey'));
    }
    public function endCall (Request $request) {

        //$response = new VoiceResponse();
        $firstname = $request->input('name', '');
        $email = $request->input('email', '');
        $content = "Este ticket se ha creado mediante la llamada Twilio";
        $phone = $request->input('Caller');
        $this->CreateTicket($email, $firstname,$phone, $content);
        // $response->say('Ahora vamos a proceder a almacenar los datos que nos has proporcionado, y le pondremos en contacto con uno de nuestros agentes.', [
        //     'language' => 'es-ES',
        //     'voice'    => 'Polly.Conchita',
        //     'rate'     => '1.3'
        // ]);
    }

    public function CreateTicket($email, $firstname,$phone, $content)
    {
        $now = new DateTime();
        $nowFormatted = $now->format('Y-m-d H:i:s'); 
        try {
            $ticketInput = new TicketModel\SimplePublicObjectInput();
            $ticketInput->setProperties([
                'hs_pipeline' => '0',
                'hs_pipeline_stage' => '1',
                'createdate' => $now, 
                'subject' => 'Llamada ',
                'content' => $content,
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

   
}
