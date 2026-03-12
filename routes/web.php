<?php

use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::view('dashboard', 'dashboard')->name('dashboard');
    Route::middleware('admin')->group(function () {
        Route::livewire('/cruds', 'pages::cruds')->name('cruds');
        Route::livewire('tinker', 'pages::tinker')->name('tinker');
        Route::livewire('admin/printed', 'pages::printed')->name('admin.printed');
    });
    Route::livewire('reception/walkin', 'pages::reception.walkin')->name('reception.walkin');
    Route::livewire('reception/appointment', 'pages::reception.appointment')->name('reception.appointment');
    Route::livewire('reception/invoices', 'pages::reception.invoices')->name('reception.invoices');
    Route::livewire('reception/queues', 'pages::reception.queues')->name('reception.queues');
    Route::livewire('reception/shift', 'pages::reception.shift')->name('reception.shift');
    Route::livewire('reception/payout', 'pages::reception.payout')->name('reception.payout');
});

require __DIR__.'/settings.php';
