<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\Category;
use App\Models\Product;
use App\Services\AuditService;
use App\Services\InventoryMovementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ProductController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('consultar-inventario');

        $term = trim((string) $request->query('q', ''));
        $products = Product::query()->with('categoria')
            ->when($term !== '', fn ($query) => $query->whereAny(['codigo_interno', 'numero_parte', 'nombre', 'marca', 'modelo'], 'like', "%{$term}%"))
            ->when($term === '', fn ($query) => $query->whereRaw('1 = 0'))
            ->orderBy('nombre')->paginate(20)->withQueryString();

        $assetMatch = $term === '' ? null : Asset::query()
            ->with(['type', 'status'])
            ->where(fn ($query) => $query->where('activo_fijo', $term)
                ->orWhere('numero_serie', $term)
                ->orWhereIn('codigo_escaneo_id', DB::table('codigos_escaneo')->where('codigo', $term)->select('id')))
            ->first();

        return view('products.index', compact('products', 'term', 'assetMatch'));
    }

    public function create(Request $request): View
    {
        Gate::authorize('gestionar-inventario');

        $scanCode = $this->scanCode($request);
        $scanCode ? $request->session()->put('pending_scan.product', $scanCode) : $request->session()->forget('pending_scan.product');

        return view('products.form', ['product' => new Product, 'categories' => $this->categories(), 'warehouses' => $this->warehouses(), 'stockTotal' => 0, 'scanCode' => $scanCode]);
    }

    public function show(Product $product): View
    {
        Gate::authorize('consultar-inventario');

        return view('products.show', compact('product'));
    }

    public function store(Request $request, AuditService $audit, InventoryMovementService $movements): RedirectResponse
    {
        Gate::authorize('gestionar-inventario');
        $product = DB::transaction(function () use ($request, $movements) {
            $data = $this->data($request);
            $initialQuantity = (int) ($data['cantidad_inicial'] ?? 0);
            $initialWarehouse = $data['bodega_inicial_id'] ?? null;
            unset($data['cantidad_inicial'], $data['bodega_inicial_id']);

            $product = Product::query()->create($data + ['activo' => true, 'codigo_interno' => 'PRD-'.strtoupper((string) Str::ulid()), 'codigo_escaneo_id' => $this->scanCodeId($request->session()->pull('pending_scan.product')), 'creado_por' => $request->user()->id]);

            if ($initialQuantity > 0) {
                $movements->createAndPublish([
                    'item_type' => 'producto',
                    'tipo' => 'entrada',
                    'producto_id' => $product->id,
                    'cantidad' => $initialQuantity,
                    'origen_id' => null,
                    'destino_id' => $initialWarehouse,
                    'observacion' => 'Stock inicial registrado al crear el producto.',
                    'idempotency_key' => (string) Str::uuid(),
                ], $request->user());
            }

            return $product;
        });
        $audit->record($request->user(), 'crear', 'producto', $product->id, after: $product->toArray());

        return redirect()->route('products.index')->with('success', 'Producto creado.');
    }

    public function edit(Product $product): View
    {
        Gate::authorize('gestionar-inventario');

        return view('products.form', ['product' => $product, 'categories' => $this->categories(), 'warehouses' => $this->warehouses(), 'stockTotal' => $product->existencias()->sum('cantidad')]);
    }

    public function update(Request $request, Product $product, AuditService $audit): RedirectResponse
    {
        Gate::authorize('gestionar-inventario');
        $before = $product->toArray();
        $product->update($this->data($request));
        $audit->record($request->user(), 'actualizar', 'producto', $product->id, $before, $product->fresh()->toArray());

        return redirect()->route('products.index')->with('success', 'Producto actualizado.');
    }

    public function destroy(Request $request, Product $product, AuditService $audit): RedirectResponse
    {
        Gate::authorize('desactivar-productos');
        if ($product->existencias()->where('cantidad', '>', 0)->exists()) {
            return back()->withErrors(['producto' => 'No se puede desactivar un producto con stock disponible.']);
        }
        $before = $product->toArray();
        $product->update(['activo' => false]);
        $audit->record($request->user(), 'desactivar', 'producto', $product->id, $before, $product->fresh()->toArray());

        return redirect()->route('products.index')->with('success', 'Producto desactivado; su historial se conserva.');
    }

    private function data(Request $request): array
    {
        return $request->validate(['categoria_id' => ['required', Rule::exists('categorias', 'id')->where('activo', true)], 'numero_parte' => ['required', 'string', 'max:40', 'regex:/^[A-Za-z0-9-]+$/'], 'nombre' => ['required', 'string', 'max:255', 'regex:/^[\pL\pN .,_()\/-]+$/u'], 'marca' => ['nullable', 'string', 'max:80', 'regex:/^[\pL\pN .,_()\/-]+$/u'], 'modelo' => ['nullable', 'string', 'max:80', 'regex:/^[\pL\pN .,_()\/-]+$/u'], 'descripcion' => ['nullable', 'string', 'max:2000'], 'costo_neto_actual' => ['required', 'numeric', 'min:0'], 'cantidad_inicial' => ['nullable', 'integer', 'min:0', 'max:100000'], 'bodega_inicial_id' => [Rule::requiredIf(fn () => (int) $request->input('cantidad_inicial', 0) > 0), 'nullable', Rule::exists('ubicaciones', 'id')->where(fn ($query) => $query->where('tipo', 'bodega')->where('activo', true))]]);
    }

    private function categories()
    {
        return Category::query()->where('activo', true)
            ->orderByRaw('CASE WHEN nombre = ? THEN 1 ELSE 0 END', ['Otros'])
            ->orderBy('nombre')
            ->get();
    }

    private function warehouses()
    {
        return DB::table('ubicaciones')->where('tipo', 'bodega')->where('activo', true)->orderBy('nombre')->get(['id', 'codigo', 'nombre']);
    }

    private function scanCode(Request $request): ?string
    {
        $code = $request->query('codigo');
        abort_unless($code === null || preg_match('/^[A-Za-z0-9-]{1,40}$/', $code), 422, 'Código de escaneo inválido.');

        return $code;
    }

    private function scanCodeId(?string $code): ?int
    {
        if (! $code) {
            return null;
        }
        $record = DB::table('codigos_escaneo')->where('codigo', $code)->lockForUpdate()->first();
        if ($record && (Product::query()->where('codigo_escaneo_id', $record->id)->exists() || DB::table('activos')->where('codigo_escaneo_id', $record->id)->exists())) {
            abort(422, 'El código escaneado ya está asociado a otro registro.');
        }

        return $record?->id ?? DB::table('codigos_escaneo')->insertGetId(['codigo' => $code, 'activo' => true, 'created_at' => now(), 'updated_at' => now()]);
    }
}
