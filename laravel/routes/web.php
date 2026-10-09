<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CentralController;
use App\Http\Controllers\NodeController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\TransactionController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/admin');
Route::get('/login', [AuthController::class, 'create'])->name('login');
Route::post('/login', [AuthController::class, 'store'])->middleware('throttle:5,1')->name('login.store');

Route::middleware(['central.admin'])->group(function (): void {
    Route::post('/logout', [AuthController::class, 'destroy'])->name('logout');
    Route::get('/admin', [CentralController::class, 'index'])->name('admin.dashboard');
    Route::get('/admin/nodos', [NodeController::class, 'index'])->name('admin.nodes.index');
    Route::post('/admin/nodos', [NodeController::class, 'store'])->name('admin.nodes.store');
    Route::patch('/admin/nodos/{id}', [NodeController::class, 'update'])->whereNumber('id')->name('admin.nodes.update');
    Route::post('/admin/ajustes-efectivo', [NodeController::class, 'adjustCash'])->name('admin.nodes.cash-adjustments.store');
    Route::get('/admin/reportes', [ReportController::class, 'index'])->name('admin.reports.index');
    Route::get('/admin/transacciones/exportar', [TransactionController::class, 'export'])->name('admin.transactions.export');
    Route::get('/admin/transacciones', [TransactionController::class, 'index'])->name('admin.transactions.index');
});
