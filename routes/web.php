<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\HomeController;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/upload', [HomeController::class, 'upload']);
Route::get('/upload-worker', [HomeController::class, 'uploadWorker']);
Route::post('/upload', [HomeController::class, 'uploadFile'])->name('upload.file');
Route::get('/upload-optimized', [HomeController::class, 'uploadOptimized']);
Route::post('/upload/init', [HomeController::class, 'initUpload']);
Route::post('/upload/chunk', [HomeController::class, 'uploadChunk']);
Route::post('/upload/finalize', [HomeController::class, 'finalizeUpload']);

Route::get('/videos', [HomeController::class, 'videos']);
