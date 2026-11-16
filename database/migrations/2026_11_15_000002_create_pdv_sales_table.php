<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pdv_sales', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('status', 20)->index();
            $table->unsignedBigInteger('operator_id')->nullable()->index();
            $table->string('customer_type')->nullable();
            $table->string('customer_id')->nullable();
            $table->unsignedBigInteger('gross_amount')->comment('Cents');
            $table->unsignedBigInteger('fees_amount')->default(0)->comment('Cents');
            $table->bigInteger('net_amount')->comment('Cents');
            $table->timestamp('occurred_at')->index();
            $table->timestamps();

            $table->index(['customer_type', 'customer_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pdv_sales');
    }
};
