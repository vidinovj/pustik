<?php

use App\Http\Controllers\Admin\LegalDocumentController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\FilterKebijakanEksternalController;
use App\Http\Controllers\FilterKebijakanInternalController;
use App\Http\Controllers\HomeControllerAwal;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PusdatinController;
use Illuminate\Support\Facades\Route;

// Home route
Route::get('/', [HomeControllerAwal::class, 'index'])->name('home');

// Halaman Nota Kesepahaman (MoU) dan PKS
Route::get('/pusdatin', [PusdatinController::class, 'index'])->name('pusdatin');

// Halaman Kebijakan TIK Eksternal
Route::get('/kebijakan-eksternal', [FilterKebijakanEksternalController::class, 'index'])->name('kebijakan-eksternal');

// Halaman Kebijakan TIK Internal
Route::get('/kebijakan-internal', [FilterKebijakanInternalController::class, 'index'])->name('kebijakan-internal');

Route::prefix('documents')->name('documents.')->group(function () {
    Route::get('/{document}', [DocumentController::class, 'show'])->name('show');
    Route::get('/{document}/download', [DocumentController::class, 'download'])->name('download');
    Route::get('/{document}/content', [DocumentController::class, 'content'])->name('content');
    Route::get('/{document}/debug', [DocumentController::class, 'debug'])->name('debug');
});

// Route group untuk admin panel (memerlukan autentikasi)
Route::middleware(['auth', 'verified'])->group(function () {
    // CRUD Legal Documents
    Route::prefix('admin')->name('admin.')->group(function () {
        Route::resource('legal-documents', LegalDocumentController::class);
    });
});

// Rute untuk profil pengguna
Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

// Rute untuk dokumen
Route::prefix('documents')->name('documents.')->group(function () {
    // Main document view
    Route::get('/{document}', [DocumentController::class, 'show'])->name('show');

    // Download - now prioritizes pdf_url over source_url
    Route::get('/{document}/download', [DocumentController::class, 'download'])->name('download');

    // AJAX content for modal (includes pdf_url)
    Route::get('/{document}/content', [DocumentController::class, 'content'])->name('content');

    // PDF proxy route (if CORS issues occur)
    Route::get('/{document}/pdf-proxy', [DocumentController::class, 'pdfProxy'])->name('pdf-proxy');

    // Debug route (remove in production)
    Route::get('/{document}/debug', [DocumentController::class, 'debug'])->name('debug');

    // File serving route for uploaded documents
    Route::get('/{document}/file', [DocumentController::class, 'serveFile'])->name('serve-file');

});

// Rute autentikasi
require __DIR__.'/auth.php';
