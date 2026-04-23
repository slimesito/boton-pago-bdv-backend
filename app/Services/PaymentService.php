<?php

namespace App\Services;

use App\Models\Payment;
use Illuminate\Support\Facades\Log;

class PaymentService
{
    public function __construct(private BiopagoApiService $biopago) {}

    public function initPayment(array $data): Payment
    {
        $data['title'] ??= 'Aportes IVSS';
        $data['description'] ??= 'Liquidación de aportes al Instituto Venezolano de los Seguros Sociales';

        $reference = 'IVSS-'.date('Ymd').'-'.strtoupper(substr(uniqid(), -6));

        $payload = [
            'letter' => $data['payer_letter'],
            'number' => $data['payer_number'],
            'amount' => $data['amount'],
            'currency' => $data['currency'] ?? 1,
            'reference' => $reference,
            'title' => $data['title'],
            'description' => $data['description'],
            'email' => $data['email'] ?? null,
            'cellphone' => $data['cellphone'],
            'urlToReturn' => config('biopago.url_to_return'),
        ];

        if (($data['payer_type'] ?? 'natural') === 'juridico') {
            $payload['rifLetter'] = $data['rif_letter'];
            $payload['rifNumber'] = $data['rif_number'];
        }

        $result = $this->biopago->createPayment($payload);

        return Payment::create([
            'internal_reference' => $reference,
            'biopago_payment_id' => $result['paymentId'],
            'amount' => $data['amount'],
            'currency' => $data['currency'] ?? 1,
            'title' => $data['title'],
            'description' => $data['description'],
            'payer_type' => $data['payer_type'] ?? 'natural',
            'payer_letter' => $data['payer_letter'],
            'payer_number' => $data['payer_number'],
            'rif_letter' => $data['rif_letter'] ?? null,
            'rif_number' => $data['rif_number'] ?? null,
            'email' => $data['email'] ?? null,
            'cellphone' => $data['cellphone'],
            'status' => 'pending',
            'url_payment' => $result['urlPayment'],
        ]);
    }

    public function verifyAndUpdate(string $biopagoPaymentId): Payment
    {
        $payment = Payment::where('biopago_payment_id', $biopagoPaymentId)->firstOrFail();

        $result = $this->biopago->verifyPayment($biopagoPaymentId);

        $isApproved = $this->isPaymentApproved($result);
        $status = $isApproved ? 'approved' : $this->mapResultToStatus($result['result'] ?? 0);

        $payment->update([
            'status' => $status,
            'biopago_result_code' => $result['result'] ?? null,
            'biopago_transaction_id' => $result['transactionId'] ?? null,
            'authorization_code' => $result['authorizationCode'] ?? null,
            'biopago_response' => $result,
        ]);

        Log::info('PaymentService: payment verified', [
            'internal_reference' => $payment->internal_reference,
            'status' => $status,
            'result' => $result['result'] ?? null,
        ]);

        return $payment->fresh();
    }

    /**
     * Validates the 3 approval criteria for interface-based payments.
     *
     * @param  array<string, mixed>  $result
     */
    private function isPaymentApproved(array $result): bool
    {
        if (($result['status'] ?? null) !== 1) {
            return false;
        }

        if (($result['result'] ?? null) !== 1) {
            return false;
        }

        $authCode = $result['authorizationCode'] ?? null;

        if ($authCode === null || $authCode === '' || $authCode === '000000') {
            return false;
        }

        if (! ctype_digit((string) $authCode)) {
            return false;
        }

        return true;
    }

    public function mapResultToStatus(int $result): string
    {
        return match ($result) {
            1 => 'approved',
            7 => 'cancelled',
            2, 3, 6, 8 => 'rejected',
            default => 'rejected',
        };
    }
}
