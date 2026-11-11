<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\Product;
use App\Support\AssetCodeMatch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class ScanController extends Controller
{
    public function mobile(): View
    {
        Gate::authorize('escanear-inventario');

        return view('mobile.scan.index');
    }

    public function mobileLookup(Request $request): JsonResponse
    {
        Gate::authorize('escanear-inventario');
        $data = $request->validate(['codigo' => ['required', 'string', 'max:40', 'regex:/^[A-Za-z0-9-]+$/']]);
        $code = trim($data['codigo']);
        $codeId = DB::table('codigos_escaneo')->where('codigo', $code)->value('id');
        $matchingCodeIds = AssetCodeMatch::assetIds($code);
        $assets = Asset::query()->with(['type', 'status', 'location'])->where(function ($query) use ($codeId, $code, $matchingCodeIds) {
            if ($codeId) {
                $query->where('codigo_escaneo_id', $codeId);
            }
            $query->orWhere('activo_fijo', $code)
                ->orWhere('numero_serie', $code)
                ->orWhereIn('id', $matchingCodeIds);
        })->get();

        if ($assets->isEmpty()) {
            return response()->json([
                'status' => 'not_found',
                'message' => "No existe un equipo asociado al código {$code}.",
            ], 404);
        }

        if ($assets->count() > 1) {
            return response()->json([
                'status' => 'duplicate',
                'message' => 'El código está asociado a más de un equipo. Un administrador debe corregir la duplicidad.',
            ], 409);
        }

        $asset = $assets->first();
        $location = $asset->location;
        $usage = [
            'administrativo' => 'Administrativo',
            'alumnos' => 'Alumnos',
            'docente' => 'Docente',
            'comun' => 'Uso común',
            'sin_definir' => 'Sin definir',
        ][$asset->uso] ?? 'Sin definir';

        return response()->json([
            'status' => 'found',
            'message' => $location
                ? "Equipo encontrado en {$location->nombre}."
                : 'Equipo encontrado, pero no tiene una sala asignada.',
            'asset' => [
                'id' => $asset->id,
                'activo_fijo' => $asset->activo_fijo,
                'numero_serie' => $asset->numero_serie,
                'tipo' => $asset->type?->nombre ?: 'No registrado',
                'estado' => $asset->status?->nombre ?: 'No registrado',
                'uso' => $usage,
                'marca_modelo' => trim(($asset->marca ?: '').' '.($asset->modelo ?: '')) ?: 'No registrado',
                'ubicacion' => $location ? [
                    'codigo' => $location->codigo,
                    'nombre' => $location->nombre,
                    'tipo' => $location->tipo,
                    'edificio' => $location->edificio,
                    'piso' => $location->piso,
                ] : null,
                'responsable' => $asset->responsable_nombre ?: 'Sin responsable',
                'departamento' => $asset->responsable_departamento ?: 'No registrado',
                'observacion' => $asset->observacion ?: 'Sin observaciones',
                'url' => route('assets.show', $asset),
            ],
        ]);
    }

    public function index(Request $request): View
    {
        Gate::authorize('escanear-inventario');

        $code = $request->query('codigo');
        if ($code !== null) {
            abort_unless(preg_match('/^[A-Za-z0-9-]{1,40}$/', $code), 422, 'Código de escaneo inválido.');

            return $this->result($code);
        }

        return view('scan.index');
    }

    public function search(Request $request): View
    {
        Gate::authorize('escanear-inventario');
        $data = $request->validate(['codigo' => ['required', 'string', 'max:40', 'regex:/^[A-Za-z0-9-]+$/']]);

        return $this->result(trim($data['codigo']));
    }

    private function result(string $code): View
    {
        $codeId = DB::table('codigos_escaneo')->where('codigo', $code)->value('id');
        $matchingCodeIds = AssetCodeMatch::assetIds($code);
        $product = Product::query()->with('categoria')->where(function ($query) use ($codeId, $code) {
            if ($codeId) {
                $query->where('codigo_escaneo_id', $codeId);
            }
            $query->orWhere('codigo_interno', $code);
        })->first();
        $assets = Asset::query()->with(['type', 'status', 'location'])->where(function ($query) use ($codeId, $code, $matchingCodeIds) {
            if ($codeId) {
                $query->where('codigo_escaneo_id', $codeId);
            }
            $query->orWhere('activo_fijo', $code)
                ->orWhere('numero_serie', $code)
                ->orWhereIn('id', $matchingCodeIds);
        })->get();
        $asset = $assets->count() === 1 ? $assets->first() : null;
        $ambiguous = ($product && $asset) || $assets->count() > 1;
        return view('scan.index', [
            'codigo' => $code,
            'product' => $product,
            'asset' => $asset,
            'ambiguous' => $ambiguous,
        ]);
    }
}
