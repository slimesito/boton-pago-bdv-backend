<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    use HasFactory;

    protected $fillable = [
        'internal_reference',
        'biopago_payment_id',
        'biopago_transaction_id',
        'authorization_code',
        'amount',
        'currency',
        'title',
        'description',
        'payer_type',
        'payer_letter',
        'payer_number',
        'rif_letter',
        'rif_number',
        'email',
        'cellphone',
        'status',
        'biopago_result_code',
        'url_payment',
        'biopago_response',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'biopago_response' => 'array',
            'biopago_result_code' => 'integer',
            'currency' => 'integer',
        ];
    }

    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('status', 'approved');
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', 'pending');
    }
}
