<?php

use App\Http\Controllers\CompanyController;
use App\Http\Controllers\EmailController;
use App\Http\Controllers\Incoming;
use App\Http\Controllers\NameController;
use Illuminate\Support\Facades\Route;

Route::get('/ManageCall', [Incoming::class, 'recieveCall']); //RECIBIMOS LA LLAMADA
//Preguntamos por el nombre

Route::post('/ProcessName', [NameController::class, 'processName']);//Preguntamos si o no
Route::post('/ProcessName/CheckNameYON', [NameController::class, 'CheckNameYON']);//comprobamos el si o no
//Preguntamos email

Route::post('/ProcessEmail', [EmailController::class, 'processEmail']);


Route::post('/ProcessCompany', [CompanyController::class, 'processCompany']);
