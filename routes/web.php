<?php

declare(strict_types=1);

use Am2tec\Pdv\Http\Controllers\PdvPageController;
use Illuminate\Support\Facades\Route;

Route::get('counter', [PdvPageController::class, 'counter'])->name('pdv.counter');
Route::get('terminals', [PdvPageController::class, 'terminals'])->name('pdv.terminals');
Route::get('coupons', [PdvPageController::class, 'coupons'])->name('pdv.coupons');
Route::get('sales', [PdvPageController::class, 'sales'])->name('pdv.sales');
