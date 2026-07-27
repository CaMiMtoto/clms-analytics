<?php

use App\Http\Middleware\SSOAuthenticate;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('admin.dashboard');
})->name('home');

Route::group(['prefix' => 'admin', 'as' => 'admin.', 'middleware' => [SSOAuthenticate::class]], function () {
    Route::livewire('dashboard', 'dashboard')->name('dashboard');
});

Route::get('/sso/logout', function (Illuminate\Http\Request $request) {
    Auth::logout();
    $request->session()->invalidate();
    $request->session()->regenerateToken();

    return redirect('/login');
});

require __DIR__ . '/settings.php';
