<?php

use App\Events\RemoteStyleUpdate;
use App\Http\Controllers\BattleshipController;
use App\Http\Controllers\GameController;
use App\Http\Controllers\RemoteStyleController;
use Illuminate\Support\Facades\Route;

// Menú principal: avisa en qué juego el lugar de anfitrión está libre u ocupado.
$menu = function () {
    return view('menu', [
        'juegoOcupado' => \App\Services\HostSlot::isTaken('juego'),
        'batallaOcupado' => \App\Services\HostSlot::isTaken('batalla'),
        'miJuego' => session('role_juego'),
        'miBatalla' => session('role_batalla'),
    ]);
};

Route::get('/', $menu);
Route::get('/emisor', $menu)->name('emisor');
Route::post('/emitir', [RemoteStyleController::class,'emitir'])->name('emitir');
Route::get('/receptor', $menu)->name('receptor');

// Trivia Relámpago ⚡
Route::get('/juego', [GameController::class, 'index'])->name('juego');
Route::get('/juego-emisor', [GameController::class, 'emisor'])->name('juego.emisor');
Route::get('/juego-receptor', [GameController::class, 'receptor'])->name('juego.receptor');
Route::post('/juego-emitir', [GameController::class, 'emitir'])->name('juego.emitir');

// Batalla Naval 🚢
Route::get('/batalla', [BattleshipController::class, 'index'])->name('batalla');
Route::get('/batalla-emisor', [BattleshipController::class, 'emisor'])->name('batalla.emisor');
Route::get('/batalla-receptor', [BattleshipController::class, 'receptor'])->name('batalla.receptor');
Route::post('/batalla-emitir', [BattleshipController::class, 'emitir'])->name('batalla.emitir');
Route::get('/batalla-estado', [BattleshipController::class, 'state'])->name('batalla.estado');
// `sendBeacon` no puede enviar el header de CSRF, asi que esta ruta lo omite.
Route::post('/batalla-liberar', [BattleshipController::class, 'releaseHost'])
    ->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class])
    ->name('batalla.liberar');

// Prueba automatizada del broadcasting (dev): dispara el evento real.
Route::get('/broadcast-test', function () {
    broadcast(new RemoteStyleUpdate('Hola Reverb', 'color:red'));
    return response()->json(['ok' => true, 'channel' => 'style-channel']);
});
