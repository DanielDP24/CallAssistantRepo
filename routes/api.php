<?php

use App\Http\Controllers\CompanyController;
use App\Http\Controllers\EmailController;
use App\Http\Controllers\Incoming;
use App\Http\Controllers\NameController;
use Illuminate\Support\Facades\Route;

Route::get('/ManageCall', [Incoming::class, 'recieveCall']);
Route::post('/ProcessName', [NameController::class, 'processName']);
Route::post('/ProcessEmail', [EmailController::class, 'processEmail']);
Route::post('/ProcessCompany', [CompanyController::class, 'processCompany']);
