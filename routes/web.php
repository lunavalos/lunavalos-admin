<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::redirect('/', '/login');

Route::get('/dashboard', [\App\Http\Controllers\DashboardController::class, 'index'])
    ->middleware(['auth', 'verified'])
    ->name('dashboard');
Route::get('/client/emails', [\App\Http\Controllers\DashboardController::class, 'clientEmails'])
    ->middleware(['auth', 'verified'])
    ->name('client.emails');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::resource('roles', \App\Http\Controllers\RoleController::class);
    Route::resource('users', \App\Http\Controllers\UserController::class);
    Route::resource('services', \App\Http\Controllers\ServiceController::class);
    Route::resource('quotes', \App\Http\Controllers\QuoteController::class);
    Route::post('quotes/{quote}/status', [\App\Http\Controllers\QuoteController::class, 'updateStatus'])->name('quotes.status');
    Route::post('quotes/{quote}/generate-contract', [\App\Http\Controllers\ContractController::class, 'generate'])->name('quotes.generateContract');
    Route::get('clients/import', [\App\Http\Controllers\ClientController::class, 'importView'])->name('clients.import');
    Route::post('clients/import-bulk', [\App\Http\Controllers\ClientController::class, 'importBulk'])->name('clients.importBulk');
    Route::resource('clients', \App\Http\Controllers\ClientController::class);
    Route::post('clients/{client}/renew', [\App\Http\Controllers\ClientController::class, 'renew'])->name('clients.renew');
    Route::resource('agencies', \App\Http\Controllers\AgencyController::class)->except(['create', 'show', 'edit']);



    // Settings
    Route::get('settings', [\App\Http\Controllers\SettingController::class, 'edit'])->name('settings.edit');
    Route::post('settings', [\App\Http\Controllers\SettingController::class, 'update'])->name('settings.update');

    // Recursos Humanos (RRHH)
    Route::get('employees/settings', [\App\Http\Controllers\EmployeeController::class, 'settings'])->name('employees.settings');
    Route::post('employees/settings', [\App\Http\Controllers\EmployeeController::class, 'updateSettings'])->name('employees.updateSettings');
    Route::resource('employees', \App\Http\Controllers\EmployeeController::class);
    Route::get('employees/{employee}/contract', [\App\Http\Controllers\EmployeeController::class, 'printContract'])->name('employees.printContract');
    Route::post('employees/{employee}/documents', [\App\Http\Controllers\EmployeeController::class, 'storeDocument'])->name('employees.storeDocument');
    Route::delete('employees/documents/{document}', [\App\Http\Controllers\EmployeeController::class, 'destroyDocument'])->name('employees.destroyDocument');
    Route::post('employees/{employee}/salaries', [\App\Http\Controllers\EmployeeController::class, 'updateSalary'])->name('employees.updateSalary');
    Route::post('employees/{employee}/payroll', [\App\Http\Controllers\EmployeeController::class, 'storePayroll'])->name('employees.storePayroll');
    Route::get('payroll/{receipt}/print', [\App\Http\Controllers\EmployeeController::class, 'printPayroll'])->name('payroll.print');
    // Tickets
    Route::resource('tickets', \App\Http\Controllers\TicketController::class);
    Route::post('tickets/{ticket}/message', [\App\Http\Controllers\TicketController::class, 'addMessage'])->name('tickets.addMessage');
    Route::post('tickets/{ticket}/status', [\App\Http\Controllers\TicketController::class, 'updateStatus'])->name('tickets.updateStatus');
    Route::post('tickets/{ticket}/assign', [\App\Http\Controllers\TicketController::class, 'assign'])->name('tickets.assign');
    Route::post('notifications/mark-as-read', [\App\Http\Controllers\DashboardController::class, 'markNotificationsAsRead'])->name('notifications.markAsRead');

    // Signature Generator
    Route::resource('signature-templates', \App\Http\Controllers\SignatureTemplateController::class);
    Route::get('/client/signatures', [\App\Http\Controllers\SignatureGeneratorController::class, 'index'])->name('client.signatures');
});

require __DIR__ . '/auth.php';

// Public Contract Routes - Test Webhook Deploy
Route::get('contratodeservicio/{token}', [\App\Http\Controllers\ContractController::class, 'show'])->name('contracts.show');
Route::post('contratodeservicio/{token}/sign', [\App\Http\Controllers\ContractController::class, 'sign'])->name('contracts.sign');
