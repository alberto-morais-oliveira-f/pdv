<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pdv_sales', function (Blueprint $table): void {
            $table->unsignedBigInteger('discount_amount')->default(0)->comment('Cents');
            $table->string('discount_type', 20)->default('none');
            $table->unsignedInteger('discount_percent_bps')->nullable();
            $table->foreignId('coupon_id')->nullable()->constrained('pdv_coupons')->nullOnDelete();
            $table->string('coupon_code_snapshot', 64)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('pdv_sales', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('coupon_id');
            $table->dropColumn([
                'discount_amount',
                'discount_type',
                'discount_percent_bps',
                'coupon_code_snapshot',
            ]);
        });
    }
};
