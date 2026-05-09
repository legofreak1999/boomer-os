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

    Route::livewire('chores', 'pages::chores.index')->name('chores.index');
    Route::livewire('chores/manage', 'pages::chores.chores')->name('chores.manage');

    Route::livewire('tasks', 'pages::tasks.index')->name('tasks.index');

    Route::livewire('hikes', 'pages::hikes.index')->name('hikes.index');
    Route::livewire('hikes/create', 'pages::hikes.create')->name('hikes.create');
    Route::livewire('hikes/tags', 'pages::hikes.tags')->name('hikes.tags');
    Route::livewire('hikes/{hikeLocation}/edit', 'pages::hikes.edit')->name('hikes.edit');
    Route::livewire('hikes/{hikeLocation}/trails/create', 'pages::hikes.trail-editor')->name('hikes.trails.create');
    Route::livewire('hikes/{hikeLocation}/trails/{hikeTrail}/edit', 'pages::hikes.trail-editor')->name('hikes.trails.edit');

    Route::livewire('demo/hike-map', 'pages::demo.hike-map')->name('demo.hike-map');

    Route::livewire('app-settings', 'pages::settings-global.index')->name('app-settings.index');
    Route::livewire('app-settings/home-location', 'pages::settings-global.home-location')->name('app-settings.home-location');
    Route::livewire('app-settings/notifications', 'pages::settings-global.notifications')->name('app-settings.notifications');
});

require __DIR__.'/settings.php';
