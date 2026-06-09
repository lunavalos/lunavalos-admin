<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::redirect('/', '/login');

// ⚠️  TEMPORAL — borrar después de usar
if (app()->environment('local')) {
    Route::get('/run-report-permissions-seeder', function () {
        app(\Database\Seeders\ReportPermissionsSeeder::class)->run();
        return response()->json(['ok' => true, 'message' => 'ReportPermissionsSeeder ejecutado correctamente.']);
    });

    Route::get('/debug-pdf/{report}', function (\App\Models\Report $report) {
        try {
            $report->load(['client', 'creator']);
            $tickets  = collect($report->tickets_snapshot ?? [])->values()->toArray();
            $settings = \App\Models\Setting::pluck('value', 'key')->toArray();

            $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('reports.pdf', [
                'report'   => $report,
                'tickets'  => $tickets,
                'settings' => $settings,
            ])->setPaper('letter', 'portrait');

            return $pdf->download('debug-report.pdf');
        } catch (\Throwable $e) {
            return response()->json([
                'error'   => $e->getMessage(),
                'file'    => $e->getFile(),
                'line'    => $e->getLine(),
                'class'   => get_class($e),
            ], 500);
        }
    });
}


Route::get('/two-factor-challenge', [\App\Http\Controllers\TwoFactorController::class, 'showChallenge'])
    ->name('two-factor.challenge');
Route::post('/two-factor-challenge', [\App\Http\Controllers\TwoFactorController::class, 'storeChallenge'])
    ->name('two-factor.challenge.store');


Route::get('/dashboard', [\App\Http\Controllers\DashboardController::class, 'index'])
    ->middleware(['auth', 'verified'])
    ->name('dashboard');
Route::get('/client/emails', [\App\Http\Controllers\DashboardController::class, 'clientEmails'])
    ->middleware(['auth', 'verified'])
    ->name('client.emails');
Route::get('/client/briefing', [\App\Http\Controllers\DashboardController::class, 'clientBriefing'])
    ->middleware(['auth', 'verified'])
    ->name('client.briefing');
Route::post('/client/briefing', [\App\Http\Controllers\DashboardController::class, 'updateBriefing'])
    ->middleware(['auth', 'verified'])
    ->name('client.briefing.update');
Route::get('/client/mail-config', [\App\Http\Controllers\DashboardController::class, 'clientMailConfig'])
    ->middleware(['auth', 'verified'])
    ->name('client.mail-config');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::patch('/profile/vault', [ProfileController::class, 'updateVault'])->name('profile.vault.update');
    Route::post('/profile/verify-password', [ProfileController::class, 'verifyPassword'])->name('profile.vault.verify');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // 2FA Routes
    Route::post('/two-factor/enable', [\App\Http\Controllers\TwoFactorController::class, 'enable'])->name('two-factor.enable');
    Route::post('/two-factor/confirm', [\App\Http\Controllers\TwoFactorController::class, 'confirm'])->name('two-factor.confirm');
    Route::delete('/two-factor/disable', [\App\Http\Controllers\TwoFactorController::class, 'disable'])->name('two-factor.disable');
    Route::get('/two-factor/recovery-codes', [\App\Http\Controllers\TwoFactorController::class, 'recoveryCodes'])->name('two-factor.recovery-codes');


    Route::resource('roles', \App\Http\Controllers\RoleController::class);
    Route::resource('users', \App\Http\Controllers\UserController::class);
    // Service addons (subrecurso visible en /services/addons-* y dentro del catálogo)
    Route::resource('service-addons', \App\Http\Controllers\ServiceAddonController::class)
        ->except(['show']);
    Route::resource('services', \App\Http\Controllers\ServiceController::class);

    // Quote wizard (debe declararse antes del resource para evitar colisión con {quote}).
    Route::get('quotes/wizard',  [\App\Http\Controllers\QuoteWizardController::class, 'create'])->name('quotes.wizard.create');
    Route::post('quotes/wizard', [\App\Http\Controllers\QuoteWizardController::class, 'store'])->name('quotes.wizard.store');
    Route::get('quotes/{quote}/wizard/edit', [\App\Http\Controllers\QuoteWizardController::class, 'edit'])->name('quotes.wizard.edit');
    Route::put('quotes/{quote}/wizard',      [\App\Http\Controllers\QuoteWizardController::class, 'update'])->name('quotes.wizard.update');
    Route::post('quotes/{quote}/transition', [\App\Http\Controllers\QuoteWizardController::class, 'transition'])->name('quotes.transition');

    // Pantalla de gestión post-creación (Inertia) + cierre de venta a contrato.
    Route::get('quotes/{quote}/manage',   [\App\Http\Controllers\QuoteManagementController::class, 'show'])->name('quotes.manage');
    Route::post('quotes/{quote}/convert', [\App\Http\Controllers\QuoteManagementController::class, 'convert'])->name('quotes.convert');

    // Pagos / cobranza
    Route::get('payments',                  [\App\Http\Controllers\PaymentController::class, 'index'])->name('payments.index');
    Route::get('payments/contracts/{contract}', [\App\Http\Controllers\PaymentController::class, 'showContract'])->name('payments.contract.show');
    Route::post('payments',                 [\App\Http\Controllers\PaymentController::class, 'store'])->name('payments.store');
    Route::post('payments/{payment}/settle',[\App\Http\Controllers\PaymentController::class, 'settle'])->name('payments.settle');
    Route::post('payments/{payment}/cancel',[\App\Http\Controllers\PaymentController::class, 'cancel'])->name('payments.cancel');
    Route::get('payments/{payment}/receipt',[\App\Http\Controllers\PaymentController::class, 'receipt'])->name('payments.receipt');

    // Renovaciones de contratos
    Route::get('contracts',                                [\App\Http\Controllers\ContractController::class, 'index'])->name('contracts.index');
    Route::get('contracts/renewals',                       [\App\Http\Controllers\ContractRenewalController::class, 'index'])->name('contracts.renewals.index');
    Route::get('contracts/{contract}/admin',               [\App\Http\Controllers\ContractController::class, 'adminShow'])->name('contracts.admin.show');
    Route::get('contracts/{contract}/edit',                [\App\Http\Controllers\ContractController::class, 'edit'])->name('contracts.edit');
    Route::put('contracts/{contract}',                     [\App\Http\Controllers\ContractController::class, 'update'])->name('contracts.update');
    Route::post('contracts/renewals/check',                [\App\Http\Controllers\ContractRenewalController::class, 'runCheck'])->name('contracts.renewals.check');
    Route::post('contracts/{contract}/renew',              [\App\Http\Controllers\ContractRenewalController::class, 'start'])->name('contracts.renewals.start');
    Route::post('contracts/{contract}/renew/decline',      [\App\Http\Controllers\ContractRenewalController::class, 'decline'])->name('contracts.renewals.decline');

    // Facturación (Facturama / CFDI 4.0)
    Route::get('invoices',                                       [\App\Http\Controllers\InvoiceController::class, 'index'])->name('invoices.index');
    Route::post('payments/{payment}/invoice',                    [\App\Http\Controllers\InvoiceController::class, 'issueForPayment'])->name('invoices.issueForPayment');
    Route::post('invoices/{invoice}/cancel',                     [\App\Http\Controllers\InvoiceController::class, 'cancel'])->name('invoices.cancel');
    Route::get('invoices/{invoice}/download/{type}',             [\App\Http\Controllers\InvoiceController::class, 'download'])->whereIn('type', ['pdf', 'xml'])->name('invoices.download');

    Route::resource('quotes', \App\Http\Controllers\QuoteController::class);
    Route::post('quotes/{quote}/status', [\App\Http\Controllers\QuoteController::class, 'updateStatus'])->name('quotes.status');
    Route::post('quotes/{quote}/generate-contract', [\App\Http\Controllers\ContractController::class, 'generate'])->name('quotes.generateContract');
    Route::get('clients/import', [\App\Http\Controllers\ClientController::class, 'importView'])->name('clients.import');
    Route::post('clients/import-bulk', [\App\Http\Controllers\ClientController::class, 'importBulk'])->name('clients.importBulk');
    Route::resource('clients', \App\Http\Controllers\ClientController::class);
    Route::post('clients/{client}/renew', [\App\Http\Controllers\ClientController::class, 'renew'])->name('clients.renew');
    Route::post('clients/{client}/toggle-historical', [\App\Http\Controllers\ClientController::class, 'toggleHistorical'])->name('clients.toggleHistorical');
    
    // Client Costs
    Route::post('clients/{client}/costs', [\App\Http\Controllers\ClientCostController::class, 'store'])->name('clients.costs.store');
    Route::put('clients/{client}/costs/{cost}', [\App\Http\Controllers\ClientCostController::class, 'update'])->name('clients.costs.update');
    Route::delete('clients/{client}/costs/{cost}', [\App\Http\Controllers\ClientCostController::class, 'destroy'])->name('clients.costs.destroy');

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
    // Clientes Recurrentes (planes mensuales con créditos / entregables)
    Route::get('recurring', [\App\Http\Controllers\RecurringDashboardController::class, 'index'])->name('recurring.index');
    Route::get('recurring/clients/{client}', [\App\Http\Controllers\RecurringClientController::class, 'show'])->name('recurring.clients.show');
    Route::post('recurring/clients/{client}/open-cycle', [\App\Http\Controllers\RecurringClientController::class, 'openCycle'])->name('recurring.clients.openCycle');
    Route::post('recurring/clients/{client}/deliverables', [\App\Http\Controllers\RecurringClientController::class, 'createDeliverable'])->name('recurring.clients.deliverables.store');
    Route::post('recurring/clients/{client}/sync-analytics', [\App\Http\Controllers\RecurringClientController::class, 'syncAnalytics'])->name('recurring.clients.syncAnalytics');

    // Configuración de entregables recurrentes dentro de un contrato
    Route::post('contracts/{contract}/services',                       [\App\Http\Controllers\ContractServiceController::class, 'store'])->name('contracts.services.store');
    Route::put('contracts/{contract}/services/{service}',              [\App\Http\Controllers\ContractServiceController::class, 'update'])->name('contracts.services.update');
    Route::delete('contracts/{contract}/services/{service}',           [\App\Http\Controllers\ContractServiceController::class, 'destroy'])->name('contracts.services.destroy');
    Route::post('contracts/{contract}/open-cycle',                     [\App\Http\Controllers\ContractServiceController::class, 'openCycle'])->name('contracts.openCycle');

    // Social Publishing (Fase 3)
    Route::get('social',                                               [\App\Http\Controllers\SocialController::class, 'index'])->name('social.index');
    Route::get('social/clients/{client}',                              [\App\Http\Controllers\SocialController::class, 'show'])->name('social.clients.show');
    Route::get('social/clients/{client}/posts/create',                 [\App\Http\Controllers\SocialController::class, 'createPost'])->name('social.posts.create');
    Route::post('social/clients/{client}/posts',                       [\App\Http\Controllers\SocialController::class, 'storePost'])->name('social.posts.store');
    Route::get('social/clients/{client}/posts/{post}/edit',            [\App\Http\Controllers\SocialController::class, 'editPost'])->name('social.posts.edit');
    Route::post('social/clients/{client}/posts/{post}',                [\App\Http\Controllers\SocialController::class, 'updatePost'])->name('social.posts.update');
    Route::delete('social/clients/{client}/posts/{post}',              [\App\Http\Controllers\SocialController::class, 'destroyPost'])->name('social.posts.destroy');
    Route::post('social/clients/{client}/posts/{post}/publish',        [\App\Http\Controllers\SocialController::class, 'publishNow'])->name('social.posts.publishNow');

    // OAuth Social — conectar/desconectar cuentas
    Route::get('social/oauth/{provider}/{client}/redirect',            [\App\Http\Controllers\SocialAuthController::class, 'redirect'])->name('social.oauth.redirect');
    Route::get('social/oauth/{provider}/callback',                     [\App\Http\Controllers\SocialAuthController::class, 'callback'])->name('social.oauth.callback');
    Route::delete('social/accounts/{account}',                         [\App\Http\Controllers\SocialAuthController::class, 'disconnect'])->name('social.accounts.disconnect');

    // Tickets
    Route::get('tickets/trash', [\App\Http\Controllers\TicketController::class, 'trash'])->name('tickets.trash');
    Route::post('tickets/{id}/restore', [\App\Http\Controllers\TicketController::class, 'restore'])->name('tickets.restore');
    Route::delete('tickets/empty-trash', [\App\Http\Controllers\TicketController::class, 'emptyTrash'])->name('tickets.emptyTrash');
    Route::get('tickets/archive', [\App\Http\Controllers\TicketController::class, 'archiveList'])->name('tickets.archive');
    Route::post('tickets/{ticket}/toggle-archive', [\App\Http\Controllers\TicketController::class, 'toggleArchive'])->name('tickets.toggleArchive');
    Route::post('tickets/{ticket}/toggle-client-visibility', [\App\Http\Controllers\TicketController::class, 'toggleClientVisibility'])->name('tickets.toggleClientVisibility');
    Route::post('tickets/{ticket}/public-link', [\App\Http\Controllers\TicketController::class, 'generatePublicLink'])->name('tickets.generatePublicLink');
    Route::delete('tickets/{ticket}/public-link', [\App\Http\Controllers\TicketController::class, 'revokePublicLink'])->name('tickets.revokePublicLink');
    Route::resource('tickets', \App\Http\Controllers\TicketController::class);
    Route::post('tickets/{ticket}/message', [\App\Http\Controllers\TicketController::class, 'addMessage'])->name('tickets.addMessage');
    Route::post('tickets/{ticket}/status', [\App\Http\Controllers\TicketController::class, 'updateStatus'])->name('tickets.updateStatus');
    Route::post('tickets/{ticket}/assign', [\App\Http\Controllers\TicketController::class, 'assign'])->name('tickets.assign');
    Route::post('tickets/{ticket}/service', [\App\Http\Controllers\TicketController::class, 'updateService'])->name('tickets.updateService');
    Route::post('tickets/{ticket}/cycle', [\App\Http\Controllers\TicketController::class, 'updateCycle'])->name('tickets.updateCycle');
    Route::post('tickets/{ticket}/start-work', [\App\Http\Controllers\TicketController::class, 'startWork'])->name('tickets.startWork');
    Route::post('tickets/send-team-report', [\App\Http\Controllers\TicketController::class, 'sendTeamReport'])->name('tickets.sendTeamReport');
    Route::put('tickets/messages/{message}', [\App\Http\Controllers\TicketController::class, 'updateMessage'])->name('tickets.messages.update');

    // Activos del cliente (documentos, paletas, tipografías, urls)
    Route::post('clients/{client}/assets',        [\App\Http\Controllers\ClientAssetController::class, 'store'])->name('clients.assets.store');
    Route::post('client-assets/{asset}',          [\App\Http\Controllers\ClientAssetController::class, 'update'])->name('clients.assets.update');
    Route::delete('client-assets/{asset}',        [\App\Http\Controllers\ClientAssetController::class, 'destroy'])->name('clients.assets.destroy');

    // Lienzo / Storyboard del ticket
    Route::post('tickets/{ticket}/canvas',                   [\App\Http\Controllers\TicketCanvasController::class, 'store'])->name('tickets.canvas.store');
    Route::post('tickets/{ticket}/canvas/reorder',           [\App\Http\Controllers\TicketCanvasController::class, 'reorder'])->name('tickets.canvas.reorder');
    Route::post('ticket-canvas-items/{item}/stack/reorder',  [\App\Http\Controllers\TicketCanvasController::class, 'reorderStack'])->name('tickets.canvas.stack.reorder');
    Route::post('ticket-canvas-items/{item}',                [\App\Http\Controllers\TicketCanvasController::class, 'update'])->name('tickets.canvas.update');
    Route::delete('ticket-canvas-items/{item}',              [\App\Http\Controllers\TicketCanvasController::class, 'destroy'])->name('tickets.canvas.destroy');
    Route::post('ticket-canvas-items/{item}/pins',           [\App\Http\Controllers\TicketCanvasController::class, 'addPin'])->name('tickets.canvas.pins.store');
    Route::post('ticket-canvas-pins/{pin}/toggle',           [\App\Http\Controllers\TicketCanvasController::class, 'togglePin'])->name('tickets.canvas.pins.toggle');
    Route::delete('ticket-canvas-pins/{pin}',                [\App\Http\Controllers\TicketCanvasController::class, 'destroyPin'])->name('tickets.canvas.pins.destroy');
    // Reportes (admin)
    Route::resource('reports', \App\Http\Controllers\ReportController::class);
    Route::get('clients/{client}/tickets-json', [\App\Http\Controllers\ReportController::class, 'clientTickets'])->name('clients.tickets-json');
    Route::get('reports/{report}/pdf', [\App\Http\Controllers\ReportController::class, 'pdf'])->name('reports.pdf');

    // Reportes (panel de cliente)
    Route::prefix('client/reports')->name('client.reports.')->group(function () {
        Route::get('/',          [\App\Http\Controllers\ClientReportController::class, 'index'])  ->name('index');
        Route::get('/create',    [\App\Http\Controllers\ClientReportController::class, 'create']) ->name('create');
        Route::post('/',         [\App\Http\Controllers\ClientReportController::class, 'store'])  ->name('store');
        Route::get('/{report}',  [\App\Http\Controllers\ClientReportController::class, 'show'])   ->name('show');
        Route::get('/{report}/pdf', [\App\Http\Controllers\ClientReportController::class, 'pdf'])->name('pdf');
    });
    Route::get('client/my-tickets', [\App\Http\Controllers\ClientReportController::class, 'myTickets'])->name('client.my-tickets');
    Route::get('client/receipt/{clientService}', [\App\Http\Controllers\ClientReportController::class, 'downloadReceipt'])->name('client.receipt.download');

    Route::post('notifications/mark-as-read', [\App\Http\Controllers\DashboardController::class, 'markNotificationsAsRead'])->name('notifications.markAsRead');
    Route::post('notifications/{id}/mark-one-read', [\App\Http\Controllers\DashboardController::class, 'markOneNotificationAsRead'])->name('notifications.markOneRead');

    // Finanzas
    Route::get('finances', [\App\Http\Controllers\FinanceController::class, 'index'])->name('finances.index');
    Route::get('finances/{client}/receipt', [\App\Http\Controllers\FinanceController::class, 'receipt'])->name('finances.receipt');
    Route::post('finances/{client}/send-receipt', [\App\Http\Controllers\FinanceController::class, 'sendReceipt'])->name('finances.send-receipt');

    // Signature Generator
    Route::resource('signature-templates', \App\Http\Controllers\SignatureTemplateController::class);
    Route::get('/client/signatures', [\App\Http\Controllers\SignatureGeneratorController::class, 'index'])->name('client.signatures');
});

// Public ticket preview — no auth required
Route::get('tickets/p/{token}', [\App\Http\Controllers\TicketController::class, 'publicShow'])->name('tickets.public');

require __DIR__ . '/auth.php';

// Public Contract Routes - Test Webhook Deploy
Route::get('contratodeservicio/{token}', [\App\Http\Controllers\ContractController::class, 'show'])->name('contracts.show');
Route::post('contratodeservicio/{token}/sign', [\App\Http\Controllers\ContractController::class, 'sign'])->name('contracts.sign');
Route::get('contratodeservicio/{token}/pdf', [\App\Http\Controllers\ContractController::class, 'downloadPdf'])->name('contracts.download.pdf');

// WhatsApp Business Cloud API — webhook público (Meta lo llama directamente, sin sesión/CSRF)
Route::get('whatsapp/webhook', [\App\Http\Controllers\WhatsAppWebhookController::class, 'verify'])->name('whatsapp.webhook.verify');
Route::post('whatsapp/webhook', [\App\Http\Controllers\WhatsAppWebhookController::class, 'receive'])->name('whatsapp.webhook.receive');
