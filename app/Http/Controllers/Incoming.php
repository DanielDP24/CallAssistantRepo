<?php

namespace App\Http\Controllers;

use App\Service\TwilioService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class Incoming extends Controller
{
    public function __construct(private TwilioService $twilio)
    {

    }
    public function askName(Request $request)
    {
        Log::info('pedimos nombre.');

        $uuid = $request->input("uuid", '');

        if (empty($uuid)) {
            $this->twilio->createUuid();
        }

        $this->twilio->askName();

        return $this->twilio->laravelResponse();
    }

    public function giveName(): string
    {
        $filePath = storage_path('app/public/Nombres.txt');

        if (!file_exists($filePath)) {
            return "Archivo no encontrado";
        }

        $content = file_get_contents($filePath);
        $names = array_map('trim', explode(',', $content));

        if (empty($names)) {
            return "No hay nombres en el archivo";
        }

        return $names[array_rand($names)];
    }
    
}