<?php

namespace App\Domain\Messaging\Connectors\Uazapi;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class UazapiAdminClient
{
    public function isConfigured(): bool
    {
        return filled($this->baseUrl()) && filled($this->adminToken());
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listInstances(): array
    {
        $response = $this->request()->get('/instance/all');

        if ($response->failed()) {
            Log::warning('Could not list the account instances.', ['response' => $response->body()]);

            return [];
        }

        $instances = $response->json();

        return is_array($instances) ? array_values(array_filter($instances, 'is_array')) : [];
    }

    public function deleteInstance(string $instanceToken): bool
    {
        $response = Http::baseUrl($this->baseUrl())
            ->withHeaders(['token' => $instanceToken])
            ->acceptJson()
            ->timeout(30)
            ->delete('/instance');

        if ($response->failed()) {
            Log::warning('Could not delete the instance.', ['response' => $response->body()]);

            return false;
        }

        return true;
    }

    private function request(): PendingRequest
    {
        return Http::baseUrl($this->baseUrl())
            ->withHeaders(['admintoken' => $this->adminToken()])
            ->acceptJson()
            ->timeout(30);
    }

    private function baseUrl(): string
    {
        return rtrim((string) config('connectors.drivers.uazapi.credentials.base_url'), '/');
    }

    private function adminToken(): string
    {
        return (string) config('connectors.drivers.uazapi.credentials.admin_token');
    }
}
