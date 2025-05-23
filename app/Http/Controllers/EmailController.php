<?php

namespace App\Http\Controllers;

use App\Service\TwilioService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Twilio\TwiML\VoiceResponse;

class EmailController extends Controller
{

    public string $filePath;

    public function __construct(private TwilioService $twilio)
    {
        $this->filePath = '/home/ddominguez/projects/Results.txt';
    }

    public function askEmail(): VoiceResponse
    {

        $this->twilio->askEmail();

        return $this->twilio->response();
    }

    public function checkEmail(Request $request)
    {
        $email = $request->input('SpeechResult') ?? '';
        Log::info('Email antes de la IA' . $email);

        $this->twilio->checkEmail($email);

        return $this->twilio->response();
    }


    public function confirmEmail(Request $request): VoiceResponse
    {
        $yon = $request->input('SpeechResult') ?? '';        

        $this->twilio->confirmEmail($yon);

        return $this->twilio->response();
    }
}
