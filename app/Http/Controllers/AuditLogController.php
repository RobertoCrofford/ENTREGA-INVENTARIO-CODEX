<?php

namespace App\Http\Controllers;

use App\Services\AuditService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AuditLogController extends Controller
{
    /**
     * Rendering a large audit trail as PDF can exhaust the PHP-FPM worker.
     * CSV remains suitable for complete, long periods.
     */
    private const PDF_MAX_ENTRIES = 250;

    public function generateForm(): RedirectResponse
    {
        Gate::authorize('generar-bitacora');

        return redirect()->route('audit-logs.index');
    }

    public function index(): View
    {
        Gate::authorize('generar-bitacora');

        $archives = DB::table('archivos_auditoria')->orderByDesc('id')->paginate(20);

        return view('audit-logs.index', compact('archives'));
    }

    public function generate(Request $request, AuditService $audit): RedirectResponse
    {
        Gate::authorize('generar-bitacora');
        $data = $request->validate(['desde' => ['required', 'date'], 'hasta' => ['required', 'date', 'after_or_equal:desde'], 'formato' => ['required', Rule::in(['csv', 'pdf'])]]);
        $from = CarbonImmutable::parse($data['desde'], config('app.timezone'))->startOfDay()->utc();
        $to = CarbonImmutable::parse($data['hasta'], config('app.timezone'))->endOfDay()->utc();

        if (DB::table('archivos_auditoria')->where('periodo_desde', '<=', $to)->where('periodo_hasta', '>=', $from)->exists()) {
            throw ValidationException::withMessages(['desde' => 'El período se solapa con una bitácora ya generada. Selecciona un rango distinto.']);
        }

        $entries = DB::table('bitacora')
            ->leftJoin('users', 'users.id', '=', 'bitacora.usuario_id')
            ->whereBetween('bitacora.creado_at', [$from, $to])
            ->orderBy('bitacora.id')
            ->select('bitacora.*', 'users.name as usuario_nombre', 'users.username as usuario_username')
            ->get();
        $format = $data['formato'];
        $pdfWasChangedToCsv = $format === 'pdf' && $entries->count() > self::PDF_MAX_ENTRIES;
        if ($pdfWasChangedToCsv) {
            $format = 'csv';
        }

        $filename = 'bitacora_'.$from->format('Ymd').'_'.$to->format('Ymd').'.'.$format;
        $path = 'auditoria/'.$filename;
        if ($format === 'pdf') {
            $content = Pdf::loadView('pdf.audit-log', compact('entries', 'from', 'to'))->setPaper('a4', 'landscape')->output();
        } else {
            $stream = fopen('php://temp', 'r+');
            fwrite($stream, "\xEF\xBB\xBF");
            fputcsv($stream, ['ID', 'Fecha UTC', 'Usuario', 'Acción', 'Entidad', 'ID entidad', 'Motivo', 'Resultado', 'IP', 'Antes', 'Después'], ',', '"', '\\');
            foreach ($entries as $entry) {
                fputcsv($stream, [$entry->id, $entry->creado_at, $entry->usuario_nombre ? $entry->usuario_nombre.' ('.$entry->usuario_username.')' : 'Sistema', $entry->accion, $entry->entidad_tipo, $entry->entidad_id, $entry->motivo, $entry->resultado, $entry->ip, $entry->antes_json, $entry->despues_json], ',', '"', '\\');
            }
            rewind($stream);
            $content = stream_get_contents($stream);
            fclose($stream);
        }
        Storage::disk('local')->put($path, $content);
        $archiveId = DB::table('archivos_auditoria')->insertGetId([
            'periodo_desde' => $from, 'periodo_hasta' => $to, 'ruta' => $path,
            'sha256' => hash_file('sha256', Storage::disk('local')->path($path)),
            'creado_por' => $request->user()->id, 'creado_at' => now(),
        ]);
        if ($entries->isNotEmpty()) {
            DB::table('bitacora')->whereIn('id', $entries->pluck('id'))->update(['archivo_auditoria_id' => $archiveId, 'archivado_at' => now()]);
        }
        $audit->record($request->user(), 'generar', 'archivo_auditoria', $archiveId, reason: "Bitácora {$data['desde']} a {$data['hasta']}.");

        $message = "Bitácora {$filename} generada correctamente. Ya puedes descargarla desde el historial.";
        if ($pdfWasChangedToCsv) {
            $message .= ' Se generó en CSV porque el período contiene más de '.self::PDF_MAX_ENTRIES.' eventos; así se evita que el sistema se caiga y se conservan todos los datos.';
        }

        return redirect()->route('audit-logs.index')->with('success', $message);
    }

    public function download(int $archiveId)
    {
        Gate::authorize('generar-bitacora');
        $archive = DB::table('archivos_auditoria')->find($archiveId);
        abort_unless($archive && Storage::disk('local')->exists($archive->ruta), 404);

        return Storage::disk('local')->download($archive->ruta, basename($archive->ruta));
    }
}
