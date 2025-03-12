<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Twilio\TwiML\VoiceResponse;

class CompanyController extends Controller
{
    public function processCompany(Request $request)
    {
        $response = new VoiceResponse();
        $company  = $request->input('SpeechResult');
        Log::info('El usuario company.', ['company' => $company]);
        $name  = $request->query('name', '');
        $email = $request->query('email', '');

        if (empty($company)) {
            Log::info('El usuario no respondió a company. Repetimos la pregunta.');
            $response->say('No le hemos escuchado.', [
                'language' => 'es-ES',
                'voice' => 'Polly.Lucia-Neural',
                'rate' => '1.1'
            ]);
            $response->redirect(url('/api/ProcessCompany/AskCompany') . '?name=' . urlencode($name) . '&email=' . urlencode($email));
            return response($response)->header('Content-Type', 'text/xml');
        }
        $processedCompany = preg_replace(
            [
                '/ punto /i',
                '/ air /i',
                '/ nzone /i',
                '/ erzone /i',
                '/ air son /i',
                '/ airzon /i',
                '/ arison /i',
                '/ erzon /i',
                '/ ayrzone /i',
                '/ air zone /i',
                '/ airz one /i',
                '/ aison /i',
                '/ aizona /i',
                '/ en zona /i',
                '/ airzoné /i',
                '/ airsoft /i',
                '/ airozone /i',         
                '/ airezone /i',         
                '/ airzun /i',           
                '/ eirzone /i',          
                '/ ersone /i',           
                '/ arizone /i',          
                '/ aireson /i',          
                '/ airso(n|ne)/i',       
            ],
            [
                '.',
                'Airzone',
                'Airzone',
                'Airzone',
                'Airzone',
                'Airzone',
                'Airzone',
                'Airzone',
                'Airzone',
                'Airzone',
                'Airzone',
                'Airzone',
                'Airzone',
                'Airzone',
                'Airzone',
                'Airzone',
                'Airzone',
                'Airzone',
                'Airzone',
                'Airzone',
                'Airzone',
                'Airzone',
                'Airzone',
                'Airzone'
            ],
            $company
        );

        Log::info('Datos recibidos en processCompany:', ['company' => $processedCompany]);

        $gather = $response->gather([
            'input'         => 'speech',
            'timeout'       => 10,
            'action'        => url('/api/ProcessCompany/CheckCompanyYON') . '?name=' . urlencode($name) . '&email=' . urlencode($email) . '&company=' . urlencode($processedCompany),
            'method'        => 'POST',
            'language'      => 'es-ES',
            'speechModel'   => 'googlev2_short',
            'speechTimeout' => '1',
            'actionOnEmptyResult' => true
        ]);
        $gather->say(
            'Gracias por facilitarnos el nombre de su empresa, ' . $processedCompany . ',  confirme si es o no correcto',
            [
                'language' => 'es-ES',
                'voice' => 'Polly.Lucia-Neural',
                'rate' => '1.1'
            ]
        );

        return response($response->__toString(), 200)->header('Content-Type', 'text/xml');
    }

    public function CheckCompanyYON(Request $request)
    {
        $response = new VoiceResponse();
        $emailController = new EmailController();

        $YON = strtolower($request->input('SpeechResult'));
        $YON =  $emailController->checkAnswerYONAI($YON);

        Log::info('Datos recibidos en CheckCompanyYON:', ['YON' => $YON]);
        $name  = $request->query('name', '');
        $email = $request->query('email', '');
        $company = $request->query('company', '');
        $company2 = $request->input('company');

                

        if (empty($YON)) {//TODO:
            Log::info('El usuario no respondió al sí o no. Repetimos la pregunta.');
            $response->say('No escuché su respuesta. Intentémoslo de nuevo.', [
                'language' => 'es-ES',
                'voice' => 'Polly.Lucia-Neural',
                'rate' => '1.1'
            ]);
            $response->redirect(url('/api/ProcessCompany/AskCompany') . '?name=' . urlencode($name) . '&email=' . urlencode($email)) . '&company=' . urlencode($company);
            return response($response)->header('Content-Type', 'text/xml');
        }

        if ($YON == 'si' || $YON == 'sí') { 
            $response->say('Respondiste sí.', [
                'language' => 'es-ES',
                'voice' => 'Polly.Lucia-Neural',
                'rate' => '1.1'
            ]);
         $response->redirect(url('/api/endCall/') . '?name=' . urlencode($name) . '&email=' . urlencode($email)) . '&company=' . urlencode($company);
        } elseif ($YON == 'no') {
            $response->say('Respondiste no. Intentémoslo de nuevo.', [
                'language' => 'es-ES',
                'voice' => 'Polly.Lucia-Neural',
                'rate' => '1.1'
            ]);
            $response->redirect(url('/api/ProcessCompany/AskCompany') . '?name=' . urlencode($name) . '&email=' . urlencode($email));
        } else {
            $response->say('Por favor, responda únicamente con sí o no.', [
                'language' => 'es-ES',
                'voice' => 'Polly.Lucia-Neural',
                'rate' => '1.1'
            ]);
            $response->redirect(url('/api/ProcessCompany/AskCompany') . '?name=' . urlencode($name) . '&email=' . urlencode($email));
        }

        return response($response->__toString(), 200)->header('Content-Type', 'text/xml');
    }
}
