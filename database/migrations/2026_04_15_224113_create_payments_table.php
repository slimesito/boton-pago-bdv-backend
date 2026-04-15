<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table): void {
            $table->id();
            $table->string('internal_reference')->unique();
            $table->string('biopago_payment_id')->nullable()->index();
            $table->string('biopago_transaction_id')->nullable();
            $table->string('authorization_code')->nullable();
            $table->decimal('amount', 15, 2);
            $table->integer('currency')->default(1);
            $table->string('title');
            $table->string('description');
            $table->string('payer_type')->default('natural');
            $table->string('payer_letter');
            $table->string('payer_number');
            $table->string('rif_letter')->nullable();
            $table->string('rif_number')->nullable();
            $table->string('email')->nullable();
            $table->string('cellphone')->nullable();
            $table->string('status')->default('pending');
            $table->integer('biopago_result_code')->nullable();
            $table->string('url_payment')->nullable();
            $table->text('biopago_response')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
