<?php

namespace App\Http\Controllers;

use App\Actions\Messaging\ProvisionConnection;
use App\Domain\Messaging\Contracts\ManagesSession;
use App\Domain\Messaging\Contracts\SupportsPairingCode;
use App\Domain\Messaging\DataObjects\ConnectionStatusUpdate;
use App\Domain\Messaging\Enums\ConnectionStatus;
use App\Domain\Messaging\Exceptions\ConnectorException;
use App\Http\Requests\PairConnectionRequest;
use App\Jobs\Messaging\RefreshPairingSession;
use App\Models\ChannelConnection;
use App\Services\Messaging\ConnectionStatusSynchronizer;
use App\Services\Messaging\ConnectorManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;

class ConnectionSessionController extends Controller
{
    public function __construct(
        private readonly ConnectorManager $connectors,
        private readonly ConnectionStatusSynchronizer $synchronizer,
        private readonly ProvisionConnection $provision,
    ) {}

    public function connect(PairConnectionRequest $request, ChannelConnection $connection): RedirectResponse
    {
        if (! $this->provision->handle($connection)) {
            return back()->with('error', 'Não foi possível preparar o canal agora. Tente novamente em instantes.');
        }

        $phone = $request->pairingPhone();

        return $this->run(
            $connection->refresh(),
            fn (ManagesSession $connector) => $this->startPairing($connector, $phone),
            $phone === null
                ? 'Pareamento iniciado. Leia o QR Code no aplicativo.'
                : 'Pareamento iniciado. Digite o código no aplicativo do número informado.',
            to_route('connections.show', $connection),
        );
    }

    private function startPairing(ManagesSession $connector, ?string $phone): ConnectionStatusUpdate
    {
        if ($phone !== null && $connector instanceof SupportsPairingCode) {
            return $connector->pairWithPhone($phone);
        }

        return $connector->connect();
    }

    public function status(ChannelConnection $connection): RedirectResponse
    {
        return $this->run($connection, fn (ManagesSession $connector) => $connector->status(), 'Status atualizado.');
    }

    public function disconnect(ChannelConnection $connection): RedirectResponse
    {
        return $this->run($connection, fn (ManagesSession $connector) => $connector->disconnect(), 'Conexão encerrada.');
    }

    private function run(ChannelConnection $connection, callable $callback, string $message, ?RedirectResponse $success = null): RedirectResponse
    {
        $connector = $this->connectors->for($connection);

        if (! $connector instanceof ManagesSession) {
            return back()->with('error', 'Este canal não precisa de pareamento.');
        }

        try {
            $this->synchronizer->apply($connection, $callback($connector));

            if ($connection->status === ConnectionStatus::Connecting) {
                RefreshPairingSession::dispatch($connection)->delay(now()->addSeconds(RefreshPairingSession::INTERVAL));
            }
        } catch (ConnectorException $exception) {
            Log::error('Channel session failed.', [
                'connection_id' => $connection->id,
                'tenant_id' => $connection->tenant_id,
                'driver' => $connection->driver,
                'error' => $exception->getMessage(),
            ]);

            $connection->forceFill(['last_error' => $exception->getMessage()])->save();

            return back()->with('error', 'Não foi possível falar com o canal agora. Tente novamente em instantes.');
        }

        return ($success ?? back())->with('success', $message);
    }
}
