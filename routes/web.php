<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function(){
    return redirect()->route('admin.dashboard');
})->name('home');



Route::group(['prefix' => 'admin', 'as' => 'admin.', 'middleware' => ['auth', 'verified']], function () {
    Route::livewire('dashboard', 'dashboard')->name('dashboard');
});

require __DIR__ . '/settings.php';
