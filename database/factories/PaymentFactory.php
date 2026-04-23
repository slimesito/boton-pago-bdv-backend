<?php

namespace Database\Factories;

use App\Models\Payment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'internal_reference' => 'IVSS-'.date('Ymd').'-'.strtoupper(fake()->lexify('??????')),
            'biopago_payment_id' => fake()->uuid(),
            'biopago_transaction_id' => null,
            'authorization_code' => null,
            'amount' => fake()->randomFloat(2, 10, 50000),
            'currency' => 1,
            'title' => 'Aportes IVSS',
            'description' => 'Liquidación de aportes al Instituto Venezolano de los Seguros Sociales',
            'payer_type' => 'natural',
            'payer_letter' => fake()->randomElement(['V', 'E', 'P']),
            'payer_number' => fake()->numerify('########'),
            'rif_letter' => null,
            'rif_number' => null,
            'email' => fake()->optional()->email(),
            'cellphone' => '0412'.fake()->numerify('#######'),
            'status' => 'pending',
            'biopago_result_code' => null,
            'url_payment' => fake()->url(),
            'biopago_response' => null,
        ];
    }

    public function approved(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'approved',
            'biopago_transaction_id' => fake()->uuid(),
            'authorization_code' => fake()->numerify('######'),
            'biopago_result_code' => 1,
        ]);
    }

    public function juridico(): static
    {
        return $this->state(fn (array $attributes) => [
            'payer_type' => 'juridico',
            'rif_letter' => 'J',
            'rif_number' => fake()->numerify('#########'),
        ]);
    }
}
