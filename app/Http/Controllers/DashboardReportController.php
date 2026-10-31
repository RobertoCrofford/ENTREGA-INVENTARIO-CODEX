<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use ZipArchive;

class DashboardReportController extends Controller
{
    public function download()
    {
        Gate::authorize('consultar-inventario');
        $summary = [
            ['Activos registrados', DB::table('activos')->count()],
            ['Eventos del año', DB::table('eventos_calendario')->whereYear('inicio_at', now()->year)->count()],
            ['Reparaciones abiertas', DB::table('reparaciones')->whereIn('estado', ['abierta', 'en_proceso'])->count()],
            ['Movimientos publicados', DB::table('movimientos_inventario')->where('estado', 'publicado')->count()],
        ];
        $events = DB::table('eventos_calendario')->orderByDesc('inicio_at')->limit(500)->get();
        $html = '<html><head><meta charset="UTF-8"></head><body><h1>Reporte de Inventario Institucional</h1><p>Generado: '.now()->format('d-m-Y H:i').'</p><h2>Resumen ejecutivo</h2><table border="1"><tr><th>Indicador</th><th>Total</th></tr>';
        foreach ($summary as [$label, $total]) $html .= '<tr><td>'.e($label).'</td><td>'.e($total).'</td></tr>';
        $html .= '</table><h2>Eventos</h2><table border="1"><tr><th>Fecha</th><th>Evento</th><th>Lugar</th><th>Estado</th></tr>';
        foreach ($events as $event) $html .= '<tr><td>'.e($event->inicio_at).'</td><td>'.e($event->titulo).'</td><td>'.e($event->lugar).'</td><td>'.e($event->estado).'</td></tr>';
        $html .= '</table></body></html>';
        return response($html, 200, ['Content-Type' => 'application/vnd.ms-excel; charset=UTF-8', 'Content-Disposition' => 'attachment; filename="reporte-inventario-'.now()->format('Y-m-d').'.xls"']);

        abort_unless(class_exists(ZipArchive::class), 422, 'El servidor no puede generar Excel.');
        $path = tempnam(sys_get_temp_dir(), 'reporte-');
        $zip = new ZipArchive;
        abort_unless($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true, 500, 'No se pudo generar el reporte.');
        $sheets = [
            'Resumen' => [['Indicador', 'Total'], ['Activos registrados', DB::table('activos')->count()], ['Eventos del año', DB::table('eventos_calendario')->whereYear('inicio_at', now()->year)->count()], ['Reparaciones abiertas', DB::table('reparaciones')->whereIn('estado', ['abierta', 'en_proceso'])->count()], ['Movimientos publicados', DB::table('movimientos_inventario')->where('estado', 'publicado')->count()]],
            'Eventos' => array_merge([['Fecha', 'Evento', 'Lugar', 'Estado']], DB::table('eventos_calendario')->orderByDesc('inicio_at')->get()->map(fn ($e) => [$e->inicio_at, $e->titulo, $e->lugar, $e->estado])->all()),
            'Reparaciones' => array_merge([['Activo', 'Estado', 'Prioridad']], DB::table('reparaciones as r')->join('activos as a', 'a.id', '=', 'r.activo_id')->select('a.activo_fijo', 'r.estado', 'r.prioridad')->get()->map(fn ($r) => [$r->activo_fijo, $r->estado, $r->prioridad])->all()),
        ];
        $content = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>';
        foreach (array_keys($sheets) as $i => $_) $content .= '<Override PartName="/xl/worksheets/sheet'.($i + 1).'.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
        $zip->addFromString('[Content_Types].xml', $content.'</Types>');
        $zip->addFromString('_rels/.rels', '<?xml version="1.0"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships" Target="xl/workbook.xml"/></Relationships>');
        $book = '<?xml version="1.0"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets>';
        $rels = '<?xml version="1.0"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">';
        foreach ($sheets as $i => $rows) { $n = array_search($i, array_keys($sheets)) + 1; $book .= '<sheet name="'.$i.'" sheetId="'.$n.'" r:id="rId'.$n.'"/>'; $rels .= '<Relationship Id="rId'.$n.'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet'.$n.'.xml"/>'; $xml = '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>'; foreach ($rows as $r => $row) { $xml .= '<row r="'.($r + 1).'">'; foreach ($row as $c => $value) { $ref = chr(65 + $c).($r + 1); $xml .= '<c r="'.$ref.'" t="inlineStr"><is><t>'.htmlspecialchars((string) $value, ENT_XML1).'</t></is></c>'; } $xml .= '</row>'; } $zip->addFromString('xl/worksheets/sheet'.$n.'.xml', $xml.'</sheetData></worksheet>'); }
        $zip->addFromString('xl/workbook.xml', $book.'</sheets></workbook>'); $zip->addFromString('xl/_rels/workbook.xml.rels', $rels.'</Relationships>'); $zip->close();
        return response()->download($path, 'reporte-inventario-'.now()->format('Y-m-d').'.xlsx', ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'])->deleteFileAfterSend(true);
    }
}
