<?php

declare(strict_types=1);

use Am2tec\Pdv\Http\Controllers\Api\CatalogController;
use Am2tec\Pdv\Http\Controllers\Api\CouponController;
use Am2tec\Pdv\Http\Controllers\Api\SaleController;
use Am2tec\Pdv\Http\Controllers\Api\TerminalController;
use Illuminate\Support\Facades\Route;

Route::apiResource('terminals', TerminalController::class)
    ->except(['show'])
    ->names('pdv.api.terminals');

Route::get('coupons/validate', [CouponController::class, 'check'])->name('pdv.api.coupons.validate');
Route::apiResource('coupons', CouponController::class)
    ->except(['show'])
    ->names('pdv.api.coupons');

Route::get('catalog', [CatalogController::class, 'index'])->name('pdv.api.catalog');

Route::post('sales', [SaleController::class, 'store'])->name('pdv.api.sales.store');
Route::get('sales', [SaleController::class, 'index'])->name('pdv.api.sales.index');
Route::get('sales/{sale}', [SaleController::class, 'show'])->whereUuid('sale')->name('pdv.api.sales.show');
Route::delete('sales/{sale}', [SaleController::class, 'destroy'])->whereUuid('sale')->name('pdv.api.sales.destroy');
