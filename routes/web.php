<?php

use Illuminate\Support\Facades\Route;

// Redirigir la raíz del sitio al panel de administración
Route::get('/', function () {
    return redirect('/admin/login');
});

