<?php

use App\Http\Controllers\DocumentController;
use App\Http\Controllers\GoogleDriveController;
use App\Http\Controllers\ReportExportController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/admin');

Route::middleware('auth')->group(function () {
    Route::get('/google/connect', [GoogleDriveController::class, 'connect'])->name('google.connect');
    Route::get('/google/callback', [GoogleDriveController::class, 'callback'])->name('google.callback');
    Route::get('/documents/{document}/download', [DocumentController::class, 'download'])->name('documents.download');
    Route::get('/reports/export/{format}', ReportExportController::class)->whereIn('format', ['csv', 'pdf', 'transactions'])->name('reports.export');
    Route::get('/status.json', [DocumentController::class, 'status'])->name('status.json');
});
