<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\Product;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class ScanController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('consultar-inventario');

        $code = $request->query('codigo');
        if ($code !== null) {
            abort_unless(preg_match('/^[A-Za-z0-9-]{1,40}$/', $code), 422, 'Código de escaneo inválido.');

            return $this->result($request, $code, false);
        }

        return view('scan.index');
    }

    public function search(Request $request): View
    {
        Gate::authorize('consultar-inventario');
        $data = $request->validate(['codigo' => ['required', 'string', 'max:40', 'regex:/^[A-Za-z0-9-]+$/']]);
        return $this->result($request, trim($data['codigo']), true);
    }

    private function result(Request $request, string $code, bool $notifyGuest): View
    {
        $codeId = DB::table('codigos_escaneo')->where('codigo', $code)->value('id');
        $product = Product::query()->with('categoria')->where(function ($query) use ($codeId, $code) {
            if ($codeId) {
                $query->where('codigo_escaneo_id', $codeId);
            }
            $query->orWhere('codigo_interno', $code)->orWhere('numero_parte', $code);
        })->first();
        $asset = Asset::query()->with(['type', 'status'])->where(function ($query) use ($codeId, $code) {
            if ($codeId) {
                $query->where('codigo_escaneo_id', $codeId);
            }
            $query->orWhere('activo_fijo', $code)->orWhere('numero_serie', $code);
        })->first();
        if ($notifyGuest && ! $product && ! $asset && $request->user()->tieneRol(Role::INVITADO)) {
            $message = "El usuario invitado {$request->user()->name} escaneó el código {$code}, sin coincidencias. Revisa y registra el producto o activo si corresponde.";
            $recipientIds = DB::table('users')->join('roles', 'roles.id', '=', 'users.rol_id')
                ->where('users.activo', true)
                ->whereIn('roles.codigo', [Role::TECNICO, Role::DIRECTOR_TECNICO, Role::SUPERADMIN])
                ->pluck('users.id');
            foreach ($recipientIds as $recipientId) {
                $alreadyNotified = DB::table('notificaciones')->where('usuario_id', $recipientId)
                    ->where('titulo', 'Código sin registrar')->where('mensaje', $message)
                    ->where('creado_at', '>=', now()->subHour())->exists();
                if (! $alreadyNotified) {
                    DB::table('notificaciones')->insert(['usuario_id' => $recipientId, 'origen_usuario_id' => $request->user()->id, 'titulo' => 'Código sin registrar', 'mensaje' => $message, 'url' => route('scan.index', ['codigo' => $code]), 'creado_at' => now()]);
                }
            }
        }

        return view('scan.index', [
            'codigo' => $code,
            'product' => $product,
            'asset' => $asset,
        ]);
    }
}
