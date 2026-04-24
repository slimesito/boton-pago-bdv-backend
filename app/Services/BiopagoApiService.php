<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class BiopagoApiService
{
    public function __construct(private BiopagoAuthService $auth) {}

    public function createPayment(array $payload): array
    {
        if ($this->isDemo()) {
            $paymentId = 'DEMO-'.strtoupper(uniqid());

            return [
                'responseCode' => 0,
                'responseDescription' => 'OK',
                'paymentId' => $paymentId,
                'urlPayment' => config('biopago.frontend_url', 'http://localhost:5173').'/demo-payment/'.$paymentId,
            ];
        }

        $response = $this->http()
            ->post(config('biopago.base_url').'/api/Payments', $payload);

        return $this->handleResponse($response);
    }

    public function sendToken(string $paymentId, int $groupId, int $authMethodId): array
    {
        if ($this->isDemo()) {
            return [
                'responseCode' => 0,
                'responseDescription' => 'Token enviado exitosamente (modo demo). Use cualquier código de 6 dígitos.',
            ];
        }

        $response = $this->http()
            ->post(config('biopago.base_url').'/api/sendTokens', [
                'paymentId' => $paymentId,
                'paymentGroupId' => $groupId,
                'authenticationMethodId' => $authMethodId,
            ]);

        return $this->handleResponse($response);
    }

    public function processPayment(string $paymentId, array $payload): array
    {
        if ($this->isDemo()) {
            $txnId = 'TXN-DEMO-'.strtoupper(uniqid());
            $authCode = (string) random_int(100000, 999999);

            return [
                'responseCode' => 0,
                'responseDescription' => 'Pago aprobado (modo demo)',
                'result' => 'PaymentAccepted',
                'detail' => [
                    'transactionId' => $txnId,
                    'authorizationCode' => $authCode,
                ],
            ];
        }

        $response = $this->http()
            ->post(config('biopago.base_url')."/api/Payments/{$paymentId}/process", $payload);

        return $this->handleResponse($response);
    }

    public function verifyPayment(string $paymentId): array
    {
        if ($this->isDemo()) {
            return [
                'responseCode' => 0,
                'responseDescription' => 'OK',
                'status' => 1,
                'result' => 1,
                'transactionId' => 'TXN-DEMO-'.strtoupper(uniqid()),
                'authorizationCode' => (string) random_int(100000, 999999),
            ];
        }

        $response = $this->http()
            ->get(config('biopago.base_url')."/api/Payments/{$paymentId}");

        return $this->handleResponse($response);
    }

    public function getPaymentGroups(int $personType): array
    {
        if ($this->isDemo()) {
            return [
                'responseCode' => 0,
                'responseDescription' => 'OK',
                'paymentGroups' => [
                    [
                        'paymentGroupId' => 1,
                        'paymentGroupName' => 'Banco de Venezuela (BDV)',
                        'paymentMethods' => [
                            ['paymentMethodId' => 1, 'paymentMethodName' => 'Débito'],
                        ],
                        'authenticationMethods' => [
                            ['authenticationMethodId' => 1, 'authenticationMethodName' => 'SMS'],
                            ['authenticationMethodId' => 2, 'authenticationMethodName' => 'Clave Dinámica'],
                        ],
                    ],
                    [
                        'paymentGroupId' => 2,
                        'paymentGroupName' => 'Banesco',
                        'paymentMethods' => [
                            ['paymentMethodId' => 2, 'paymentMethodName' => 'Débito'],
                            ['paymentMethodId' => 3, 'paymentMethodName' => 'Crédito'],
                        ],
                        'authenticationMethods' => [
                            ['authenticationMethodId' => 1, 'authenticationMethodName' => 'SMS'],
                        ],
                    ],
                ],
            ];
        }

        $response = $this->http()
            ->get(config('biopago.base_url').'/api/PaymentGroups', [
                'personType' => $personType,
            ]);

        return $this->handleResponse($response);
    }

    private function isDemo(): bool
    {
        return config('biopago.env') === 'demo';
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
    private function handleResponse(Response $response): array
    {
        if ($response->serverError()) {
            Log::error('BiopagoApiService: server error', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            throw new \Exception('Error interno en la pasarela de Biopago. Intente nuevamente.');
        }

        $data = $response->json();

        $responseCode = $data['responseCode'] ?? null;

        if ($responseCode !== 0) {
            $description = $data['responseDescription'] ?? 'Error desconocido en Biopago.';

            Log::warning('BiopagoApiService: non-zero responseCode', [
                'responseCode' => $responseCode,
                'responseDescription' => $description,
                'body' => $data,
            ]);

            throw new \Exception($description);
        }

        return $data;
    }
}
