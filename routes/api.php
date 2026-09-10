<?php

use App\Http\Controllers\Api\MockCheckoutController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\ProductController;
use Illuminate\Support\Facades\Route;

// Products
Route::get('/products', [ProductController::class, 'index'])->name('products.index');

// Orders
Route::post('/orders', [OrderController::class, 'store'])->name('orders.store');
Route::post('/orders/{order}/cancel', [OrderController::class, 'cancel'])->name('orders.cancel');
Route::post('/orders/{order}/process', [OrderController::class, 'process'])->name('orders.process');
Route::post('/orders/{order}/ship', [OrderController::class, 'ship'])->name('orders.ship');
Route::post('/orders/{order}/complete', [OrderController::class, 'complete'])->name('orders.complete');
Route::get('/orders/{order}/status-history', [OrderController::class, 'statusHistory'])->name('orders.statusHistory');

// Payment
Route::post('/orders/{order}/pay', [PaymentController::class, 'initiate'])->name('payments.initiate');
Route::post('/webhooks/payment', [PaymentController::class, 'webhook'])->name('payments.webhook');

// Mock payment gateway checkout (development / testing only)
Route::get('/mock-checkout/{reference}', [MockCheckoutController::class, 'show'])->name('mock.checkout');
