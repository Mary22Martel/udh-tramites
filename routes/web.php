<?php

use Illuminate\Support\Facades\Route;

// Toda la interfaz es una aplicación React (resources/js). Laravel solo entrega la página base.
Route::view('/{any?}', 'app')->where('any', '^(?!api|storage|build|up).*$');
