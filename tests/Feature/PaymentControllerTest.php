<?php

namespace Tests\Feature;

use App\Models\Payment;
use App\Services\BiopagoApiService;
use App\Services\PaymentService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Mockery\MockInterface;
use Tests\TestCase;

class PaymentControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    // ── POST /api/payments/init ──────────────────────────────────────────────

    public function test_init_creates_payment_and_returns_ids(): void
    {
        $this->mock(PaymentService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('initPayment')
                ->once()
                ->andReturn(Payment::factory()->make([
                    'internal_reference' => 'IVSS-20260423-ABCDEF',
                    'biopago_payment_id' => 'biopago-uuid-123',
                    'url_payment' => 'https://biopago.example.com/pay/123',
                ]));
        });

        $response = $this->postJson('/api/payments/init', [
            'payer_type' => 'natural',
            'payer_letter' => 'V',
            'payer_number' => '12345678',
            'amount' => 1250.50,
            'cellphone' => '04121234567',
        ]);

        $response->assertOk()->assertJson([
            'success' => true,
            'payment_id' => 'biopago-uuid-123',
            'reference' => 'IVSS-20260423-ABCDEF',
        ]);
    }

    public function test_init_requires_all_mandatory_fields(): void
    {
        $response = $this->postJson('/api/payments/init', []);

        $response->assertUnprocessable()->assertJsonValidationErrors([
            'payer_type', 'payer_letter', 'payer_number', 'amount', 'cellphone',
        ]);
    }

    public function test_init_rejects_invalid_payer_type(): void
    {
        $response = $this->postJson('/api/payments/init', [
            'payer_type' => 'empresa',
            'payer_letter' => 'V',
            'payer_number' => '12345678',
            'amount' => 100,
            'cellphone' => '04121234567',
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors(['payer_type']);
    }

    public function test_init_requires_rif_for_juridico(): void
    {
        $response = $this->postJson('/api/payments/init', [
            'payer_type' => 'juridico',
            'payer_letter' => 'V',
            'payer_number' => '12345678',
            'amount' => 100,
            'cellphone' => '04121234567',
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors(['rif_letter', 'rif_number']);
    }

    public function test_init_rejects_cellphone_not_11_digits(): void
    {
        $response = $this->postJson('/api/payments/init', [
            'payer_type' => 'natural',
            'payer_letter' => 'V',
            'payer_number' => '12345678',
            'amount' => 100,
            'cellphone' => '0412123',
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors(['cellphone']);
    }

    public function test_init_returns_422_when_service_throws(): void
    {
        $this->mock(PaymentService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('initPayment')
                ->once()
                ->andThrow(new \Exception('Error interno en la pasarela de Biopago.'));
        });

        $response = $this->postJson('/api/payments/init', [
            'payer_type' => 'natural',
            'payer_letter' => 'V',
            'payer_number' => '12345678',
            'amount' => 100,
            'cellphone' => '04121234567',
        ]);

        $response->assertUnprocessable()->assertJson([
            'success' => false,
            'message' => 'Error interno en la pasarela de Biopago.',
        ]);
    }

    // ── POST /api/payments/send-token ────────────────────────────────────────

    public function test_send_token_returns_success(): void
    {
        $this->mock(BiopagoApiService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('sendToken')
                ->once()
                ->with('payment-id', 1, 1)
                ->andReturn(['responseCode' => 0]);
        });

        $response = $this->postJson('/api/payments/send-token', [
            'payment_id' => 'payment-id',
            'payment_group_id' => 1,
            'authentication_method_id' => 1,
        ]);

        $response->assertOk()->assertJson(['success' => true]);
    }

    public function test_send_token_requires_all_fields(): void
    {
        $response = $this->postJson('/api/payments/send-token', []);

        $response->assertUnprocessable()->assertJsonValidationErrors([
            'payment_id', 'payment_group_id', 'authentication_method_id',
        ]);
    }

    // ── GET /api/payments/groups ─────────────────────────────────────────────

    public function test_groups_returns_normalized_list(): void
    {
        $this->mock(BiopagoApiService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('getPaymentGroups')
                ->once()
                ->with(1)
                ->andReturn([
                    'responseCode' => 0,
                    'paymentGroups' => [
                        [
                            'paymentGroupId' => 1,
                            'paymentGroupName' => 'Débito BDV',
                            'paymentMethods' => [['paymentMethodId' => 1, 'paymentMethodName' => 'Débito']],
                            'authenticationMethods' => [['authenticationMethodId' => 1, 'authenticationMethodName' => 'OTP SMS']],
                        ],
                    ],
                ]);
        });

        $response = $this->getJson('/api/payments/groups?person_type=1');

        $response->assertOk()->assertJson([
            'success' => true,
            'groups' => [
                [
                    'id' => 1,
                    'name' => 'Débito BDV',
                    'paymentMethods' => [['id' => 1, 'name' => 'Débito']],
                    'authenticationMethods' => [['id' => 1, 'name' => 'OTP SMS']],
                ],
            ],
        ]);
    }

    public function test_groups_requires_valid_person_type(): void
    {
        $response = $this->getJson('/api/payments/groups?person_type=5');

        $response->assertUnprocessable()->assertJsonValidationErrors(['person_type']);
    }

    // ── GET /api/payments/{reference}/status ─────────────────────────────────

    public function test_status_returns_404_for_unknown_reference(): void
    {
        $response = $this->getJson('/api/payments/NONEXISTENT/status');

        $response->assertNotFound()->assertJson(['success' => false]);
    }

    // ── POST /api/payments/process ───────────────────────────────────────────

    public function test_process_requires_all_fields(): void
    {
        $response = $this->postJson('/api/payments/process', []);

        $response->assertUnprocessable()->assertJsonValidationErrors([
            'payment_id', 'payment_method_id', 'payment_group_id',
            'authentication_token', 'authentication_method_id',
        ]);
    }

    public function test_process_returns_422_when_api_throws(): void
    {
        $this->mock(BiopagoApiService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('processPayment')
                ->once()
                ->andThrow(new \Exception('Token inválido o expirado.'));
        });

        $response = $this->postJson('/api/payments/process', [
            'payment_id' => 'any-id',
            'payment_method_id' => 1,
            'payment_group_id' => 1,
            'authentication_token' => '000000',
            'authentication_method_id' => 1,
        ]);

        $response->assertUnprocessable()->assertJson([
            'success' => false,
            'message' => 'Token inválido o expirado.',
        ]);
    }

    public function test_process_not_approved_returns_approved_false(): void
    {
        $this->mock(BiopagoApiService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('processPayment')
                ->once()
                ->andReturn([
                    'responseCode' => 0,
                    'result' => 'PaymentRejected',
                    'responseDescription' => 'Fondos insuficientes',
                ]);
        });

        $response = $this->postJson('/api/payments/process', [
            'payment_id' => 'any-id',
            'payment_method_id' => 1,
            'payment_group_id' => 1,
            'authentication_token' => '000000',
            'authentication_method_id' => 1,
        ]);

        $response->assertOk()->assertJson(['success' => false, 'approved' => false]);
    }
}
