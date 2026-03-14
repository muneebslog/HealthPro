<?php

use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::view('dashboard', 'dashboard')->name('dashboard');
    Route::middleware('admin')->group(function () {
        Route::livewire('/cruds', 'pages::cruds')->name('cruds')->defer();
        Route::livewire('tinker', 'pages::tinker')->name('tinker')->defer();
        Route::livewire('admin/printed', 'pages::printed')->name('admin.printed')->defer();
        Route::livewire('admin/print-test', 'pages::print-test')->name('admin.print-test')->defer();
        Route::livewire('admin/accounts', 'pages::accounts')->name('admin.accounts')->defer();
    });
    Route::middleware('doctor')->prefix('doctor')->name('doctor.')->group(function () {
        Route::livewire('dashboard', 'pages::doctor.dashboard')->name('dashboard')->defer();
        Route::livewire('profile', 'pages::doctor.profile')->name('profile')->defer();
        Route::livewire('invoices', 'pages::doctor.invoices')->name('invoices')->defer();
        Route::livewire('payouts', 'pages::doctor.payouts')->name('payouts')->defer();
        Route::livewire('appointments', 'pages::doctor.appointments')->name('appointments')->defer();
        Route::livewire('procedures', 'pages::doctor.procedures')->name('procedures')->defer();
    });
    Route::livewire('reception/walkin', 'pages::reception.walkin')->name('reception.walkin')->defer();
    Route::livewire('reception/procedure', 'pages::reception.procedure')->name('reception.procedure')->defer();
    Route::livewire('reception/procedures', 'pages::reception.procedures')->name('reception.procedures')->defer();
    Route::livewire('reception/appointment', 'pages::reception.appointment')->name('reception.appointment')->defer();
    Route::livewire('reception/invoices', 'pages::reception.invoices')->name('reception.invoices')->defer();
    Route::livewire('reception/queues', 'pages::reception.queues')->name('reception.queues')->defer();
    Route::livewire('reception/shift', 'pages::reception.shift')->name('reception.shift')->defer();
    Route::livewire('reception/payout', 'pages::reception.payout')->name('reception.payout')->defer();
    Route::livewire('reception/mr-lookup', 'pages::reception.mr-lookup')->name('reception.mr-lookup')->defer();
});

Route::get('/printer-debug-port', function () {
    try {
        $connector = new \Mike42\Escpos\PrintConnectors\FilePrintConnector('\\\\.\\COM8');
        $printer = new \Mike42\Escpos\Printer($connector);
        $printer->text("TEST\n");
        $printer->cut();
        $printer->close();

        return 'Printed via port OK';
    } catch (\Throwable $e) {
        return 'Port failed: '.$e->getMessage();
    }
})->middleware('auth');
require __DIR__.'/settings.php';
