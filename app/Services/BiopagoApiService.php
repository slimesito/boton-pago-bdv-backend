<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class BiopagoApiService
{
    public function __construct(private BiopagoAuthService $auth) {}

    public function createPayment(array $payload): array
    {
        $response = $this->http()
            ->post(config('biopago.base_url').'/api/Payments', $payload);

        return $this->handleResponse($response);
    }

    public function sendToken(string $paymentId, int $groupId, int $authMethodId): array
    {
        $response = $this->http()
            ->post(config('biopago.base_url').'/api/sendTokens', [
                'paymentId'              => $paymentId,
                'paymentGroupId'         => $groupId,
                'authenticationMethodId' => $authMethodId,
            ]);

        return $this->handleResponse($response);
    }

    public function processPayment(string $paymentId, array $payload): array
    {
        $response = $this->http()
            ->post(config('biopago.base_url')."/api/Payments/{$paymentId}/process", $payload);

        return $this->handleResponse($response);
    }

    public function verifyPayment(string $paymentId): array
    {
        $response = $this->http()
            ->get(config('biopago.base_url')."/api/Payments/{$paymentId}");

        return $this->handleResponse($response);
    }

    public function getPaymentGroups(int $personType): array
    {
        $response = $this->http()
            ->get(config('biopago.base_url').'/api/PaymentGroups', [
                'personType' => $personType,
            ]);

        return $this->handleResponse($response);
    }

    private function http(): PendingRequest
    {
        $http = Http::withToken($this->auth->getAccessToken())
            ->timeout(30)
            ->connectTimeout(10)
            ->retry(2, 500, throw: false);

        if (config('biopago.env') === 'quality') {
            $http = $http->withoutVerifying();
        }

        return $http;
    }

    /**
     * @return array<string, mixed>
     *
     * @throws \Exception
     */
    private function handleResponse(\Illuminate\Http\Client\Response $response): array
    {
        if ($response->serverError()) {
            Log::error('BiopagoApiService: server error', [
                'status' => $response->status(),
                'body'   => $response->body(),
            ]);

            throw new \Exception('Error interno en la pasarela de Biopago. Intente nuevamente.');
        }

        $data = $response->json();

        $responseCode = $data['responseCode'] ?? null;

        if ($responseCode !== 0) {
            $description = $data['responseDescription'] ?? 'Error desconocido en Biopago.';

            Log::warning('BiopagoApiService: non-zero responseCode', [
                'responseCode'        => $responseCode,
                'responseDescription' => $description,
                'body'                => $data,
            ]);

            throw new \Exception($description);
        }

        return $data;
    }
}
