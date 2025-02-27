<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Twilio\TwiML\VoiceResponse;

class Incoming extends Controller
{
    public function recieveCall()
    {
        $response = new VoiceResponse();

        $response->say('Hola, has llamado a Eirzon.', ['language' => 'es-ES']);

        $gather = $response->gather([
            'input'         => 'speech',
            'timeout'       => 10,
            'action'        => url('/api/ProcessName'),
            'method'        => 'POST',        
            'language'      => 'es-ES',
            'speechModel'   => 'googlev2_long',
            'bargeIn'       => true,
            'speechTimeout' => 'auto',
            'hints'   => 'Juan, María, José, Jose, Carmen, Antonio, Ana, Manuel, Laura, Francisco, Lucia, David, Paula, Javier, Elena, Miguel, Sara, Carlos, Patricia, Pedro, Andrea, Luis, Marta, Sergio, Raúl, Rosa, Guillermo, Nuria, Alberto, Irene, Jorge, Beatriz, Ricardo, Cristina, Víctor, Silvia, Alejandro, Mario, Isabel, Diego, Gloria, Fernando, Claudia, Roberto, Teresa, Andrés, Mercedes, Julio, Sonia, Ramón, Inmaculada, Marcos, Concepción, Ángel, Estrella, Mariano, Lourdes, Jaime, Susana, Octavio, Esperanza, Adrián, Benito, Rebeca, Enrique, Soledad, Santiago, Amparo, Armando, Carolina, Eloy, Dolores, Damián, Fátima, Gonzalo, Jacinta, Hilario, Irma, Mauricio, Josefina, Ernesto, Liliana, Federico, Martina, Blanca, Oscar, Clara, Ismael, Juana, Hugo, Pilar, Valentín'
        ]);

        $gather->say('Por favor, dígame su nombre', [
            'language' => 'es-ES',
            'voice' => 'Polly.Conchita',
            'rate' => '1.2'
        ]);

        return $response;//esto contiene pepe
    }
}
