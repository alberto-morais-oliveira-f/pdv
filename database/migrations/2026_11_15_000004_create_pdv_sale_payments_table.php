<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pdv_sale_payments', function (Blueprint $table): void {
            $table->id();
            $table->foreignUuid('sale_id')->constrained('pdv_sales')->cascadeOnDelete();
            $table->string('method', 20);
            $table->unsignedBigInteger('amount')->comment('Cents');
            $table->foreignId('terminal_id')->nullable()->constrained('pdv_terminals');
            $table->decimal('fee_percent', 5, 2)->nullable()->comment('Frozen at sale time');
            $table->unsignedBigInteger('fee_amount')->default(0)->comment('Cents, frozen');
            $table->unsignedInteger('fee_fixed_snapshot')->default(0)->comment('Cents, frozen');
            $table->unsignedInteger('settlement_days')->nullable()->comment('Frozen at sale time');
            $table->timestamp('expected_settlement_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pdv_sale_payments');
    }
};
