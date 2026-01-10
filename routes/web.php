<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\SystemAdminController;

// Redirigir la raíz del sitio al panel de administración
Route::get('/', function () {
    return redirect('/admin/login');
});

// Rutas de administración del sistema (protegidas por autenticación)
Route::middleware(['web', 'auth'])->prefix('system')->group(function () {
    Route::get('/', [SystemAdminController::class, 'index'])->name('system.admin');
    Route::post('/clear-cache', [SystemAdminController::class, 'clearCache'])->name('system.clear-cache');
    Route::post('/run-migrations', [SystemAdminController::class, 'runMigrations'])->name('system.run-migrations');
});

