<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class SupabaseCentralService
{
    public function rpc(string $function, array $arguments): mixed
    {
        $response = $this->request()->post("/rest/v1/rpc/{$function}", $arguments);

        return $this->decode($response);
    }

    public function table(string $table, array $query = []): array
    {
        $response = $this->request()->get("/rest/v1/{$table}", $query);
        $data = $this->decode($response);

        return is_array($data) ? $data : [];
    }

    public function updateNode(int $id, array $changes): array
    {
        $response = $this->request()
            ->withHeaders(['Prefer' => 'return=representation'])
            ->patch("/rest/v1/nodos?id=eq.{$id}", $changes);
        $data = $this->decode($response);

        return is_array($data) ? $data : [];
    }

    private function request(): PendingRequest
    {
        $url = (string) config('central.supabase_url');
        $key = (string) config('central.service_role_key');

        if ($url === '' || $key === '') {
            throw new RuntimeException('Configura SUPABASE_URL y SUPABASE_SERVICE_ROLE_KEY en laravel/.env.');
        }

        return Http::baseUrl($url)
            ->acceptJson()
            ->asJson()
            ->withHeaders([
                'apikey' => $key,
                'Authorization' => "Bearer {$key}",
            ])
            ->timeout(15);
    }

    private function decode(Response $response): mixed
    {
        if ($response->failed()) {
            $message = $response->json('message')
                ?? $response->json('msg')
                ?? 'Supabase rechazó la solicitud.';

            throw new RuntimeException((string) $message, $response->status());
        }

        return $response->json();
    }
}
