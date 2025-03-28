<?php

use App\Http\Controllers\CompanyController;
use App\Http\Controllers\EmailController;
use App\Http\Controllers\HubSpotController;
use App\Http\Controllers\Incoming;
use App\Http\Controllers\NameController;
use Illuminate\Support\Facades\Route;

//Preguntamos el nombre.
Route::get('/ManageCall', [Incoming::class, 'askName']); //RECIBIMOS LA LLAMADA
Route::post('/ProcessName', [NameController::class, 'checkName']);//Preguntamos si o no
Route::post('/ProcessName/CheckNameYON', [NameController::class, 'confirmName']);//comprobamos el si o no

//Preguntamos email.
Route::post('/ProcessEmail/AskEmail', [EmailController::class, 'askEmail']);//pedimos el email
Route::post('/ProcessEmail', [EmailController::class, 'checkEmail']); //Preguntamos si o no.
Route::post('/ProcessEmail/CheckEmailYON', [EmailController::class, 'confirmEmail']);//comprobamos el sí o no

//Preguntamos por la empresa.
Route::post('/ProcessCompany/AskCompany', [CompanyController::class, 'askCompany']);//comprobamos el sí o no
Route::post('/ProcessCompany', [CompanyController::class, 'checkCompany']); //preguntamos si o no.
Route::post('/ProcessCompany/CheckCompanyYON', [CompanyController::class, 'confirmCompany']); //Comprobamos el sí o no

//Guardamos los datos.
Route::post('/EndCall', [HubSpotController::class, 'endCall']);//Creamos el ticket mientras le decimos sus datos y le avisamos que se le va a redirigir
Route::post('/redirectCall', [HubSpotController::class, 'RedirectCall']);// Redirigimos la llamada a un número de Aircall

Route::post('/CreateJson', [HubSpotController::class, 'CreateJson']);// Redirigimos la llamada a un número de Aircall
