<?php

namespace App\Http\Controllers;

use App\Service\TwilioService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Twilio\TwiML\VoiceResponse;

class CompanyController extends Controller
{

    public function __construct(private TwilioService $twilio) {}

    public function askCompany()
    {
        $this->twilio->askCompany();

        return $this->twilio->response();
    }
    public function checkCompany(Request $request)
    {
        $company = $request->input('SpeechResult') ?? '';

        $this->twilio->checkCompany($company);

        return $this->twilio->response();
    }
    public function confirmCompany(Request $request)
    {
        //COJE LA RESPUESTA DEL YON
        $yon = $request->input('SpeechResult') ?? '';

        $this->twilio->confirmCompany($yon);

        return $this->twilio->response();
    }
}




/**
 * 
 * 
 * 
 * 
 *  $processedCompany = preg_replace(
 * 
 *          [
                '/ punto /i',
                '/ Air zone /i',
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
                'Airzone',
                'Airzone'
            ],
            $company
        );

 */
