<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Services\BiopagoApiService;
use App\Services\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PaymentController extends Controller
{
    public function __construct(
        private PaymentService $paymentService,
        private BiopagoApiService $biopagoApiService,
    ) {}

    public function init(Request $request): JsonResponse
    {
        $data = $request->validate([
            'payer_type' => ['required', 'in:natural,juridico'],
            'payer_letter' => ['required', 'in:V,E,P'],
            'payer_number' => ['required', 'digits_between:1,20'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'title' => ['sometimes', 'nullable', 'string', 'max:100'],
            'description' => ['sometimes', 'nullable', 'string', 'max:200'],
            'email' => ['sometimes', 'nullable', 'email'],
            'cellphone' => ['required', 'string', 'size:11'],
            'rif_letter' => ['required_if:payer_type,juridico', 'nullable', 'in:J,G,V'],
            'rif_number' => ['required_if:payer_type,juridico', 'nullable', 'digits_between:1,20'],
        ]);

        try {
            $payment = $this->paymentService->initPayment($data);

            return response()->json([
                'success' => true,
                'payment_id' => $payment->biopago_payment_id,
                'url_payment' => $payment->url_payment,
                'reference' => $payment->internal_reference,
            ]);
        } catch (\Throwable $e) {
            Log::error('PaymentController@init failed', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);

            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function sendToken(Request $request): JsonResponse
    {
        $data = $request->validate([
            'payment_id' => ['required', 'string'],
            'payment_group_id' => ['required', 'integer'],
            'authentication_method_id' => ['required', 'integer'],
        ]);

        try {
            $this->biopagoApiService->sendToken(
                $data['payment_id'],
                $data['payment_group_id'],
                $data['authentication_method_id'],
            );

            return response()->json(['success' => true, 'message' => 'Token enviado exitosamente.']);
        } catch (\Throwable $e) {
            Log::error('PaymentController@sendToken failed', ['error' => $e->getMessage()]);

            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function process(Request $request): JsonResponse
    {
        $data = $request->validate([
            'payment_id' => ['required', 'string'],
            'payment_method_id' => ['required', 'integer'],
            'payment_group_id' => ['required', 'integer'],
            'authentication_token' => ['required', 'string'],
            'authentication_method_id' => ['required', 'integer'],
        ]);

        try {
            $result = $this->biopagoApiService->processPayment($data['payment_id'], [
                'paymentMethodId' => $data['payment_method_id'],
                'paymentGroupId' => $data['payment_group_id'],
                'authenticationToken' => $data['authentication_token'],
                'authenticationMethodId' => $data['authentication_method_id'],
            ]);

            $isApproved = ($result['result'] ?? '') === 'PaymentAccepted';

            if ($isApproved) {
                Payment::where('biopago_payment_id', $data['payment_id'])->update([
                    'status' => 'approved',
                    'biopago_transaction_id' => $result['detail']['transactionId'] ?? null,
                    'authorization_code' => $result['detail']['authorizationCode'] ?? null,
                    'biopago_response' => json_encode($result),
                ]);
            }

            return response()->json([
                'success' => $isApproved,
                'approved' => $isApproved,
                'result' => $result,
            ]);
        } catch (\Throwable $e) {
            Log::error('PaymentController@process failed', ['error' => $e->getMessage()]);

            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function groups(Request $request): JsonResponse
    {
        $data = $request->validate([
            'person_type' => ['required', 'integer', 'in:1,2'],
        ]);

        try {
            $result = $this->biopagoApiService->getPaymentGroups($data['person_type']);

            // Biopago may use 'paymentGroups' or 'groups' as the key
            $rawGroups = $result['paymentGroups'] ?? $result['groups'] ?? [];

            // Normalize to a consistent schema for the frontend
            $groups = array_map(static function (array $g): array {
                return [
                    'id' => $g['paymentGroupId'] ?? $g['id'] ?? null,
                    'name' => $g['paymentGroupName'] ?? $g['name'] ?? '',
                    'paymentMethods' => array_map(static fn (array $m): array => [
                        'id' => $m['paymentMethodId'] ?? $m['id'] ?? null,
                        'name' => $m['paymentMethodName'] ?? $m['name'] ?? '',
                    ], $g['paymentMethods'] ?? []),
                    'authenticationMethods' => array_map(static fn (array $m): array => [
                        'id' => $m['authenticationMethodId'] ?? $m['id'] ?? null,
                        'name' => $m['authenticationMethodName'] ?? $m['name'] ?? '',
                    ], $g['authenticationMethods'] ?? []),
                ];
            }, $rawGroups);

            return response()->json(['success' => true, 'groups' => $groups]);
        } catch (\Throwable $e) {
            Log::error('PaymentController@groups failed', ['error' => $e->getMessage()]);

            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function return(Request $request): RedirectResponse
    {
        $frontendUrl = rtrim(config('biopago.frontend_url'), '/');
        $biopagoPaymentId = $request->query('paymentId');

        if (empty($biopagoPaymentId)) {
            return redirect($frontendUrl.'/payment/result?status=error');
        }

        try {
            $payment = $this->paymentService->verifyAndUpdate($biopagoPaymentId);

            return redirect($frontendUrl.'/payment/result?status='.$payment->status.'&ref='.$payment->internal_reference);
        } catch (\Throwable $e) {
            Log::error('PaymentController@return failed', ['error' => $e->getMessage(), 'paymentId' => $biopagoPaymentId]);

            return redirect($frontendUrl.'/payment/result?status=error');
        }
    }

    public function status(string $reference): JsonResponse
    {
        try {
            $payment = Payment::where('internal_reference', $reference)->firstOrFail();

            return response()->json([
                'reference' => $payment->internal_reference,
                'status' => $payment->status,
                'amount' => $payment->amount,
                'authorization_code' => $payment->authorization_code,
                'transaction_id' => $payment->biopago_transaction_id,
                'payer_type' => $payment->payer_type,
                'created_at' => $payment->created_at,
            ]);
        } catch (\Exception) {
            return response()->json(['success' => false, 'message' => 'Pago no encontrado.'], 404);
        }
    }
}
