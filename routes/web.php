<?php

use App\Http\Controllers\Admin\GlobalAuditLogController;
use App\Http\Controllers\Admin\TenantAccessController;
use App\Http\Controllers\Admin\TenantController;
use App\Http\Controllers\Admin\UsageController;
use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Controllers\Admin\WorkspaceController;
use App\Http\Controllers\AiObjectiveController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\LockScreenController;
use App\Http\Controllers\CardController;
use App\Http\Controllers\ChannelConnectionController;
use App\Http\Controllers\ChatFlowController;
use App\Http\Controllers\ConnectionSessionController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\Conversations\ConversationAssignmentController;
use App\Http\Controllers\Conversations\ConversationController;
use App\Http\Controllers\Conversations\ConversationMessageController;
use App\Http\Controllers\Conversations\ConversationNoteController;
use App\Http\Controllers\Conversations\ConversationPresenceController;
use App\Http\Controllers\Conversations\ConversationStatusController;
use App\Http\Controllers\Conversations\ConversationTagController;
use App\Http\Controllers\Conversations\StartConversationController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\QuickReplyController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\ServiceQueueController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\TagController;
use App\Http\Controllers\Tenancy\TenantSelectionController;
use App\Http\Controllers\UserController;
use App\Http\Middleware\AuthorizeRoute;
use App\Http\Middleware\EnsureConversationIsVisible;
use App\Http\Middleware\EnsureScreenIsUnlocked;
use App\Http\Middleware\EnsureUserIsAvailable;
use App\Http\Middleware\EnsureUserIsSuperAdmin;
use App\Http\Middleware\IdentifyTenant;
use App\Http\Middleware\RequireTenant;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/painel');

Route::middleware('guest')->group(function (): void {
    Route::get('/entrar', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/entrar', [AuthenticatedSessionController::class, 'store']);
});

Route::middleware(['auth', IdentifyTenant::class])->group(function (): void {
    Route::post('/sair', [AuthenticatedSessionController::class, 'destroy'])->name('logout');

    Route::get('/bloqueio', [LockScreenController::class, 'show'])->name('lock.show');
    Route::post('/bloqueio', [LockScreenController::class, 'store'])->name('lock.store');
    Route::put('/bloqueio', [LockScreenController::class, 'update'])->name('lock.unlock');

    Route::get('/selecionar-conta', [TenantSelectionController::class, 'create'])->name('tenants.select');
    Route::post('/selecionar-conta', [TenantSelectionController::class, 'store'])->name('tenants.choose');

    Route::middleware([RequireTenant::class, EnsureUserIsAvailable::class, EnsureScreenIsUnlocked::class, AuthorizeRoute::class])->group(function (): void {
        Route::get('/painel', [DashboardController::class, 'index'])->name('dashboard');

        Route::put('/perfil', [ProfileController::class, 'update'])->name('profile.update');

        Route::get('/busca', SearchController::class)->name('search');

        Route::prefix('/atendimentos')->name('conversations.')->middleware(EnsureConversationIsVisible::class)->group(function (): void {
            Route::get('/', [ConversationController::class, 'index'])->name('index');
            Route::post('/', [StartConversationController::class, 'store'])->name('store');
            Route::get('/contatos', [StartConversationController::class, 'contacts'])->name('contacts');
            Route::get('/{conversation}', [ConversationController::class, 'show'])->name('show');
            Route::get('/{conversation}/mensagens/{message}/midia', [ConversationMessageController::class, 'media'])->name('messages.media');
            Route::post('/{conversation}/mensagens', [ConversationMessageController::class, 'store'])->name('messages.store');
            Route::post('/{conversation}/mensagens/{message}/reenviar', [ConversationMessageController::class, 'resend'])->name('messages.resend');
            Route::post('/{conversation}/mensagens/{message}/cancelar', [ConversationMessageController::class, 'cancel'])->name('messages.cancel');
            Route::delete('/{conversation}/mensagens/{message}', [ConversationMessageController::class, 'destroy'])->name('messages.destroy');
            Route::post('/{conversation}/lida', [ConversationStatusController::class, 'read'])->name('read');
            Route::post('/{conversation}/digitando', [ConversationPresenceController::class, 'typing'])->name('typing');
            Route::post('/{conversation}/encerrar', [ConversationStatusController::class, 'close'])->name('close');
            Route::post('/{conversation}/reabrir', [ConversationStatusController::class, 'reopen'])->name('reopen');
            Route::put('/{conversation}/transferencia', [ConversationAssignmentController::class, 'transfer'])->name('transfer');
            Route::post('/{conversation}/assumir', [ConversationAssignmentController::class, 'take'])->name('take');
            Route::put('/{conversation}/tags', [ConversationTagController::class, 'sync'])->name('tags.sync');
            Route::post('/{conversation}/notas', [ConversationNoteController::class, 'store'])->name('notes.store');
            Route::delete('/{conversation}/notas/{note}', [ConversationNoteController::class, 'destroy'])->name('notes.destroy');
        });

        Route::prefix('/contatos')->name('contacts.')->group(function (): void {
            Route::get('/', [ContactController::class, 'index'])->name('index');
            Route::post('/', [ContactController::class, 'store'])->name('store');
            Route::get('/{contact}', [ContactController::class, 'show'])->name('show');
            Route::put('/{contact}', [ContactController::class, 'update'])->name('update');
            Route::delete('/{contact}', [ContactController::class, 'destroy'])->name('destroy');
        });

        Route::prefix('/filas')->name('service-queues.')->group(function (): void {
            Route::get('/', [ServiceQueueController::class, 'index'])->name('index');
            Route::post('/', [ServiceQueueController::class, 'store'])->name('store');
            Route::put('/{serviceQueue}', [ServiceQueueController::class, 'update'])->name('update');
            Route::delete('/{serviceQueue}', [ServiceQueueController::class, 'destroy'])->name('destroy');
        });

        Route::prefix('/tags')->name('tags.')->group(function (): void {
            Route::get('/', [TagController::class, 'index'])->name('index');
            Route::post('/', [TagController::class, 'store'])->name('store');
            Route::put('/{tag}', [TagController::class, 'update'])->name('update');
            Route::delete('/{tag}', [TagController::class, 'destroy'])->name('destroy');
        });

        Route::prefix('/respostas-rapidas')->name('quick-replies.')->group(function (): void {
            Route::get('/', [QuickReplyController::class, 'index'])->name('index');
            Route::post('/', [QuickReplyController::class, 'store'])->name('store');
            Route::put('/{quickReply}', [QuickReplyController::class, 'update'])->name('update');
            Route::post('/{quickReply}/favorito', [QuickReplyController::class, 'favorite'])->name('favorite');
            Route::delete('/{quickReply}', [QuickReplyController::class, 'destroy'])->name('destroy');
        });

        Route::prefix('/cartoes')->name('cards.')->group(function (): void {
            Route::get('/', [CardController::class, 'index'])->name('index');
            Route::post('/', [CardController::class, 'store'])->name('store');
            Route::put('/{card}', [CardController::class, 'update'])->name('update');
            Route::delete('/{card}', [CardController::class, 'destroy'])->name('destroy');
        });

        Route::prefix('/conexoes')->name('connections.')->group(function (): void {
            Route::get('/', [ChannelConnectionController::class, 'index'])->name('index');
            Route::get('/{connection}', [ChannelConnectionController::class, 'show'])->name('show');
            Route::put('/{connection}', [ChannelConnectionController::class, 'update'])->name('update');
            Route::post('/{connection}/conectar', [ConnectionSessionController::class, 'connect'])->name('connect');
            Route::post('/{connection}/status', [ConnectionSessionController::class, 'status'])->name('status');
            Route::post('/{connection}/desconectar', [ConnectionSessionController::class, 'disconnect'])->name('disconnect');
        });

        Route::prefix('/objetivos-de-ia')->name('ai-objectives.')->group(function (): void {
            Route::get('/', [AiObjectiveController::class, 'index'])->name('index');
            Route::get('/novo', [AiObjectiveController::class, 'create'])->name('create');
            Route::post('/', [AiObjectiveController::class, 'store'])->name('store');
            Route::get('/{aiObjective}/editar', [AiObjectiveController::class, 'edit'])->name('edit');
            Route::put('/{aiObjective}', [AiObjectiveController::class, 'update'])->name('update');
            Route::delete('/{aiObjective}', [AiObjectiveController::class, 'destroy'])->name('destroy');
        });

        Route::prefix('/fluxos')->name('chat-flows.')->group(function (): void {
            Route::get('/', [ChatFlowController::class, 'index'])->name('index');
            Route::post('/', [ChatFlowController::class, 'store'])->name('store');
            Route::get('/{chatFlow}', [ChatFlowController::class, 'show'])->name('show');
            Route::put('/{chatFlow}', [ChatFlowController::class, 'update'])->name('update');
            Route::delete('/{chatFlow}', [ChatFlowController::class, 'destroy'])->name('destroy');
        });

        Route::prefix('/usuarios')->name('users.')->group(function (): void {
            Route::get('/', [UserController::class, 'index'])->name('index');
            Route::post('/', [UserController::class, 'store'])->name('store');
            Route::put('/{user}', [UserController::class, 'update'])->name('update');
            Route::delete('/{user}', [UserController::class, 'destroy'])->name('destroy');
        });

        Route::prefix('/papeis')->name('roles.')->group(function (): void {
            Route::get('/', [RoleController::class, 'index'])->name('index');
            Route::post('/', [RoleController::class, 'store'])->name('store');
            Route::put('/{role}', [RoleController::class, 'update'])->name('update');
            Route::delete('/{role}', [RoleController::class, 'destroy'])->name('destroy');
        });

        Route::get('/relatorios', [ReportController::class, 'index'])->name('reports.index');
        Route::get('/auditoria', [AuditLogController::class, 'index'])->name('audit-logs.index');

        Route::get('/configuracoes', [SettingController::class, 'edit'])->name('settings.edit');
        Route::put('/configuracoes', [SettingController::class, 'update'])->name('settings.update');
    });

    Route::middleware([EnsureUserIsAvailable::class, EnsureScreenIsUnlocked::class, EnsureUserIsSuperAdmin::class])
        ->prefix('/admin')
        ->name('admin.')
        ->group(function (): void {
            Route::redirect('/', '/admin/tenants');

            Route::get('/tenants', [TenantController::class, 'index'])->name('tenants.index');
            Route::post('/tenants', [TenantController::class, 'store'])->name('tenants.store');
            Route::put('/tenants/acesso', [TenantAccessController::class, 'update'])->name('tenants.access');
            Route::put('/tenants/{tenant}', [TenantController::class, 'update'])->name('tenants.update');
            Route::delete('/tenants/{tenant}', [TenantController::class, 'destroy'])->name('tenants.destroy');

            Route::post('/tenants/{tenant}/acessar', [WorkspaceController::class, 'store'])->name('tenants.enter');
            Route::delete('/workspace', [WorkspaceController::class, 'destroy'])->name('workspace.leave');

            Route::prefix('/usuarios')->name('users.')->group(function (): void {
                Route::get('/', [AdminUserController::class, 'index'])->name('index');
                Route::post('/', [AdminUserController::class, 'store'])->name('store');
                Route::put('/{adminUser}', [AdminUserController::class, 'update'])->name('update');
                Route::delete('/{adminUser}', [AdminUserController::class, 'destroy'])->name('destroy');
            });

            Route::get('/uso', [UsageController::class, 'index'])->name('usage.index');
            Route::get('/auditoria', [GlobalAuditLogController::class, 'index'])->name('audit-logs.index');
        });
});
