<?php

use App\Http\Controllers\ProfileController;

use App\Http\Controllers\Admin\DashboardController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\FilterKebijakanMenluController;
use App\Http\Controllers\FilterKebijakanBukanMenluController;
use App\Http\Controllers\FilterMOUMenluController;
use App\Http\Controllers\HomeControllerAwal;

// Home route
Route::get('/', [HomeControllerAwal::class, 'index'])->name('home');



// Halaman Nota Kesepahaman (MoU) dan PKS
Route::get('/nkmdp', [FilterMOUMenluController::class, 'index'])->name('nkmdp');

// Halaman Kebijakan TIK by Non Kemlu
Route::get('/ktbnk', [FilterKebijakanBukanMenluController::class, 'index'])->name('ktbnk');


/// Halaman Kebijakan TIK by Kemlu
Route::get('/ktbk', [FilterKebijakanMenluController::class, 'index'])->name('ktbk');



 



// Route group untuk admin panel (memerlukan autentikasi)
Route::middleware(['auth', 'verified'])->prefix('admin')->name('admin.')->group(function () {
    // Dashboard admin
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    });


// Rute untuk profil pengguna
Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

// Route untuk menampilkan dashboard setelah login
Route::get('/dashboard', function () {
    return redirect()->route('admin.dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

// Rute autentikasi
require __DIR__.'/auth.php';