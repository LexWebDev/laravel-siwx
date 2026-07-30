<?php

use Illuminate\Support\Facades\Route;
use LexWebDev\Siwx\Http\Controllers\SiwxNonceController;

Route::get('nonce', SiwxNonceController::class)->name('siwx.nonce');
