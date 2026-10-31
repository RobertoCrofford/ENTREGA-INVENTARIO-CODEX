<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\Product;
use App\Services\InventoryMovementService;
use App\Support\AssetCodeMatch;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class InventoryMovementController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('consultar-inventario');

        $matchingCodeIds = $request->filled('q') ? AssetCodeMatch::assetIds((string) $request->q) : [];
        $movements = DB::table('movimientos_inventario as movimiento')
            ->join('movimientos_detalle as detalle', 'detalle.movimiento_id', '=', 'movimiento.id')
            ->leftJoin('productos as producto', 'producto.id', '=', 'detalle.producto_id')
            ->leftJoin('activos as activo', 'activo.id', '=', 'detalle.activo_id')
            ->join('users as creador', 'creador.id', '=', 'movimiento.creado_por')
            ->leftJoin('users as publicador', 'publicador.id', '=', 'movimiento.publicado_por')
            ->when($request->q, fn ($query, $term) => $query->where(fn ($matches) => $matches->whereAny(['movimiento.folio', 'producto.codigo_interno', 'producto.nombre', 'activo.activo_fijo', 'activo.numero_serie', 'activo.marca', 'activo.modelo', 'movimiento.motivo', 'creador.name'], 'like', "%{$term}%")
                ->orWhereIn('activo.id', $matchingCodeIds)))
            ->selectRaw("movimiento.id, movimiento.folio, movimiento.tipo, movimiento.estado, movimiento.motivo, movimiento.receptor_tipo, movimiento.receptor_nombre, movimiento.receptor_cargo, movimiento.receptor_departamento, movimiento.receptor_confirmado_at, movimiento.confirmacion_observacion, movimiento.publicado_at, movimiento.created_at, COALESCE(producto.codigo_interno, activo.activo_fijo) as item_codigo, COALESCE(producto.nombre, CONCAT_WS(' ', activo.marca, activo.modelo)) as item_nombre, detalle.cantidad, creador.name as creado_por_nombre, publicador.name as publicado_por_nombre")
            ->orderByDesc('movimiento.id')->paginate(20)->withQueryString();

        return view('movements.index', compact('movements'));
    }

    public function create(Request $request): View
    {
        Gate::authorize('gestionar-inventario');

        $products = Product::query()->with('categoria')->where('activo', true)->orderBy('nombre')->get();
        $assets = Asset::query()->with(['type', 'location'])->whereHas('status', fn ($query) => $query->where('codigo', '!=', 'dado_baja'))->orderBy('activo_fijo')->get();

        return view('movements.form', [
            'products' => $products,
            'assets' => $assets,
            'defaultItemType' => $products->isEmpty() && $assets->isNotEmpty() ? 'activo' : 'producto',
            'defaultProductId' => $request->integer('producto') && $products->contains('id', $request->integer('producto')) ? $request->integer('producto') : null,
            'defaultMovementType' => in_array($request->query('tipo'), ['entrada', 'salida'], true) ? $request->query('tipo') : null,
            'locations' => DB::table('ubicaciones')->where('activo', true)->orderBy('tipo')->orderBy('nombre')->get(),
        ]);
    }

    public function groupCreate(): View
    {
        Gate::authorize('gestionar-inventario');
        return view('movements.group-form', [
            'products' => Product::query()->with('categoria')->where('activo', true)->orderBy('nombre')->get(),
            'assets' => Asset::query()->with('location')->whereHas('status', fn ($q) => $q->where('codigo', '!=', 'dado_baja'))->orderBy('activo_fijo')->get(),
            'locations' => DB::table('ubicaciones')->where('activo', true)->orderBy('nombre')->get(),
        ]);
    }

    public function groupStore(Request $request, InventoryMovementService $service): RedirectResponse
    {
        Gate::authorize('gestionar-inventario');
        $data = $request->validate([
            'origen_id' => ['required', 'exists:ubicaciones,id'], 'destino_id' => ['required', 'different:origen_id', 'exists:ubicaciones,id'],
            'receptor_nombre' => ['nullable', 'string', 'max:120'], 'receptor_email' => ['nullable', 'email', 'max:255'],
            'receptor_departamento' => ['nullable', 'string', 'max:120'], 'observacion' => ['nullable', 'string', 'max:2000'],
            'items_json' => ['required', 'string', 'max:20000'],
        ]);
        $items = json_decode($data['items_json'], true);
        if (! is_array($items) || $items === []) return back()->withErrors(['items_json' => 'Agrega al menos un producto o activo al carrito.'])->withInput();
        $id = $service->createGroupTransfer($data, $items, $request->user());
        DB::table('borradores_movimiento_grupal')->where('user_id', $request->user()->id)->delete();
        return redirect()->route('movements.index')->with('success', "Movimiento grupal MOV-".now()->format('Y')."-{$id} publicado.");
    }

    public function groupDraft(Request $request): JsonResponse
    {
        Gate::authorize('gestionar-inventario');
        $draft = DB::table('borradores_movimiento_grupal')->where('user_id', $request->user()->id)->value('contenido');

        return response()->json(['draft' => $draft ? json_decode($draft, true) : null]);
    }

    public function saveGroupDraft(Request $request): JsonResponse
    {
        Gate::authorize('gestionar-inventario');
        $data = $request->validate([
            'draft' => ['nullable', 'array'],
            'draft.items' => ['nullable', 'array', 'max:100'],
            'draft.values' => ['nullable', 'array'],
        ]);
        $content = $data['draft'] ?? ['items' => [], 'values' => []];
        DB::table('borradores_movimiento_grupal')->updateOrInsert(
            ['user_id' => $request->user()->id],
            ['contenido' => json_encode($content), 'updated_at' => now(), 'created_at' => now()]
        );

        return response()->json(['saved' => true]);
    }

    public function store(Request $request, InventoryMovementService $service): RedirectResponse
    {
        Gate::authorize('gestionar-inventario');
        $data = $request->validate(['item_type' => ['required', Rule::in(['producto', 'activo'])], 'tipo' => ['required', Rule::in(['entrada', 'salida', 'devolucion', 'traslado'])], 'producto_id' => ['nullable', 'exists:productos,id'], 'activo_id' => ['nullable', 'exists:activos,id'], 'cantidad' => ['required', 'integer', 'min:1', 'max:100000'], 'origen_id' => ['nullable', 'exists:ubicaciones,id'], 'destino_id' => ['nullable', 'exists:ubicaciones,id'], 'receptor_tipo' => ['nullable', Rule::in(['persona', 'area', 'otro'])], 'receptor_nombre' => ['nullable', 'string', 'max:120'], 'receptor_cargo' => ['nullable', 'string', 'max:120'], 'receptor_email' => ['nullable', 'email', 'max:255'], 'receptor_departamento' => ['nullable', 'string', 'max:120'], 'confirmar_recepcion' => ['nullable', 'boolean'], 'confirmacion_observacion' => ['nullable', 'string', 'max:1000'], 'observacion' => ['nullable', 'string', 'max:2000'], 'idempotency_key' => ['required', 'uuid']]);
        $id = $service->createAndPublish($data, $request->user());

        return redirect()->route('movements.index')->with('success', "Movimiento #{$id} publicado.");
    }

    public function confirmReceipt(Request $request, int $movement, InventoryMovementService $service): RedirectResponse
    {
        Gate::authorize('gestionar-inventario');
        $data = $request->validate(['confirmacion_observacion' => ['nullable', 'string', 'max:1000']]);
        $service->confirmReceipt($movement, $request->user(), $data['confirmacion_observacion'] ?? null);

        return back()->with('success', 'Recepción manual registrada por el técnico.');
    }
}
