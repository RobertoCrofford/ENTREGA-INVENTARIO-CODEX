<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class ScanController extends Controller
{
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
        $product = Product::query()->with('categoria')->where(function ($query) use ($codeId, $code) {
            if ($codeId) {
                $query->where('codigo_escaneo_id', $codeId);
            }
            $query->orWhere('codigo_interno', $code);
        })->first();
        $asset = Asset::query()->with(['type', 'status', 'location'])->where(function ($query) use ($codeId, $code) {
            if ($codeId) {
                $query->where('codigo_escaneo_id', $codeId);
            }
            $query->orWhere('activo_fijo', $code)
                ->orWhere('numero_serie', $code);
        })->first();
        $ambiguous = $product && $asset;
        return view('scan.index', [
            'codigo' => $code,
            'product' => $product,
            'asset' => $asset,
            'ambiguous' => $ambiguous,
        ]);
    }
}
