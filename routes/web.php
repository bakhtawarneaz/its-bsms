<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\OtpController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\GatewayUserController;
use App\Http\Controllers\MaskingController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\ResellerController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\TemplateController;
use App\Http\Controllers\GroupController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\ComposeController;
use App\Http\Controllers\CampaignController;
use App\Http\Controllers\PersonalizedController;

Route::redirect('/', '/login');

// Guest-only routes
Route::middleware('portal.guest')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->name('login.store');
    Route::get('/otp', [OtpController::class, 'recipients'])->name('otp.recipients');
    Route::post('/otp/send', [OtpController::class, 'send'])->middleware('throttle:5,1')->name('otp.send');
    Route::get('/otp/verify', [OtpController::class, 'show'])->name('otp.verify');
    Route::post('/otp/verify', [OtpController::class, 'verify'])->name('otp.verify.store');
    Route::post('/otp/resend', [OtpController::class, 'resend'])->middleware('throttle:5,1')->name('otp.resend');
});

// Authenticated routes
Route::middleware('portal.auth')->group(function () {
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Profile
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::put('/profile/password', [ProfileController::class, 'password'])->middleware('throttle:5,1')->name('profile.password');

    // Superadmin
    Route::middleware('portal.role:superadmin')->group(function () {
        // Users: anyone can view and add; changes need the legacy EditUsers flag.
        Route::get('/users', [UserController::class, 'index'])->name('users.index');
        Route::get('/users/create', [UserController::class, 'create'])->name('users.create');
        Route::post('/users', [UserController::class, 'store'])->name('users.store');

        Route::middleware('portal.can:EditUsers')->whereNumber('user')->group(function () {
            Route::get('/users/{user}/edit', [UserController::class, 'edit'])->name('users.edit');
            Route::put('/users/{user}', [UserController::class, 'update'])->name('users.update');
            Route::patch('/users/{user}/status', [UserController::class, 'status'])->name('users.status');
            Route::patch('/users/{user}/unblock', [UserController::class, 'unblock'])->name('users.unblock');
            Route::post('/users/{user}/balance', [UserController::class, 'balance'])->name('users.balance');
            Route::delete('/users/{user}', [UserController::class, 'destroy'])->name('users.destroy');
        });

        // Maskings: status changes need the legacy MaskApproval flag.
        Route::get('/maskings', [MaskingController::class, 'index'])->name('maskings.index');
        Route::get('/maskings/create', [MaskingController::class, 'create'])->name('maskings.create');
        Route::post('/maskings', [MaskingController::class, 'store'])->name('maskings.store');
        Route::get('/maskings/requests', [MaskingController::class, 'requests'])->name('maskings.requests');
        Route::post('/maskings/status', [MaskingController::class, 'status'])->middleware('portal.can:MaskApproval')->name('maskings.status');
        Route::get('/maskings/{masking}/edit', [MaskingController::class, 'edit'])->whereNumber('masking')->name('maskings.edit');
        Route::put('/maskings/{masking}', [MaskingController::class, 'update'])->whereNumber('masking')->name('maskings.update');

        // Resellers: editing an existing reseller needs EditUsers (legacy edited it as a user).
        Route::get('/resellers', [ResellerController::class, 'index'])->name('resellers.index');
        Route::get('/resellers/create', [ResellerController::class, 'create'])->name('resellers.create');
        Route::post('/resellers', [ResellerController::class, 'store'])->name('resellers.store');
        Route::middleware('portal.can:EditUsers')->whereNumber('reseller')->group(function () {
            Route::get('/resellers/{reseller}/edit', [ResellerController::class, 'edit'])->name('resellers.edit');
            Route::put('/resellers/{reseller}', [ResellerController::class, 'update'])->name('resellers.update');
        });

        // Compose
        Route::get('/compose', [ComposeController::class, 'quick'])->name('compose.index');
        Route::post('/compose/quick', [ComposeController::class, 'quickSend'])->middleware('throttle:20,1')->name('compose.quick');
        Route::get('/compose/campaign', [ComposeController::class, 'campaign'])->name('compose.campaign');
        Route::post('/compose/campaign', [ComposeController::class, 'campaignPrepare'])->middleware('throttle:10,1')->name('compose.campaign.prepare');
        Route::post('/compose/campaign/confirm', [ComposeController::class, 'campaignConfirm'])->middleware('throttle:10,1')->name('compose.campaign.confirm');
        Route::post('/compose/campaign/cancel', [ComposeController::class, 'campaignCancel'])->name('compose.campaign.cancel');
        Route::post('/compose/campaign/resend', [ComposeController::class, 'campaignResend'])->middleware('throttle:5,1')->name('compose.campaign.resend');
        Route::get('/compose/personalize', [ComposeController::class, 'personalize'])->name('compose.personalize');
        Route::post('/compose/personalize', [ComposeController::class, 'personalizeSend'])->middleware('throttle:10,1')->name('compose.personalize.send');

        // Campaign management
        Route::post('/campaigns/{campaign}/revert', [CampaignController::class, 'revert'])->whereNumber('campaign')->name('campaigns.revert');
        // Route::get('/campaigns', [CampaignController::class, 'index'])->name('campaigns.index');
        // Route::whereNumber('campaign')->group(function () {
        //     Route::post('/campaigns/{campaign}/action', [CampaignController::class, 'action'])->name('campaigns.action');
        //     Route::get('/campaigns/{campaign}/logs', [CampaignController::class, 'logs'])->name('campaigns.logs');
        //     Route::post('/campaigns/{campaign}/revert', [CampaignController::class, 'revert'])->name('campaigns.revert');
        // });

        // Gateway users
        Route::get('/gateway-users', [GatewayUserController::class, 'index'])->name('gateway.users');
        Route::middleware('portal.can:MaskApproval')->group(function () {
            Route::get('/gateway-users/mask-routes', [GatewayUserController::class, 'maskRoutes'])->name('gateway.mask-routes');
            Route::put('/gateway-users/mask-routes', [GatewayUserController::class, 'updateMaskRoutes'])->name('gateway.mask-routes.update');
        });
        Route::middleware('portal.can:GWUserCreate')->group(function () {
            Route::get('/gateway-users/create', [GatewayUserController::class, 'create'])->name('gateway.users.create');
            Route::post('/gateway-users', [GatewayUserController::class, 'store'])->name('gateway.users.store');
            Route::whereNumber('gatewayUser')->group(function () {
                Route::get('/gateway-users/{gatewayUser}/edit', [GatewayUserController::class, 'edit'])->name('gateway.users.edit');
                Route::put('/gateway-users/{gatewayUser}', [GatewayUserController::class, 'update'])->name('gateway.users.update');
                Route::post('/gateway-users/{gatewayUser}/balance', [GatewayUserController::class, 'balance'])->name('gateway.users.balance');
                Route::post('/gateway-users/{gatewayUser}/masks', [GatewayUserController::class, 'addMasks'])->name('gateway.users.masks.store');
                Route::delete('/gateway-users/{gatewayUser}/masks', [GatewayUserController::class, 'deleteMasks'])->name('gateway.users.masks.destroy');
                Route::post('/gateway-users/{gatewayUser}/two-factor', [GatewayUserController::class, 'addTwoFactor'])->name('gateway.users.2fa.store');
            });
        });

        // Templates (superadmin's own) and OTP templates for any user
        Route::get('/templates', [TemplateController::class, 'index'])->name('templates.index');
        Route::post('/templates', [TemplateController::class, 'store'])->name('templates.store');
        Route::put('/templates/{template}', [TemplateController::class, 'update'])->whereNumber('template')->name('templates.update');
        Route::delete('/templates/{template}', [TemplateController::class, 'destroy'])->whereNumber('template')->name('templates.destroy');
        Route::get('/templates/otp', [TemplateController::class, 'otp'])->name('templates.otp');
        Route::post('/templates/otp', [TemplateController::class, 'otpStore'])->name('templates.otp.store');

        // Contact groups (superadmin's own)
        Route::get('/groups', [GroupController::class, 'index'])->name('groups.index');
        Route::post('/groups', [GroupController::class, 'store'])->name('groups.store');
        Route::whereNumber('group')->group(function () {
            Route::get('/groups/{group}', [GroupController::class, 'show'])->name('groups.show');
            Route::put('/groups/{group}', [GroupController::class, 'update'])->name('groups.update');
            Route::delete('/groups/{group}', [GroupController::class, 'destroy'])->name('groups.destroy');
            //Route::post('/groups/{group}/contacts', [GroupController::class, 'assign'])->name('groups.assign');
        });

        // Contacts (superadmin sees every user's contacts, adds to its own)
        Route::get('/contacts', [ContactController::class, 'index'])->name('contacts.index');
        Route::post('/contacts', [ContactController::class, 'store'])->name('contacts.store');
        Route::get('/contacts/export', [ContactController::class, 'export'])->name('contacts.export');
        Route::post('/contacts/import', [ContactController::class, 'import'])->name('contacts.import');
        Route::whereNumber('contact')->group(function () {
            Route::put('/contacts/{contact}', [ContactController::class, 'update'])->name('contacts.update');
            Route::delete('/contacts/{contact}', [ContactController::class, 'destroy'])->name('contacts.destroy');
            Route::post('/contacts/{contact}/groups', [ContactController::class, 'groups'])->name('contacts.groups');
        });

        // Reports (names match config/navigation.php)
        Route::get('/reports/outbox', [ReportController::class, 'outbox'])->name('outbox.index');
        Route::get('/reports/outbox/export', [ReportController::class, 'outboxExport'])->name('outbox.export');
        Route::get('/reports/inbox', [ReportController::class, 'log'])->defaults('report', 'inbox')->name('inbox.index');
        Route::get('/reports/inbox/export', [ReportController::class, 'logExport'])->defaults('report', 'inbox')->name('inbox.export');
        Route::get('/reports/gateway-outbox', [ReportController::class, 'log'])->defaults('report', 'gateway-outbox')->name('gateway.outbox');
        Route::get('/reports/gateway-outbox/export', [ReportController::class, 'logExport'])->defaults('report', 'gateway-outbox')->name('gateway.outbox.export');
        Route::get('/reports/gateway-inbox', [ReportController::class, 'log'])->defaults('report', 'gateway-inbox')->name('gateway.inbox');
        Route::get('/reports/gateway-inbox/export', [ReportController::class, 'logExport'])->defaults('report', 'gateway-inbox')->name('gateway.inbox.export');
        Route::get('/reports/summary', [ReportController::class, 'summary'])->defaults('report', 'summary')->name('summary.index');
        Route::get('/reports/summary/export', [ReportController::class, 'summaryExport'])->defaults('report', 'summary')->name('summary.export');
        Route::get('/reports/gateway-summary', [ReportController::class, 'summary'])->defaults('report', 'gateway-summary')->name('gateway.summary');
        Route::get('/reports/gateway-summary/export', [ReportController::class, 'summaryExport'])->defaults('report', 'gateway-summary')->name('gateway.summary.export');
        Route::get('/reports/activity', [ReportController::class, 'activity'])->name('activity.index');
    });

        // Campaign management: campaign approvers see only their own users' campaigns
    Route::middleware('portal.role:superadmin,campapprover')->group(function () {
        Route::get('/campaigns', [CampaignController::class, 'index'])->name('campaigns.index');
        Route::whereNumber('campaign')->group(function () {
            Route::post('/campaigns/{campaign}/action', [CampaignController::class, 'action'])->name('campaigns.action');
            Route::get('/campaigns/{campaign}/logs', [CampaignController::class, 'logs'])->name('campaigns.logs');
        });
        // Personalized SMS approval
        Route::get('/personalized', [PersonalizedController::class, 'index'])->name('personalized.index');
        Route::post('/personalized/action', [PersonalizedController::class, 'action'])->middleware('throttle:30,1')->name('personalized.action');
    });
    
});