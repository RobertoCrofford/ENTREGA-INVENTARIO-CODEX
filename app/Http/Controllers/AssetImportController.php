<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\Role;
use App\Services\AuditService;
use App\Support\AssetCodeMatch;
use DOMDocument;
use DOMXPath;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use ZipArchive;

class AssetImportController extends Controller
{
    private const MAX_XLSX_ENTRIES = 100;

    private const MAX_XLSX_UNCOMPRESSED_BYTES = 100 * 1024 * 1024;

    private const MAX_XLSX_XML_BYTES = 50 * 1024 * 1024;

    private const HEADERS = ['activo_fijo', 'sede_codigo', 'tipo_codigo', 'estado_codigo', 'uso', 'ubicacion_codigo', 'numero_serie', 'marca', 'modelo', 'costo_neto', 'responsable_nombre', 'responsable_email', 'responsable_departamento', 'observacion'];

    private const AUDIT_HEADERS = ['Activofijo', 'Numerodeserie', 'ubicacion', 'Denominaciondelactivofijo', 'Ce.coste', 'Cantidad', 'Fe.capit.', 'ValorNeto', 'VidaRestante', 'Encargado'];

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

    public function export()
    {
        Gate::authorize('gestionar-inventario');
        abort_unless(class_exists(ZipArchive::class), 422, 'El servidor no tiene habilitada la generación de archivos Excel.');
        $path = tempnam(sys_get_temp_dir(), 'activos-');
        $zip = new ZipArchive;
        abort_unless($zip->open($path, ZipArchive::OVERWRITE) === true, 500, 'No se pudo generar el archivo Excel.');

        $headers = ['Activo fijo', 'Numero de serie', 'ubicación', 'Denominación del activo fijo', 'Ce.coste', 'Cantidad', 'Fe.capit.', 'Valor Neto', 'Vida Restante', 'Encargado'];
        $rows = [$this->xlsxRow(1, $headers)];
        $assets = Asset::query()->with('location')->orderBy('activo_fijo')->cursor();
        $rowNumber = 2;
        foreach ($assets as $asset) {
            $rows[] = $this->xlsxRow($rowNumber++, [
                $asset->activo_fijo,
                $asset->numero_serie,
                $asset->location?->nombre,
                $asset->modelo,
                $this->observationValue($asset->observacion, 'Centro de costo'),
                1,
                $this->observationValue($asset->observacion, 'Fecha de capitalización'),
                $asset->costo_neto_actual,
                $this->observationValue($asset->observacion, 'Vida restante'),
                $asset->responsable_nombre ?: $this->observationValue($asset->observacion, 'Encargado'),
            ]);
        }
        $sheet = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>'.implode('', $rows).'</sheetData></worksheet>';
        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/></Types>');
        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');
        $zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="activos" sheetId="1" r:id="rId1"/></sheets></workbook>');
        $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/></Relationships>');
        $zip->addFromString('xl/worksheets/sheet1.xml', $sheet);
        $zip->close();

        return response()->download($path, 'activos-auditoria-'.now()->format('Y-m-d').'.xlsx', ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'])->deleteFileAfterSend(true);
    }

    public function preview(Request $request): RedirectResponse
    {
        Gate::authorize('gestionar-inventario');
        $request->validate(['archivo' => ['required', 'file', 'max:20480', 'mimetypes:text/plain,text/csv,application/csv,application/vnd.ms-excel,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']]);
        $file = $request->file('archivo');
        $extension = strtolower($file->getClientOriginalExtension());
        abort_unless(in_array($extension, ['csv', 'xlsx'], true), 422, 'Solo se admiten archivos CSV o Excel (.xlsx).');
        $hash = hash_file('sha256', $file->getRealPath());

        if (DB::table('importaciones')->where('tipo', 'activos')->where('archivo_sha256', $hash)->whereIn('estado', ['lista', 'procesando', 'completada'])->exists()) {
            return back()->with('warning', 'Este archivo ya fue revisado o importado. Revisa el historial para evitar duplicados.');
        }

        $path = 'importaciones/'.$hash.'.'.$extension;
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

    public function original(int $import)
    {
        Gate::authorize('gestionar-inventario');
        $import = DB::table('importaciones')->where('id', $import)->where('tipo', 'activos')->firstOrFail();
        $path = 'importaciones/'.$import->archivo_sha256.'.'.$this->extension($import->archivo_nombre);
        abort_unless(Storage::disk('local')->exists($path), 404, 'No se encontró el archivo original de la importación.');

        return Storage::disk('local')->download($path, $import->archivo_nombre);
    }

    public function confirm(Request $request, int $import, AuditService $audit): RedirectResponse
    {
        Gate::authorize('gestionar-inventario');
        $import = DB::table('importaciones')->where('id', $import)->where('tipo', 'activos')->firstOrFail();
        $path = 'importaciones/'.$import->archivo_sha256.'.'.$this->extension($import->archivo_nombre);
        abort_unless(Storage::disk('local')->exists($path), 422, 'No se encontró el archivo de importación.');

        $rows = $this->readRows(Storage::disk('local')->path($path));
        DB::transaction(function () use ($import, $rows, $request, $audit) {
            $import = DB::table('importaciones')->where('id', $import->id)->where('tipo', 'activos')->lockForUpdate()->firstOrFail();
            abort_unless($import->estado === 'lista', 422, 'Esta importación ya fue procesada.');
            $errors = $this->validateRows($rows);
            $invalid = collect($errors)->pluck('fila')->unique()->flip();
            DB::table('errores_importacion')->where('importacion_id', $import->id)->delete();
            foreach ($errors as $error) {
                DB::table('errores_importacion')->insert(['importacion_id' => $import->id] + $error);
            }
            DB::table('importaciones')->where('id', $import->id)->update(['estado' => 'procesando']);
            foreach ($rows as $row) {
                if ($invalid->has($row['_fila'])) {
                    continue;
                }
                $asset = Asset::create($this->assetData($row, $request->user()->id));
                DB::table('eventos_activo')->insert(['activo_id' => $asset->id, 'tipo' => 'alta', 'estado_destino_id' => $asset->estado_activo_id, 'ubicacion_destino_id' => $asset->ubicacion_actual_id, 'motivo' => 'Importación masiva', 'ejecutado_por' => $request->user()->id, 'ocurrido_at' => now()]);
                $audit->record($request->user(), 'importar', 'activo', $asset->id, after: $asset->toArray(), reason: 'Importación #'.$import->id, notify: false);
            }
            $invalidRows = $invalid->count();
            DB::table('importaciones')->where('id', $import->id)->update(['estado' => 'completada', 'filas_exitosas' => count($rows) - $invalidRows, 'filas_error' => $invalidRows, 'finalizado_at' => now()]);
        });

        $completedImport = DB::table('importaciones')->where('id', $import->id)->firstOrFail();
        $this->notifyCompletion($completedImport);

        return redirect()->route('imports.assets.show', $import->id)->with('success', 'Importación completada.');
    }

    private function notifyCompletion(object $import): void
    {
        $recipients = DB::table('users')
            ->join('roles', 'roles.id', '=', 'users.rol_id')
            ->where('users.activo', true)
            ->whereIn('roles.codigo', [Role::SUPERADMIN, Role::DIRECTOR_TECNICO, Role::TECNICO])
            ->pluck('users.id');

        if ($recipients->isEmpty()) {
            return;
        }

        $valid = (int) $import->filas_exitosas;
        $errors = (int) $import->filas_error;
        $message = "La importación {$import->archivo_nombre} finalizó con {$valid} activo(s) registrado(s).";
        if ($errors > 0) {
            $message .= " {$errors} fila(s) quedaron con error.";
        }
        $createdAt = now();
        $notifications = $recipients->map(fn (int $userId) => [
            'usuario_id' => $userId,
            'titulo' => 'Importación de activos completada',
            'mensaje' => $message,
            'url' => route('imports.assets.show', $import->id),
            'creado_at' => $createdAt,
        ])->all();

        DB::table('notificaciones')->insert($notifications);
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
        if ($this->extension($path) === 'xlsx') {
            return $this->readAuditWorkbook($path);
        }

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
            abort_unless(count($values) <= count(self::HEADERS), 422, "La fila {$line} tiene más columnas que la plantilla.");
            $rows[] = array_combine(self::HEADERS, array_pad($values, count(self::HEADERS), '')) + ['_fila' => $line];
        }
        fclose($handle);

        return $rows;
    }

    private function readAuditWorkbook(string $path): array
    {
        abort_unless(class_exists(ZipArchive::class), 422, 'El servidor no tiene habilitada la lectura de archivos Excel.');
        $zip = $this->openSafeWorkbook($path);
        $sharedStrings = $this->sharedStrings($zip);
        $sheet = $zip->getFromName('xl/worksheets/sheet1.xml');
        abort_unless($sheet !== false, 422, 'El archivo Excel no contiene una hoja de datos válida.');
        $rows = $this->worksheetRows($sheet, $sharedStrings);
        $zip->close();
        abort_unless(count($rows) > 1, 422, 'El archivo Excel no contiene filas para importar.');
        $headers = array_map(fn ($value) => $this->normalizeHeader($value), $rows[1] ?? []);
        abort_unless($headers === self::AUDIT_HEADERS, 422, 'La cabecera del Excel no coincide con el formato de auditoría esperado. Usa la opción “Descargar activos en Excel” como plantilla o verifica que la primera fila contenga las columnas requeridas.');

        return collect($rows)->skip(1)->map(function (array $values, int $index): array {
            $source = array_pad($values, count(self::AUDIT_HEADERS), '');

            return $this->auditRow($source, $index + 2);
        })->filter(fn (array $row) => $row['activo_fijo'] !== '')->values()->all();
    }

    private function sharedStrings(ZipArchive $zip): array
    {
        $xml = $zip->getFromName('xl/sharedStrings.xml');
        if ($xml === false) {
            return [];
        }
        $document = $this->xmlDocument($xml);
        $xpath = new DOMXPath($document);
        $xpath->registerNamespace('s', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');

        return collect($xpath->query('//s:si'))->map(fn ($item) => trim($xpath->evaluate('string(.)', $item)))->all();
    }

    private function worksheetRows(string $xml, array $sharedStrings): array
    {
        $document = $this->xmlDocument($xml);
        $xpath = new DOMXPath($document);
        $xpath->registerNamespace('s', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
        $rows = [];
        foreach ($xpath->query('//s:sheetData/s:row') as $row) {
            $values = [];
            foreach ($xpath->query('./s:c', $row) as $cell) {
                preg_match('/[A-Z]+/', $cell->getAttribute('r'), $matches);
                $column = $this->columnNumber($matches[0] ?? 'A');
                $type = $cell->getAttribute('t');
                $value = $type === 'inlineStr'
                    ? $xpath->evaluate('string(s:is)', $cell)
                    : $xpath->evaluate('string(s:v)', $cell);
                if ($type === 's') {
                    $value = $sharedStrings[(int) $value] ?? '';
                }
                $values[$column] = $value;
            }
            if ($values) {
                $line = array_values(array_replace(array_fill(1, 10, ''), $values));
                while ($line && end($line) === '') {
                    array_pop($line);
                }
                $rows[(int) $row->getAttribute('r')] = $line;
            }
        }

        return $rows;
    }

    private function openSafeWorkbook(string $path): ZipArchive
    {
        $zip = new ZipArchive;
        abort_unless($zip->open($path) === true, 422, 'No se pudo leer el archivo Excel.');
        $size = 0;
        for ($index = 0; $index < $zip->numFiles; $index++) {
            $entry = $zip->statIndex($index);
            $size += (int) ($entry['size'] ?? 0);
        }
        if ($zip->numFiles > self::MAX_XLSX_ENTRIES || $size > self::MAX_XLSX_UNCOMPRESSED_BYTES) {
            $zip->close();
            abort(422, 'El archivo Excel supera los límites de seguridad permitidos.');
        }

        return $zip;
    }

    private function xmlDocument(string $xml): DOMDocument
    {
        abort_unless(strlen($xml) <= self::MAX_XLSX_XML_BYTES, 422, 'El archivo Excel contiene una hoja demasiado grande.');
        $previous = libxml_use_internal_errors(true);
        $document = new DOMDocument;
        $loaded = $document->loadXML($xml, LIBXML_NONET | LIBXML_COMPACT);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        abort_unless($loaded, 422, 'El archivo Excel contiene datos XML no válidos.');

        return $document;
    }

    private function auditRow(array $source, int $line): array
    {
        [$fixed, $serial, $location, $designation, $costCenter, $quantity, $capitalizationDate, $netValue, $remainingLife, $manager] = $source;
        $location = trim((string) $location);
        $hasLocation = $location !== '' && strtoupper($location) !== '#N/A';
        $type = $this->auditAssetType((string) $designation);
        $observation = collect([
            'Denominación: '.trim((string) $designation),
            $costCenter !== '' ? 'Centro de costo: '.trim((string) $costCenter) : null,
            $quantity !== '' ? 'Cantidad origen: '.trim((string) $quantity) : null,
            $capitalizationDate !== '' ? 'Fecha de capitalización: '.$this->excelDate($capitalizationDate) : null,
            $remainingLife !== '' ? 'Vida restante: '.trim((string) $remainingLife) : null,
            $manager !== '' ? 'Encargado: '.trim((string) $manager) : null,
        ])->filter()->implode(' | ');

        return ['activo_fijo' => trim((string) $fixed), 'sede_codigo' => 'MAIPU', 'tipo_codigo' => $type, 'estado_codigo' => 'operativo', 'uso' => 'sin_definir', 'ubicacion_codigo' => $hasLocation ? 'AUD-'.strtoupper(preg_replace('/[^A-Za-z0-9]+/', '-', $location)) : '', 'numero_serie' => trim((string) $serial), 'marca' => strtok(trim((string) $designation), ' ') ?: '', 'modelo' => mb_substr(trim((string) $designation), 0, 80), 'costo_neto' => trim((string) $netValue), 'responsable_nombre' => '', 'responsable_email' => '', 'responsable_departamento' => '', 'observacion' => $observation, '_fila' => $line, '_crear_ubicacion' => $hasLocation, '_ubicacion_nombre' => $location];
    }

    private function auditAssetType(string $designation): string
    {
        $text = mb_strtolower($designation);

        return str_contains($text, 'notebook') || str_contains($text, 'laptop') ? 'notebook' : (str_contains($text, 'monitor') || str_contains($text, 'pantalla') ? 'monitor' : (str_contains($text, 'pc') || str_contains($text, 'pro ') || str_contains($text, 'thinkcentre') ? 'pc' : (str_contains($text, 'switch') || str_contains($text, 'router') || str_contains($text, 'cable') || str_contains($text, 'adaptador') ? 'data' : 'otro')));
    }

    private function auditLocationId(int $siteId, string $code, string $name): int
    {
        return DB::table('ubicaciones')->where('sede_id', $siteId)->where('codigo', $code)->value('id') ?? DB::table('ubicaciones')->insertGetId(['sede_id' => $siteId, 'tipo' => 'sala', 'codigo' => $code, 'nombre' => $name, 'activo' => true, 'created_at' => now(), 'updated_at' => now()]);
    }

    private function xlsxRow(int $row, array $values): string
    {
        $cells = [];
        foreach ($values as $index => $value) {
            $reference = $this->columnLetters($index + 1).$row;
            if (is_numeric($value) && ! in_array($index, [0, 1], true)) {
                $cells[] = '<c r="'.$reference.'"><v>'.htmlspecialchars((string) $value, ENT_XML1).'</v></c>';

                continue;
            }
            $cells[] = '<c r="'.$reference.'" t="inlineStr"><is><t>'.htmlspecialchars((string) $value, ENT_XML1 | ENT_COMPAT, 'UTF-8').'</t></is></c>';
        }

        return '<row r="'.$row.'">'.implode('', $cells).'</row>';
    }

    private function columnLetters(int $number): string
    {
        $letters = '';
        while ($number > 0) {
            $number--;
            $letters = chr(65 + ($number % 26)).$letters;
            $number = intdiv($number, 26);
        }

        return $letters;
    }

    private function observationValue(?string $observation, string $label): string
    {
        preg_match('/'.preg_quote($label, '/').':\s*([^|]+)/u', (string) $observation, $matches);

        return trim($matches[1] ?? '');
    }

    private function extension(string $filename): string
    {
        return strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    }

    private function normalizeHeader(mixed $value): string
    {
        $value = trim((string) $value);
        $value = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value) ?: $value;

        return preg_replace('/[^A-Za-z0-9.]+/', '', $value);
    }

    private function columnNumber(string $letters): int
    {
        return array_reduce(str_split($letters), fn (int $number, string $letter) => $number * 26 + ord($letter) - 64, 0);
    }

    private function excelDate(mixed $value): string
    {
        return is_numeric($value) ? now()->setDate(1899, 12, 30)->addDays((int) $value)->toDateString() : trim((string) $value);
    }

    private function validateRows(array $rows): array
    {
        $errors = [];
        $seen = [];
        $seenCanonicalCodes = [];
        $sites = DB::table('sedes')->pluck('id', 'codigo');
        $types = DB::table('tipos_activo')->where('activo', true)->pluck('id', 'codigo');
        $statuses = DB::table('estados_activo')->where('activo', true)->pluck('id', 'codigo');
        $locations = DB::table('ubicaciones')->where('activo', true)->get()->keyBy('codigo');
        $existing = Asset::query()->pluck('id', 'activo_fijo');
        $existingCanonicalCodes = $existing->keys()
            ->mapWithKeys(fn (string $code) => ($canonical = AssetCodeMatch::canonicalNumericCode($code)) === null ? [] : [$canonical => true]);
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
            $canonicalCode = AssetCodeMatch::canonicalNumericCode($fixed);
            if ($canonicalCode !== null && isset($seenCanonicalCodes[$canonicalCode])) {
                $add('activo_fijo', 'duplicado_archivo', 'El activo fijo coincide con otro código del archivo al ignorar ceros finales.');
            }
            if ($canonicalCode !== null) {
                $seenCanonicalCodes[$canonicalCode] = true;
            }
            if ($existing->has($fixed) || ($canonicalCode !== null && $existingCanonicalCodes->has($canonicalCode))) {
                $add('activo_fijo', 'duplicado_base', 'El activo fijo ya existe o coincide con uno existente al ignorar ceros finales.');
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
            if (! in_array(strtolower(trim($row['uso'])), ['administrativo', 'alumnos', 'docente', 'comun', 'sin_definir'], true)) {
                $add('uso', 'uso_invalido', 'El uso debe ser administrativo, alumnos, docente, comun o sin_definir.');
            }
            $locationCode = trim($row['ubicacion_codigo']);
            if ($locationCode !== '' && empty($row['_crear_ubicacion']) && (! $locations->has($locationCode) || ($sites->get(trim($row['sede_codigo'])) && $locations->get($locationCode)->sede_id !== $sites->get(trim($row['sede_codigo']))))) {
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
        $locationId = trim($row['ubicacion_codigo']) === '' ? null : ($row['_crear_ubicacion'] ?? false ? $this->auditLocationId($siteId, trim($row['ubicacion_codigo']), $row['_ubicacion_nombre']) : DB::table('ubicaciones')->where('codigo', trim($row['ubicacion_codigo']))->value('id'));

        return ['sede_id' => $siteId, 'tipo_activo_id' => $typeId, 'estado_activo_id' => $statusId, 'uso' => strtolower(trim($row['uso'])), 'ubicacion_actual_id' => $locationId, 'activo_fijo' => trim($row['activo_fijo']), 'numero_serie' => trim($row['numero_serie']) ?: null, 'marca' => trim($row['marca']) ?: null, 'modelo' => trim($row['modelo']) ?: null, 'costo_neto_actual' => trim($row['costo_neto']) ?: null, 'responsable_nombre' => trim($row['responsable_nombre']) ?: null, 'responsable_email' => trim($row['responsable_email']) ?: null, 'responsable_departamento' => trim($row['responsable_departamento']) ?: null, 'observacion' => trim($row['observacion']) ?: null, 'creado_por' => $userId];
    }
}
