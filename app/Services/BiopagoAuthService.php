<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class BiopagoAuthService
{
    public function getAccessToken(): string
    {
        return Cache::remember('biopago_access_token', 3500, function (): string {
            $response = $this->requestToken();

            if (! $response->successful()) {
                Log::error('BiopagoAuthService: token request failed', [
                    'status' => $response->status(),
                    'body'   => $response->body(),
                ]);

                throw new \Exception('Error autenticando con Biopago: '.$response->status());
            }

            $token = $response->json('access_token');

            if (empty($token)) {
                throw new \Exception('Biopago no devolvió access_token en la respuesta de autenticación.');
            }

            return $token;
        });
    }

    private function requestToken(): \Illuminate\Http\Client\Response
    {
        $http = Http::asForm()
            ->timeout(15)
            ->connectTimeout(10);

        if (config('biopago.env') === 'quality') {
            $http = $http->withoutVerifying();
        }

        return $http->post(config('biopago.token_url'), [
            'grant_type'    => 'client_credentials',
            'client_id'     => config('biopago.client_id'),
            'client_secret' => config('biopago.client_secret'),
        ]);
    }
}
