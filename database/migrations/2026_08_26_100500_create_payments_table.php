<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** REQ-005 — gateway audit trail. Planning §11.A.5, §12.2. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('provider', 32)->default('toyyibpay');
            $table->string('bill_code', 64)->nullable();

            // The duplicate-callback guard, second line of defence behind the
            // guarded status transition. A repeat callback hits this unique key
            // and is caught as a no-op (Planning §11.A.5).
            $table->string('provider_ref', 128)->nullable()->unique();

            $table->unsignedInteger('amount_minor');
            $table->string('status', 32)->default('pending');

            // Kept for reconciliation, scrubbed of credentials before it is
            // written (spec §24, Planning §15).
            $table->json('raw_response')->nullable();

            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->index('order_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
