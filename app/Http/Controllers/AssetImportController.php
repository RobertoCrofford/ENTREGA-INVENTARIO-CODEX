<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Services\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class AssetImportController extends Controller
{
    private const HEADERS = ['activo_fijo', 'sede_codigo', 'tipo_codigo', 'estado_codigo', 'uso', 'ubicacion_codigo', 'numero_serie', 'marca', 'modelo', 'costo_neto', 'responsable_nombre', 'responsable_email', 'responsable_departamento', 'observacion'];

    public function index(): View
    {
        Gate::authorize('gestionar-inventario');

        $imports = DB::table('importaciones')
            ->join('users', 'users.id', '=', 'importaciones.ejecutado_por')
            ->where('tipo', 'activos')
            ->orderByDesc('importaciones.id')
            ->limit(10)
            ->get(['importaciones.*', 'users.username']);

        return view('imports.assets', compact('imports'));
    }

    public function template()
    {
        Gate::authorize('gestionar-inventario');

        $stream = fopen('php://temp', 'r+');
        fputcsv($stream, self::HEADERS);
        fputcsv($stream, ['AF-2026-001', 'MAIPU', 'PC', 'OPERATIVO', 'alumnos', 'SALA-MAIPU', 'SN-AB12-3456', 'Lenovo', 'ThinkCentre M70', '450000', '', '', '', 'Ejemplo: eliminar esta fila antes de importar.']);
        rewind($stream);
        $contents = stream_get_contents($stream);
        fclose($stream);

        return response($contents, 200, ['Content-Type' => 'text/csv; charset=UTF-8', 'Content-Disposition' => 'attachment; filename="plantilla-activos.csv"']);
    }

    public function preview(Request $request): RedirectResponse
    {
        Gate::authorize('gestionar-inventario');
        $request->validate(['archivo' => ['required', 'file', 'max:5120', 'mimetypes:text/plain,text/csv,application/csv,application/vnd.ms-excel']]);
        $file = $request->file('archivo');
        $hash = hash_file('sha256', $file->getRealPath());

        if (DB::table('importaciones')->where('tipo', 'activos')->where('archivo_sha256', $hash)->whereIn('estado', ['lista', 'procesando', 'completada'])->exists()) {
            return back()->with('warning', 'Este archivo ya fue revisado o importado. Revisa el historial para evitar duplicados.');
        }

        $path = 'importaciones/'.$hash.'.csv';
        Storage::disk('local')->put($path, file_get_contents($file->getRealPath()));
        $rows = $this->readRows(Storage::disk('local')->path($path));
        $importId = DB::table('importaciones')->insertGetId([
            'tipo' => 'activos', 'archivo_nombre' => $file->getClientOriginalName(), 'archivo_sha256' => $hash,
            'estado' => 'validando', 'filas_total' => count($rows), 'filas_exitosas' => 0, 'filas_error' => 0,
            'ejecutado_por' => $request->user()->id, 'iniciado_at' => now(),
        ]);

        $errors = $this->validateRows($rows);
        foreach ($errors as $error) {
            DB::table('errores_importacion')->insert(['importacion_id' => $importId] + $error);
        }
        $invalidRows = collect($errors)->pluck('fila')->unique()->count();
        DB::table('importaciones')->where('id', $importId)->update([
            'estado' => 'lista', 'filas_exitosas' => count($rows) - $invalidRows, 'filas_error' => $invalidRows, 'finalizado_at' => now(),
        ]);

        return redirect()->route('imports.assets.show', $importId);
    }

    public function show(int $import): View
    {
        Gate::authorize('gestionar-inventario');
        $import = DB::table('importaciones')->where('id', $import)->where('tipo', 'activos')->firstOrFail();
        $errors = DB::table('errores_importacion')->where('importacion_id', $import->id)->orderBy('fila')->get();

        return view('imports.asset-preview', compact('import', 'errors'));
    }

    public function confirm(Request $request, int $import, AuditService $audit): RedirectResponse
    {
        Gate::authorize('gestionar-inventario');
        $import = DB::table('importaciones')->where('id', $import)->where('tipo', 'activos')->lockForUpdate()->firstOrFail();
        abort_unless($import->estado === 'lista', 422, 'Esta importación ya fue procesada.');
        $path = 'importaciones/'.$import->archivo_sha256.'.csv';
        abort_unless(Storage::disk('local')->exists($path), 422, 'No se encontró el archivo de importación.');

        $rows = $this->readRows(Storage::disk('local')->path($path));
        $errors = $this->validateRows($rows);
        $invalid = collect($errors)->pluck('fila')->unique()->flip();
        DB::transaction(function () use ($import, $rows, $invalid, $request, $audit) {
            DB::table('importaciones')->where('id', $import->id)->update(['estado' => 'procesando']);
            foreach ($rows as $row) {
                if ($invalid->has($row['_fila'])) {
                    continue;
                }
                $asset = Asset::create($this->assetData($row, $request->user()->id));
                DB::table('eventos_activo')->insert(['activo_id' => $asset->id, 'tipo' => 'alta', 'estado_destino_id' => $asset->estado_activo_id, 'ubicacion_destino_id' => $asset->ubicacion_actual_id, 'motivo' => 'Importación masiva', 'ejecutado_por' => $request->user()->id, 'ocurrido_at' => now()]);
                $audit->record($request->user(), 'importar', 'activo', $asset->id, after: $asset->toArray(), reason: 'Importación #'.$import->id, notify: false);
            }
            DB::table('importaciones')->where('id', $import->id)->update(['estado' => 'completada', 'finalizado_at' => now()]);
        });

        return redirect()->route('imports.assets.show', $import->id)->with('success', 'Importación completada.');
    }

    public function rejected(int $import)
    {
        Gate::authorize('gestionar-inventario');
        $import = DB::table('importaciones')->where('id', $import)->where('tipo', 'activos')->firstOrFail();
        $errors = DB::table('errores_importacion')->where('importacion_id', $import->id)->orderBy('fila')->get();
        $stream = fopen('php://temp', 'r+');
        fputcsv($stream, ['fila', 'campo', 'codigo_error', 'mensaje', 'datos']);
        foreach ($errors as $error) {
            fputcsv($stream, [$error->fila, $error->campo, $error->codigo_error, $error->mensaje, $error->datos_json]);
        }
        rewind($stream);
        $contents = stream_get_contents($stream);
        fclose($stream);

        return response($contents, 200, ['Content-Type' => 'text/csv; charset=UTF-8', 'Content-Disposition' => 'attachment; filename="importacion-activos-'.$import->id.'-rechazados.csv"']);
    }

    private function readRows(string $path): array
    {
        $handle = fopen($path, 'r');
        $headers = fgetcsv($handle) ?: [];
        $headers[0] = preg_replace('/^\xEF\xBB\xBF/', '', $headers[0] ?? '');
        abort_unless($headers === self::HEADERS, 422, 'La cabecera no coincide con la plantilla de activos.');
        $rows = [];
        $line = 1;
        while (($values = fgetcsv($handle)) !== false) {
            $line++;
            if ($values === [null] || $values === []) {
                continue;
            }
            $rows[] = array_combine(self::HEADERS, array_pad($values, count(self::HEADERS), '')) + ['_fila' => $line];
        }
        fclose($handle);

        return $rows;
    }

    private function validateRows(array $rows): array
    {
        $errors = [];
        $seen = [];
        $sites = DB::table('sedes')->pluck('id', 'codigo');
        $types = DB::table('tipos_activo')->where('activo', true)->pluck('id', 'codigo');
        $statuses = DB::table('estados_activo')->where('activo', true)->pluck('id', 'codigo');
        $locations = DB::table('ubicaciones')->where('activo', true)->get()->keyBy('codigo');
        $existing = Asset::query()->pluck('id', 'activo_fijo');
        foreach ($rows as $row) {
            $add = function (string $field, string $code, string $message) use (&$errors, $row): void {
                $errors[] = ['fila' => $row['_fila'], 'campo' => $field, 'codigo_error' => $code, 'mensaje' => $message, 'datos_json' => json_encode(collect($row)->except('_fila')->all())];
            };
            $fixed = trim($row['activo_fijo']);
            if (! preg_match('/^[A-Za-z0-9-]{1,40}$/', $fixed)) {
                $add('activo_fijo', 'formato', 'El activo fijo solo admite letras, números y guiones.');
            }
            if (isset($seen[$fixed])) {
                $add('activo_fijo', 'duplicado_archivo', 'El activo fijo está repetido en el archivo.');
            }
            $seen[$fixed] = true;
            if ($existing->has($fixed)) {
                $add('activo_fijo', 'duplicado_base', 'El activo fijo ya existe.');
            }
            if (! $sites->has(trim($row['sede_codigo']))) {
                $add('sede_codigo', 'sede_invalida', 'La sede no existe o está inactiva.');
            }
            if (! $types->has(strtolower(trim($row['tipo_codigo'])))) {
                $add('tipo_codigo', 'tipo_invalido', 'El tipo de activo no existe o está inactivo.');
            }
            if (! $statuses->has(strtolower(trim($row['estado_codigo'])))) {
                $add('estado_codigo', 'estado_invalido', 'El estado no existe o está inactivo.');
            }
            if (! in_array(strtolower(trim($row['uso'])), ['administrativo', 'alumnos', 'docente', 'comun'], true)) {
                $add('uso', 'uso_invalido', 'El uso debe ser administrativo, alumnos, docente o comun.');
            }
            $locationCode = trim($row['ubicacion_codigo']);
            if ($locationCode !== '' && (! $locations->has($locationCode) || ($sites->get(trim($row['sede_codigo'])) && $locations->get($locationCode)->sede_id !== $sites->get(trim($row['sede_codigo']))))) {
                $add('ubicacion_codigo', 'ubicacion_invalida', 'La ubicación no existe, está inactiva o pertenece a otra sede.');
            }
            if ($locationCode !== '' && (trim($row['responsable_nombre']) !== '' || trim($row['responsable_email']) !== '')) {
                $add('ubicacion_codigo', 'destino_duplicado', 'No combines ubicación con responsable.');
            }
            if ((trim($row['responsable_nombre']) === '') xor (trim($row['responsable_email']) === '')) {
                $add('responsable', 'responsable_incompleto', 'El responsable exige nombre y correo.');
            }
            if ($row['responsable_email'] !== '' && ! filter_var(trim($row['responsable_email']), FILTER_VALIDATE_EMAIL)) {
                $add('responsable_email', 'correo_invalido', 'El correo del responsable no es válido.');
            }
            if (trim($row['costo_neto']) !== '' && (! is_numeric($row['costo_neto']) || (float) $row['costo_neto'] < 0)) {
                $add('costo_neto', 'costo_invalido', 'El costo debe ser numérico y no negativo.');
            }
        }

        return $errors;
    }

    private function assetData(array $row, int $userId): array
    {
        $siteId = DB::table('sedes')->where('codigo', trim($row['sede_codigo']))->value('id');
        $typeId = DB::table('tipos_activo')->where('codigo', strtolower(trim($row['tipo_codigo'])))->value('id');
        $statusId = DB::table('estados_activo')->where('codigo', strtolower(trim($row['estado_codigo'])))->value('id');
        $locationId = trim($row['ubicacion_codigo']) === '' ? null : DB::table('ubicaciones')->where('codigo', trim($row['ubicacion_codigo']))->value('id');

        return ['sede_id' => $siteId, 'tipo_activo_id' => $typeId, 'estado_activo_id' => $statusId, 'uso' => strtolower(trim($row['uso'])), 'ubicacion_actual_id' => $locationId, 'activo_fijo' => trim($row['activo_fijo']), 'numero_serie' => trim($row['numero_serie']) ?: null, 'marca' => trim($row['marca']) ?: null, 'modelo' => trim($row['modelo']) ?: null, 'costo_neto_actual' => trim($row['costo_neto']) ?: null, 'responsable_nombre' => trim($row['responsable_nombre']) ?: null, 'responsable_email' => trim($row['responsable_email']) ?: null, 'responsable_departamento' => trim($row['responsable_departamento']) ?: null, 'observacion' => trim($row['observacion']) ?: null, 'creado_por' => $userId];
    }
}
