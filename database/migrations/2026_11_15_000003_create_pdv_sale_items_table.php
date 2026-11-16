<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pdv_sale_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignUuid('sale_id')->constrained('pdv_sales')->cascadeOnDelete();
            $table->string('type', 20);
            $table->string('source_type');
            $table->string('source_id');
            $table->string('description');
            $table->unsignedInteger('qty');
            $table->unsignedBigInteger('unit_amount')->comment('Cents');
            $table->unsignedBigInteger('total_amount')->comment('Cents');
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pdv_sale_items');
    }
};
