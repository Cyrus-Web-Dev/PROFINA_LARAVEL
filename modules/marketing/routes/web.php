<?php

use App\Http\Controllers\ComplaintController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DeliveryNoteController;
use App\Http\Controllers\PriceLevelController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\QuotationController;
use App\Http\Controllers\SalesOrderController;
use Illuminate\Support\Facades\Route;

require __DIR__.'/auth.php';

Route::get('/', [DashboardController::class, 'home'])->name('home');

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/api/price-quote', [DashboardController::class, 'priceQuote'])->middleware('permission:quotations.create')->name('price-quote');

    // Pelanggan
    Route::get('/customers', [CustomerController::class, 'index'])->middleware('permission:customers.view')->name('customers.index');
    Route::middleware('permission:customers.manage')->group(function () {
        Route::post('/customers', [CustomerController::class, 'store'])->name('customers.store');
        Route::put('/customers/{customer}', [CustomerController::class, 'update'])->name('customers.update');
        Route::delete('/customers/{customer}', [CustomerController::class, 'destroy'])->name('customers.destroy');
        Route::get('/customers/{customer}/prices', [CustomerController::class, 'prices'])->name('customers.prices');
        Route::put('/customers/{customer}/prices', [CustomerController::class, 'syncPrices'])->name('customers.prices.sync');
    });

    // Produk & harga
    Route::get('/products', [ProductController::class, 'index'])->middleware('permission:products.view')->name('products.index');
    Route::middleware('permission:products.manage')->group(function () {
        Route::post('/products', [ProductController::class, 'store'])->name('products.store');
        Route::put('/products/{product}', [ProductController::class, 'update'])->name('products.update');
        Route::delete('/products/{product}', [ProductController::class, 'destroy'])->name('products.destroy');
    });
    Route::middleware('permission:prices.manage')->group(function () {
        Route::post('/price-levels', [PriceLevelController::class, 'store'])->name('levels.store');
        Route::put('/price-levels/{level}', [PriceLevelController::class, 'update'])->name('levels.update');
    });

    // Penawaran
    Route::get('/quotations', [QuotationController::class, 'index'])->middleware('permission:quotations.view')->name('quotations.index');
    Route::middleware('permission:quotations.create')->group(function () {
        Route::get('/quotations/create', [QuotationController::class, 'create'])->name('quotations.create');
        Route::post('/quotations', [QuotationController::class, 'store'])->name('quotations.store');
        Route::get('/quotations/{quotation}/edit', [QuotationController::class, 'edit'])->name('quotations.edit');
        Route::put('/quotations/{quotation}', [QuotationController::class, 'update'])->name('quotations.update');
        Route::delete('/quotations/{quotation}', [QuotationController::class, 'destroy'])->name('quotations.destroy');
        Route::post('/quotations/{quotation}/send', [QuotationController::class, 'send'])->name('quotations.send');
        Route::post('/quotations/{quotation}/reopen', [QuotationController::class, 'reopen'])->name('quotations.reopen');
    });
    Route::middleware('permission:quotations.approve')->group(function () {
        Route::post('/quotations/{quotation}/approve', [QuotationController::class, 'approve'])->name('quotations.approve');
        Route::post('/quotations/{quotation}/reject', [QuotationController::class, 'reject'])->name('quotations.reject');
    });
    Route::middleware('permission:quotations.view')->group(function () {
        Route::get('/quotations/{quotation}', [QuotationController::class, 'show'])->name('quotations.show');
        Route::get('/quotations/{quotation}/pdf', [QuotationController::class, 'pdf'])->name('quotations.pdf');
    });

    // Sales order
    Route::middleware('permission:orders.view')->group(function () {
        Route::get('/orders', [SalesOrderController::class, 'index'])->name('orders.index');
        Route::get('/orders/{order}', [SalesOrderController::class, 'show'])->name('orders.show');
    });
    Route::post('/orders/{order}/status', [SalesOrderController::class, 'updateStatus'])->middleware('permission:orders.manage')->name('orders.status');

    // Surat jalan
    Route::get('/deliveries', [DeliveryNoteController::class, 'index'])->middleware('permission:deliveries.view')->name('deliveries.index');
    Route::middleware('permission:deliveries.manage')->group(function () {
        Route::get('/orders/{order}/deliveries/create', [DeliveryNoteController::class, 'create'])->name('deliveries.create');
        Route::post('/orders/{order}/deliveries', [DeliveryNoteController::class, 'store'])->name('deliveries.store');
        Route::post('/deliveries/{delivery}/ship', [DeliveryNoteController::class, 'ship'])->name('deliveries.ship');
        Route::post('/deliveries/{delivery}/receive', [DeliveryNoteController::class, 'receive'])->name('deliveries.receive');
        Route::delete('/deliveries/{delivery}', [DeliveryNoteController::class, 'destroy'])->name('deliveries.destroy');
    });
    Route::middleware('permission:deliveries.view')->group(function () {
        Route::get('/deliveries/{delivery}', [DeliveryNoteController::class, 'show'])->name('deliveries.show');
        Route::get('/deliveries/{delivery}/pdf', [DeliveryNoteController::class, 'pdf'])->name('deliveries.pdf');
    });

    // Komplain
    Route::get('/complaints', [ComplaintController::class, 'index'])->middleware('permission:complaints.view')->name('complaints.index');
    Route::middleware('permission:complaints.manage')->group(function () {
        Route::post('/complaints', [ComplaintController::class, 'store'])->name('complaints.store');
        Route::put('/complaints/{complaint}', [ComplaintController::class, 'update'])->name('complaints.update');
    });
});
