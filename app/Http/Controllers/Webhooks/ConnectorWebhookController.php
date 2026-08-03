<?php

namespace App\Http\Controllers\Webhooks;

use App\Domain\Messaging\Contracts\HandlesWebhooks;
use App\Domain\Messaging\Contracts\VerifiesWebhookSubscription;
use App\Http\Controllers\Controller;
use App\Jobs\Messaging\ProcessConnectorEvent;
use App\Models\ChannelConnection;
use App\Services\Messaging\ConnectorManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class ConnectorWebhookController extends Controller
{
    public function __construct(private readonly ConnectorManager $connectors) {}

    public function verify(Request $request, string $connection): Response
    {
        $connector = $this->connectors->for($this->resolve($connection));

        if (! $connector instanceof VerifiesWebhookSubscription) {
            return response('', 404);
        }

        $challenge = $connector->respondToSubscriptionChallenge($request);

        return $challenge === null
            ? response('', 403)
            : response($challenge);
    }

    public function handle(Request $request, string $connection): JsonResponse
    {
        $model = $this->resolve($connection);
        $connector = $this->connectors->for($model);

        abort_unless($connector instanceof HandlesWebhooks, 404);

        if (! $connector->verifyWebhook($request)) {
            Log::warning('Connector callback refused.', [
                'connection_id' => $model->id,
                'driver' => $model->driver,
                'event' => $request->input('EventType', $request->input('event')),
                'carries_token' => filled($request->input('token')),
            ]);

            abort(401);
        }

        foreach ($connector->parseWebhook($request) as $event) {
            ProcessConnectorEvent::dispatch($model, $event);
        }

        return response()->json(['received' => true]);
    }

    private function resolve(string $id): ChannelConnection
    {
        return ChannelConnection::query()
            ->acrossTenants()
            ->whereKey($id)
            ->where('is_active', true)
            ->firstOrFail();
    }
}
