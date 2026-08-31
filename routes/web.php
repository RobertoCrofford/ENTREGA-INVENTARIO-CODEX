<?php

use App\Http\Controllers\AssetController;
use App\Http\Controllers\AssetDisposalController;
use App\Http\Controllers\AssetImportController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\InventoryMovementController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\RepairController;
use App\Http\Controllers\ScanController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\WarehouseController;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

Route::redirect('/', '/dashboard');

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])->middleware('throttle:login')->name('login.store');
    Route::get('/forgot-password', [PasswordResetLinkController::class, 'create'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetLinkController::class, 'store'])->middleware('throttle:6,1')->name('password.email');
    Route::get('/reset-password/{token}', [NewPasswordController::class, 'create'])->name('password.reset');
    Route::post('/reset-password', [NewPasswordController::class, 'store'])->middleware('throttle:6,1')->name('password.store');
});

Route::middleware(['auth', 'active'])->group(function () {
    Route::get('/password', [PasswordController::class, 'edit'])->name('password.edit');
    Route::put('/password', [PasswordController::class, 'update'])->name('password.update');
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');

    Route::middleware('password.changed')->group(function () {
        Route::get('/dashboard', function () {
            return view('dashboard', [
                'stockAgotado' => Schema::hasTable('existencias') ? DB::table('existencias')->where('activo', true)->where('cantidad', 0)->count() : 0,
                'totalActivos' => Schema::hasTable('activos') ? DB::table('activos')->count() : 0,
                'activosOperativos' => Schema::hasTable('activos') && Schema::hasTable('estados_activo') ? DB::table('activos')->join('estados_activo', 'activos.estado_activo_id', '=', 'estados_activo.id')->where('estados_activo.codigo', 'operativo')->count() : 0,
                'activosSinClasificar' => Schema::hasTable('activos') ? DB::table('activos')->where('uso', 'sin_definir')->count() : 0,
                'activosReparacion' => Schema::hasTable('activos') && Schema::hasTable('estados_activo') ? DB::table('activos')->join('estados_activo', 'activos.estado_activo_id', '=', 'estados_activo.id')->where('estados_activo.codigo', 'en_reparacion')->count() : 0,
                'solicitudesPendientes' => Schema::hasTable('solicitudes_baja_activo') ? DB::table('solicitudes_baja_activo')->where('estado', 'pendiente')->count() : 0,
                'movimientosRecientes' => Schema::hasTable('movimientos_inventario') ? DB::table('movimientos_inventario')->where('estado', 'publicado')->orderByDesc('publicado_at')->limit(5)->get() : collect(),
            ]);
        })->name('dashboard');
        Route::resource('users', UserController::class)->except('show', 'destroy');
        Route::put('users/{user}/reset-password', [UserController::class, 'resetPassword'])->name('users.reset-password');
        Route::get('notifications', [NotificationController::class, 'index'])->name('notifications.index');
        Route::post('notifications/read-all', [NotificationController::class, 'markAllRead'])->name('notifications.read-all');
        Route::post('notifications/{notificationId}/attend', [NotificationController::class, 'attend'])->name('notifications.attend');
        Route::get('audit-logs', [AuditLogController::class, 'index'])->name('audit-logs.index');
        Route::get('audit-logs/generate', [AuditLogController::class, 'generateForm'])->name('audit-logs.generate.form');
        Route::post('audit-logs/generate', [AuditLogController::class, 'generate'])->name('audit-logs.generate');
        Route::get('audit-logs/{archiveId}/download', [AuditLogController::class, 'download'])->name('audit-logs.download');
        Route::get('scan', [ScanController::class, 'index'])->name('scan.index');
        Route::post('scan', [ScanController::class, 'search'])->name('scan.search');
        Route::get('warehouses', [WarehouseController::class, 'index'])->name('warehouses.index');
        Route::resource('products', ProductController::class);
        Route::resource('movements', InventoryMovementController::class)->only('index', 'create', 'store');
        Route::get('assets/{asset}/classify', [AssetController::class, 'classify'])->name('assets.classify');
        Route::patch('assets/{asset}/usage', [AssetController::class, 'updateUsage'])->name('assets.update-usage');
        Route::resource('assets', AssetController::class)->except('destroy');
        Route::get('imports/assets', [AssetImportController::class, 'index'])->name('imports.assets.index');
        Route::get('imports/assets/export', [AssetImportController::class, 'export'])->name('imports.assets.export');
        Route::get('imports/assets/template', [AssetImportController::class, 'template'])->name('imports.assets.template');
        Route::post('imports/assets/preview', [AssetImportController::class, 'preview'])->name('imports.assets.preview');
        Route::get('imports/assets/{import}', [AssetImportController::class, 'show'])->name('imports.assets.show');
        Route::post('imports/assets/{import}/confirm', [AssetImportController::class, 'confirm'])->name('imports.assets.confirm');
        Route::get('imports/assets/{import}/rejected', [AssetImportController::class, 'rejected'])->name('imports.assets.rejected');
        Route::post('assets/{asset}/status', [AssetController::class, 'changeStatus'])->name('assets.status');
        Route::post('assets/{asset}/reincorporate', [AssetController::class, 'reincorporate'])->name('assets.reincorporate');
        Route::get('asset-disposals', [AssetDisposalController::class, 'index'])->name('asset-disposals.index');
        Route::post('asset-disposals', [AssetDisposalController::class, 'store'])->name('asset-disposals.store');
        Route::post('asset-disposals/{disposal}/approve', [AssetDisposalController::class, 'approve'])->name('asset-disposals.approve');
        Route::post('asset-disposals/{disposal}/reject', [AssetDisposalController::class, 'reject'])->name('asset-disposals.reject');
        Route::get('asset-disposals/{disposal}/download', [AssetDisposalController::class, 'download'])->name('asset-disposals.download');
        Route::resource('repairs', RepairController::class)->only('index', 'create', 'store');
        Route::get('repairs/{repair}/evidences/{evidence}', [RepairController::class, 'evidence'])->name('repairs.evidence');
        Route::post('repairs/{repair}/complete', [RepairController::class, 'complete'])->name('repairs.complete');
    });
});
