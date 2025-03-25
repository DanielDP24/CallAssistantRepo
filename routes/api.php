<?php

use App\Http\Controllers\CompanyController;
use App\Http\Controllers\EmailController;
use App\Http\Controllers\HubSpotController;
use App\Http\Controllers\Incoming;
use App\Http\Controllers\NameController;
use Illuminate\Support\Facades\Route;

Route::get('/ManageCall', [Incoming::class, 'askName']); //RECIBIMOS LA LLAMADA
//Preguntamos por el nombre

Route::post('/ProcessName', [NameController::class, 'checkName']);//Preguntamos si o no

Route::post('/ProcessName/CheckNameYON', [NameController::class, 'confirmName']);//comprobamos el si o no

//Preguntamos email

Route::post('/ProcessEmail/AskEmail', [EmailController::class, 'askEmail']);//pedimos el email

Route::post('/ProcessEmail', [EmailController::class, 'checkEmail']); //Preguntamos si o no.
Route::post('/ProcessEmail/CheckEmailYON', [EmailController::class, 'ConfirmEmail']);//comprobamos el sí o no

//Preguntamos por la empresa
Route::post('/ProcessCompany', [CompanyController::class, 'processCompany']); //preguntamos si o no.
Route::post('/ProcessCompany/CheckCompanyYON', [CompanyController::class, 'CheckCompanyYON']); //Comprobamos el sí o no
Route::post('/ProcessCompany/AskCompany', [EmailController::class, 'AskCompany']);//comprobamos el sí o no

//POST Info solicitada
Route::post('/endCall', [HubSpotController::class, 'endCall']);//Creamos el ticket mientras le decimos sus datos y le avisamos que se le va a redirigir
Route::post('/redirectCall', [HubSpotController::class, 'RedirectCall']);// Redirigimos la llamada a un número de Aircall

Route::post('/CreateJson', [HubSpotController::class, 'CreateJson']);// Redirigimos la llamada a un número de Aircall
