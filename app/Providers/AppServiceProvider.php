<?php

namespace App\Providers;

use App\Domain\Ai\Contracts\AudioTranscriber;
use App\Domain\Ai\Transcribers\OpenAiTranscriber;
use App\Domain\Tenancy\TenantContext;
use App\Events\Messaging\ConnectorConnected;
use App\Jobs\Messaging\SyncConnectionGroups;
use App\Listeners\Audit\RecordConversationActivity;
use App\Listeners\Conversations\AnnounceConversationChange;
use App\Models\User;
use App\Support\Permissions\Permission;
use App\Support\Permissions\PermissionRegistry;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(TenantContext::class);

        $this->app->bind(
            AudioTranscriber::class,
            fn (): AudioTranscriber => new OpenAiTranscriber(config('ai.transcription', [])),
        );
    }

    public function boot(): void
    {
        Model::preventSilentlyDiscardingAttributes($this->app->isLocal());

        JsonResource::withoutWrapping();

        $this->registerPermissionGates();

        Route::bind('adminUser', fn (string $id): User => User::query()->acrossTenants()->findOrFail($id));

        Event::subscribe(RecordConversationActivity::class);
        Event::subscribe(AnnounceConversationChange::class);

        Event::listen(
            ConnectorConnected::class,
            fn (ConnectorConnected $event) => SyncConnectionGroups::dispatch($event->channelConnection),
        );
    }

    private function registerPermissionGates(): void
    {
        Gate::before(fn (User $user): ?bool => $user->is_super_admin ? true : null);

        PermissionRegistry::all()->each(
            fn (Permission $permission) => Gate::define(
                $permission->key,
                fn (User $user): bool => $user->hasPermission($permission->key),
            )
        );
    }
}
