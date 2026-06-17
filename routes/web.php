<?php

use Illuminate\Support\Facades\Route;

use Livewire\Volt\Volt;

Volt::route('/', 'pages.landing')->name('home');

Route::view('dashboard', 'dashboard')
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::view('profile', 'profile')
    ->middleware(['auth'])
    ->name('profile');

require __DIR__.'/auth.php';
