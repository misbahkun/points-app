<?php

use App\Http\Controllers\TransactionController;
use Illuminate\Support\Facades\Route;

Route::post('/transactions', [TransactionController::class, 'store'])->middleware('hmac.verify');
Route::get('/transactions', [TransactionController::class, 'index']);
Route::post('/transactions/seed', [TransactionController::class, 'seed']);