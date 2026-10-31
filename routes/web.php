<?php

use App\Http\Controllers\AssetController;
use App\Http\Controllers\AssetDisposalController;
use App\Http\Controllers\AssetImportController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\CalendarEventController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\AdminRecoveryController;
use App\Http\Controllers\Auth\InitialSetupController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\InventoryMovementController;
use App\Http\Controllers\PhysicalInventoryController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\SystemStatusController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\RepairController;
use App\Http\Controllers\ScanController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\WarehouseController;
use App\Http\Controllers\DashboardReportController;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

Route::redirect('/', '/dashboard');
Route::get('/ready', function () {
    try {
        DB::select('select 1');

        return response()->json(['status' => 'ok']);
    } catch (\Throwable) {
        return response()->json(['status' => 'unavailable'], 503);
    }
})->name('health.ready');
// La administración manual de ubicaciones se retiró. Conservamos esta redirección
// para que enlaces o pestañas antiguas no muestren un error 404.
Route::redirect('/locations', '/dashboard');

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])->middleware('throttle:login')->name('login.store');
    Route::get('/setup', [InitialSetupController::class, 'create'])->name('setup.create');
    Route::post('/setup', [InitialSetupController::class, 'store'])->middleware('throttle:3,1')->name('setup.store');
    Route::get('/recover-admin', [AdminRecoveryController::class, 'create'])->name('admin-recovery.create');
    Route::post('/recover-admin', [AdminRecoveryController::class, 'store'])->middleware('throttle:3,1')->name('admin-recovery.store');
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
            $isTechnicalSupport = auth()->user()->tieneRol(\App\Models\Role::APOYO_TECNICO);
            $assetStates = Schema::hasTable('estados_activo') ? DB::table('activos')
                ->join('estados_activo', 'activos.estado_activo_id', '=', 'estados_activo.id')
                ->selectRaw('estados_activo.codigo, COUNT(*) as total')->groupBy('estados_activo.codigo')->pluck('total', 'codigo') : collect();
            $repairStates = Schema::hasTable('reparaciones') ? DB::table('reparaciones')
                ->selectRaw('estado, COUNT(*) as total')->groupBy('estado')->pluck('total', 'estado') : collect();
            $physicalStates = Schema::hasTable('inventarios_fisicos') ? DB::table('inventarios_fisicos')
                ->selectRaw('estado, COUNT(*) as total')->groupBy('estado')->pluck('total', 'estado') : collect();
            $months = collect(range(5, 0))->map(fn ($offset) => now()->subMonths($offset)->format('Y-m'));
            $eventRows = Schema::hasTable('eventos_calendario') ? DB::table('eventos_calendario')
                ->where('estado', '!=', 'cancelado')->where('inicio_at', '>=', now()->subMonths(5)->startOfMonth())
                ->selectRaw("DATE_FORMAT(inicio_at, '%Y-%m') as periodo, COUNT(*) as total")->groupBy('periodo')->pluck('total', 'periodo') : collect();
            $movementRows = Schema::hasTable('movimientos_inventario') ? DB::table('movimientos_inventario')
                ->where('estado', 'publicado')->where('publicado_at', '>=', now()->subMonths(5)->startOfMonth())
                ->selectRaw("DATE_FORMAT(publicado_at, '%Y-%m') as periodo, COUNT(*) as total")->groupBy('periodo')->pluck('total', 'periodo') : collect();
            $eventMonths = $months->map(fn ($month) => ['label' => \Carbon\Carbon::createFromFormat('Y-m', $month)->translatedFormat('M'), 'total' => (int) ($eventRows[$month] ?? 0)]);
            $movementMonths = $months->map(fn ($month) => ['label' => \Carbon\Carbon::createFromFormat('Y-m', $month)->translatedFormat('M'), 'total' => (int) ($movementRows[$month] ?? 0)]);
            $eventsThisWeek = Schema::hasTable('eventos_calendario') ? DB::table('eventos_calendario')->where('estado', '!=', 'cancelado')->whereBetween('inicio_at', [now()->startOfWeek(), now()->endOfWeek()])->count() : 0;
            $eventsThisYear = Schema::hasTable('eventos_calendario') ? DB::table('eventos_calendario')->where('estado', '!=', 'cancelado')->whereYear('inicio_at', now()->year)->count() : 0;
            $physicalTotal = (int) $physicalStates->sum();

            return view('dashboard', [
                'isTechnicalSupport' => $isTechnicalSupport,
                'stockAgotado' => Schema::hasTable('existencias') ? DB::table('productos as producto')->where('producto.activo', true)
                    ->whereNotExists(fn ($query) => $query->selectRaw('1')->from('existencias as existencia')
                        ->whereColumn('existencia.producto_id', 'producto.id')->where('existencia.activo', true)->where('existencia.cantidad', '>', 0))->count() : 0,
                'totalActivos' => Schema::hasTable('activos') ? DB::table('activos')->count() : 0,
                'activosOperativos' => Schema::hasTable('activos') && Schema::hasTable('estados_activo') ? DB::table('activos')->join('estados_activo', 'activos.estado_activo_id', '=', 'estados_activo.id')->where('estados_activo.codigo', 'operativo')->count() : 0,
                'activosSinClasificar' => Schema::hasTable('activos') ? DB::table('activos')->where('uso', 'sin_definir')->count() : 0,
                'activosReparacion' => Schema::hasTable('activos') && Schema::hasTable('estados_activo') ? DB::table('activos')->join('estados_activo', 'activos.estado_activo_id', '=', 'estados_activo.id')->where('estados_activo.codigo', 'en_reparacion')->count() : 0,
                'solicitudesPendientes' => Schema::hasTable('solicitudes_baja_activo') ? DB::table('solicitudes_baja_activo')->where('estado', 'pendiente')->when($isTechnicalSupport, fn ($query) => $query->where('solicitado_por', auth()->id()))->count() : 0,
                'movimientosRecientes' => Schema::hasTable('movimientos_inventario') ? DB::table('movimientos_inventario')->where('estado', 'publicado')->orderByDesc('publicado_at')->limit(5)->get() : collect(),
                'assetChart' => [
                    ['label' => 'Operativos', 'count' => (int) ($assetStates['operativo'] ?? 0), 'color' => '#52c888'],
                    ['label' => 'En reparación', 'count' => (int) ($assetStates['en_reparacion'] ?? 0), 'color' => '#f2c14e'],
                    ['label' => 'Dado de baja', 'count' => (int) ($assetStates['dado_baja'] ?? 0), 'color' => '#e4778b'],
                    ['label' => 'Otros', 'count' => max(0, (int) (Schema::hasTable('activos') ? DB::table('activos')->count() : 0) - (int) $assetStates->sum()), 'color' => '#9f8bb1'],
                ],
                'repairChart' => [
                    ['label' => 'Abiertas', 'count' => (int) ($repairStates['abierta'] ?? 0), 'color' => '#e4778b'],
                    ['label' => 'En proceso', 'count' => (int) ($repairStates['en_proceso'] ?? 0), 'color' => '#f2c14e'],
                    ['label' => 'Resueltas', 'count' => (int) ($repairStates['resuelta'] ?? 0), 'color' => '#52c888'],
                ],
                'physicalTotal' => $physicalTotal,
                'physicalFinished' => (int) ($physicalStates['finalizado'] ?? 0),
                'eventMonths' => $eventMonths,
                'movementMonths' => $movementMonths,
                'eventsThisWeek' => $eventsThisWeek,
                'eventsThisYear' => $eventsThisYear,
            ]);
        })->name('dashboard');
        Route::get('/dashboard/report', [DashboardReportController::class, 'download'])->name('dashboard.report');
        Route::resource('users', UserController::class)->except('show', 'destroy');
        Route::put('users/{user}/reset-password', [UserController::class, 'resetPassword'])->name('users.reset-password');
        Route::get('notifications/summary', [NotificationController::class, 'summary'])->name('notifications.summary');
        Route::get('notifications', [NotificationController::class, 'index'])->name('notifications.index');
        Route::get('notifications/{notificationId}/open', [NotificationController::class, 'open'])->name('notifications.open');
        Route::get('system-status', [SystemStatusController::class, 'index'])->name('system-status.index');
        Route::post('system-status/backup', [SystemStatusController::class, 'requestBackup'])->name('system-status.backup');
        Route::post('notifications/read-all', [NotificationController::class, 'markAllRead'])->name('notifications.read-all');
        Route::post('notifications/{notificationId}/attend', [NotificationController::class, 'attend'])->name('notifications.attend');
        Route::get('audit-logs', [AuditLogController::class, 'index'])->name('audit-logs.index');
        Route::get('audit-logs/generate', [AuditLogController::class, 'generateForm'])->name('audit-logs.generate.form');
        Route::post('audit-logs/generate', [AuditLogController::class, 'generate'])->name('audit-logs.generate');
        Route::get('audit-logs/{archiveId}/download', [AuditLogController::class, 'download'])->name('audit-logs.download');
        Route::get('scan', [ScanController::class, 'index'])->name('scan.index');
        Route::post('scan', [ScanController::class, 'search'])->name('scan.search');
        Route::view('help', 'help.index')->name('help.index');
        Route::get('warehouses', [WarehouseController::class, 'index'])->name('warehouses.index');
        Route::get('events', [CalendarEventController::class, 'index'])->name('events.index');
        Route::post('events', [CalendarEventController::class, 'store'])->name('events.store');
        Route::post('events/import', [CalendarEventController::class, 'import'])->name('events.import');
        Route::post('events/{event}/cancel', [CalendarEventController::class, 'cancel'])->name('events.cancel');
        Route::resource('products', ProductController::class);
        Route::resource('movements', InventoryMovementController::class)->only('index', 'create', 'store');
        Route::get('movements/group/create', [InventoryMovementController::class, 'groupCreate'])->name('movements.group.create');
        Route::post('movements/group', [InventoryMovementController::class, 'groupStore'])->name('movements.group.store');
        Route::get('movements/group/draft', [InventoryMovementController::class, 'groupDraft'])->name('movements.group.draft');
        Route::put('movements/group/draft', [InventoryMovementController::class, 'saveGroupDraft'])->name('movements.group.draft.save');
        Route::post('movements/{movement}/confirm-receipt', [InventoryMovementController::class, 'confirmReceipt'])->name('movements.confirm-receipt');
        Route::get('physical-inventories', [PhysicalInventoryController::class, 'index'])->name('physical-inventories.index');
        Route::post('physical-inventories', [PhysicalInventoryController::class, 'store'])->name('physical-inventories.store');
        Route::get('physical-inventories/{physicalInventory}', [PhysicalInventoryController::class, 'show'])->name('physical-inventories.show');
        Route::get('physical-inventories/{physicalInventory}/print', [PhysicalInventoryController::class, 'print'])->name('physical-inventories.print');
        Route::post('physical-inventories/{physicalInventory}/scan', [PhysicalInventoryController::class, 'scan'])->name('physical-inventories.scan');
        Route::delete('physical-inventories/{physicalInventory}/scans/{asset}', [PhysicalInventoryController::class, 'removeScan'])->name('physical-inventories.scans.destroy');
        Route::post('physical-inventories/{physicalInventory}/complete', [PhysicalInventoryController::class, 'complete'])->name('physical-inventories.complete');
        Route::get('assets/{asset}/classify', [AssetController::class, 'classify'])->name('assets.classify');
        Route::patch('assets/{asset}/usage', [AssetController::class, 'updateUsage'])->name('assets.update-usage');
        Route::resource('assets', AssetController::class)->except('destroy');
        Route::get('assets/{asset}/print', [AssetController::class, 'print'])->name('assets.print');
        Route::get('imports/assets', [AssetImportController::class, 'index'])->name('imports.assets.index');
        Route::get('imports/assets/export', [AssetImportController::class, 'export'])->name('imports.assets.export');
        Route::get('imports/assets/template', [AssetImportController::class, 'template'])->name('imports.assets.template');
        Route::post('imports/assets/preview', [AssetImportController::class, 'preview'])->name('imports.assets.preview');
        Route::get('imports/assets/{import}/original', [AssetImportController::class, 'original'])->name('imports.assets.original');
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
        Route::put('repairs/{repair}/evidences/{evidence}', [RepairController::class, 'replaceEvidence'])->name('repairs.evidences.replace');
        Route::post('repairs/{repair}/complete', [RepairController::class, 'complete'])->name('repairs.complete');
        Route::post('repairs/{repair}/cancel', [RepairController::class, 'cancel'])->name('repairs.cancel');
    });
});
