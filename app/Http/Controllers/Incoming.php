<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Twilio\TwiML\VoiceResponse;

class Incoming extends Controller
{
    public function recieveCall()
    {
        $response = new VoiceResponse();

        $response->say('Hola, has llamado a Airzone. Le solicitaremos unos datos antes de redirigirle con uno de nuestros agentes; ', [ 'language' => 'es-ES',
        'voice' => 'Polly.Lucia-Neural',
        'rate' => '1.1']);

        $gather = $response->gather([
            'input'         => 'speech',
            'timeout'       => '5',
            'action'        => url('/api/ProcessName'),
            'method'        => 'POST',        
            'language'      => 'es-ES',
            'speechModel'   => 'googlev2_short',
            'hints'   => 'Juan, María, José, Jose, Carmen, Antonio, Ana, Manuel, Laura, Francisco, Lucia, David, Paula, Javier, Elena, Miguel, Sara, Carlos, Patricia, Pedro, Andrea, Luis, Marta, Sergio, Raúl, Rosa, Guillermo, Nuria, Alberto, Irene, Jorge, Beatriz, Ricardo, Cristina, Víctor, Silvia, Alejandro, Mario, Isabel, Diego, Gloria, Fernando, Claudia, Roberto, Teresa, Andrés, Mercedes, Julio, Sonia, Ramón, Inmaculada, Marcos, Concepción, Ángel, Estrella, Mariano, Lourdes, Jaime, Susana, Octavio, Esperanza, Adrián, Benito, Rebeca, Enrique, Soledad, Santiago, Amparo, Armando, Carolina, Eloy, Dolores, Damián, Fátima, Gonzalo, Jacinta, Hilario, Irma, Mauricio, Josefina, Ernesto, Liliana, Federico, Martina, Blanca, Oscar, Clara, Ismael, Juana, Hugo, Pilar, Valentín',
            'actionOnEmptyResult' => true
        ]);

        $gather->say('Por favor, dígame su nombre', [
            'language' => 'es-ES',
            'voice' => 'Polly.Lucia-Neural',
            'rate' => '1.1'
        ]);
        return $response;
    }

    public function RedirectCall(Request $request)
    {
        $response = new VoiceResponse();
    
        Log::info('Redirigiendo la llamada de ' . $request->input('Caller') . ' a +34615581444');
    
        $response->say('Estamos transfiriendo su llamada...',[ 'language' => 'es-ES',
        'voice' => 'Polly.Lucia-Neural',
        'rate' => '1.1']);
    
        // Transferimos la llamada en curso al número de destino
        $dial = $response->dial('answerOnBridge="true"');
        $dial->number('+34951125359');
    
        return response($response)->header('Content-Type', 'text/xml');
    }
    
}
