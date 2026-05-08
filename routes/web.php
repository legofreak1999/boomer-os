<?php

use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::livewire('dashboard', 'pages::dashboard')->name('dashboard');

    Route::livewire('expenses', 'pages::expenses.index')->name('expenses.index');
    Route::livewire('expenses/dashboard', 'pages::expenses.dashboard')->name('expenses.dashboard');
    Route::livewire('expenses/create', 'pages::expenses.create')->name('expenses.create');
    Route::livewire('expenses/{receipt}/edit', 'pages::expenses.edit')->name('expenses.edit');

    Route::livewire('categories', 'pages::categories.index')->name('categories.index');
    Route::livewire('stores', 'pages::stores.index')->name('stores.index');

    Route::livewire('app-settings', 'pages::settings-global.index')->name('app-settings.index');
    Route::livewire('app-settings/notifications', 'pages::settings-global.notifications')->name('app-settings.notifications');
});

require __DIR__.'/settings.php';
