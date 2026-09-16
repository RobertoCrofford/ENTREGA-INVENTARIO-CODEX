<?php

namespace App\Http\Controllers;

use App\Services\AuditService;
use Carbon\Carbon;
use DOMDocument;
use DOMXPath;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use ZipArchive;

class CalendarEventController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('ver-eventos');
        $month = $this->month($request->query('mes'));
        $from = $month->copy()->startOfMonth();
        $until = $month->copy()->endOfMonth();
        $events = DB::table('eventos_calendario')
            ->where('inicio_at', '<=', $until->endOfDay())
            ->where(fn ($query) => $query->whereNull('termino_at')->orWhere('termino_at', '>=', $from->startOfDay()))
            ->orderBy('inicio_at')
            ->get();
        $eventsByDay = $this->eventsByDay($events, $from, $until);
        $selectedDate = $this->selectedDate($request->query('dia'), $month);

        return view('events.index', [
            'month' => $month,
            'previousMonth' => $month->copy()->subMonth()->format('Y-m'),
            'nextMonth' => $month->copy()->addMonth()->format('Y-m'),
            'eventsByDay' => $eventsByDay,
            'selectedDate' => $selectedDate,
            'selectedEvents' => $selectedDate ? $eventsByDay->get($selectedDate, collect()) : collect(),
            'weeks' => $this->weeks($month),
            'upcomingEvents' => DB::table('eventos_calendario')->where('inicio_at', '>=', now()->startOfDay())->orderBy('inicio_at')->limit(8)->get(),
        ]);
    }

    public function store(Request $request, AuditService $audit): RedirectResponse
    {
        Gate::authorize('gestionar-eventos');
        $data = $request->validate([
            'titulo' => ['required', 'string', 'max:160'],
            'fecha_inicio' => ['required', 'date'],
            'fecha_termino' => ['nullable', 'date', 'after_or_equal:fecha_inicio'],
            'hora_inicio' => ['nullable', 'date_format:H:i'],
            'hora_termino' => ['nullable', 'date_format:H:i'],
            'lugar' => ['nullable', 'string', 'max:160'],
            'descripcion' => ['nullable', 'string', 'max:2000'],
        ]);
        $allDay = empty($data['hora_inicio']);
        $start = Carbon::parse($data['fecha_inicio'])->startOfDay();
        $end = Carbon::parse($data['fecha_termino'] ?: $data['fecha_inicio'])->startOfDay();
        if (! $allDay) {
            $start->setTimeFromTimeString($data['hora_inicio']);
            $end->setTimeFromTimeString($data['hora_termino'] ?: $data['hora_inicio']);
            if ($end->lessThan($start)) {
                return back()->withInput()->withErrors(['hora_termino' => 'La hora de término debe ser posterior a la hora de inicio.']);
            }
        } else {
            $end->endOfDay();
        }
        $event = DB::table('eventos_calendario')->insertGetId([
            'titulo' => trim($data['titulo']), 'descripcion' => $this->blankToNull($data['descripcion'] ?? null),
            'lugar' => $this->blankToNull($data['lugar'] ?? null), 'inicio_at' => $start,
            'termino_at' => $end, 'todo_el_dia' => $allDay, 'origen' => 'manual',
            'creado_por' => $request->user()->id, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $audit->record($request->user(), 'registrar', 'evento_calendario', $event, after: ['titulo' => trim($data['titulo']), 'inicio_at' => $start->toDateTimeString()], reason: 'Registro manual', notify: false);
        $this->notifyUsers('Nuevo evento registrado', "Se registró el evento: ".trim($data['titulo']).'.', route('events.index', ['mes' => $start->format('Y-m'), 'dia' => $start->toDateString()]));

        return redirect()->route('events.index', ['mes' => $start->format('Y-m')])->with('success', 'Evento registrado en el calendario.');
    }

    public function import(Request $request, AuditService $audit): RedirectResponse
    {
        Gate::authorize('gestionar-eventos');
        $request->validate([
            'archivo' => ['required', 'file', 'max:5120', 'mimetypes:text/plain,text/csv,application/csv,application/vnd.ms-excel,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
            'anio' => ['required', 'integer', 'between:2020,2100'],
        ]);
        $file = $request->file('archivo');
        $extension = strtolower($file->getClientOriginalExtension());
        if (! in_array($extension, ['csv', 'xlsx'], true)) {
            return back()->withErrors(['archivo' => 'Selecciona un archivo CSV o Excel (.xlsx).']);
        }

        try {
            $rows = $this->readRows($file->getRealPath(), $extension, (int) $request->integer('anio'));
        } catch (\Throwable $exception) {
            return back()->withErrors(['archivo' => $exception->getMessage()]);
        }
        $created = 0;
        $errors = [];
        DB::transaction(function () use ($rows, $request, $file, &$created, &$errors): void {
            foreach ($rows as $row) {
                $event = $this->eventFromRow($row);
                if (! $event['valid']) {
                    $errors[] = "Fila {$row['_fila']}: {$event['error']}";
                    continue;
                }
                DB::table('eventos_calendario')->insert([
                    'titulo' => $event['titulo'], 'descripcion' => $event['descripcion'], 'lugar' => $event['lugar'],
                    'inicio_at' => $event['inicio_at'], 'termino_at' => $event['termino_at'], 'todo_el_dia' => $event['todo_el_dia'],
                    'origen' => 'excel', 'archivo_origen' => $file->getClientOriginalName(), 'creado_por' => $request->user()->id,
                    'created_at' => now(), 'updated_at' => now(),
                ]);
                $created++;
            }
        });
        $audit->record($request->user(), 'importar', 'evento_calendario', null, after: ['eventos_creados' => $created, 'filas_con_error' => count($errors)], reason: $file->getClientOriginalName(), notify: false);
        if ($created > 0) {
            $this->notifyUsers('Eventos importados', "Se registraron {$created} eventos desde el archivo {$file->getClientOriginalName()}.", route('events.index'));
        }

        $message = "Se registraron {$created} evento(s) desde el archivo.";
        if ($errors) {
            return redirect()->route('events.index')->with('warning', $message.' Algunas filas no se importaron: '.implode(' ', array_slice($errors, 0, 3)));
        }

        return redirect()->route('events.index')->with('success', $message);
    }

    private function month(?string $value): Carbon
    {
        return preg_match('/^\d{4}-\d{2}$/', (string) $value) ? Carbon::createFromFormat('Y-m', $value)->startOfMonth() : now()->startOfMonth();
    }

    private function weeks(Carbon $month): array
    {
        $cursor = $month->copy()->startOfMonth()->startOfWeek(Carbon::MONDAY);
        $last = $month->copy()->endOfMonth()->endOfWeek(Carbon::SUNDAY);
        $weeks = [];
        while ($cursor->lessThanOrEqualTo($last)) {
            $week = [];
            for ($day = 0; $day < 7; $day++) {
                $week[] = $cursor->copy();
                $cursor->addDay();
            }
            $weeks[] = $week;
        }

        return $weeks;
    }

    private function selectedDate(?string $value, Carbon $month): ?string
    {
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $value)) {
            return null;
        }
        try {
            $date = Carbon::createFromFormat('Y-m-d', $value);

            return $date->isSameMonth($month) ? $date->toDateString() : null;
        } catch (\Throwable) {
            return null;
        }
    }

    private function eventsByDay(Collection $events, Carbon $from, Carbon $until): Collection
    {
        $days = collect();
        foreach ($events as $event) {
            $cursor = Carbon::parse($event->inicio_at)->startOfDay()->max($from->copy());
            $last = Carbon::parse($event->termino_at ?: $event->inicio_at)->startOfDay()->min($until->copy());
            while ($cursor->lessThanOrEqualTo($last)) {
                $days->put($cursor->toDateString(), $days->get($cursor->toDateString(), collect())->push($event));
                $cursor->addDay();
            }
        }

        return $days;
    }

    private function readRows(string $path, string $extension, int $year): array
    {
        if ($extension === 'csv') {
            $handle = fopen($path, 'r');
            $headers = fgetcsv($handle) ?: [];
            $rows = [];
            $line = 1;
            while (($values = fgetcsv($handle)) !== false) {
                $line++;
                if ($values === [null] || $values === []) {
                    continue;
                }
                $rows[] = $values;
            }
            fclose($handle);

            return $this->eventRows(array_merge([$headers], $rows), $year);
        }
        if (! class_exists(ZipArchive::class)) {
            throw new \RuntimeException('El servidor no tiene habilitada la lectura de archivos Excel.');
        }
        $zip = new ZipArchive;
        if ($zip->open($path) !== true) {
            throw new \RuntimeException('No se pudo leer el archivo Excel.');
        }
        $strings = $this->sharedStrings($zip);
        $sheet = $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();
        if ($sheet === false) {
            throw new \RuntimeException('El Excel no contiene una primera hoja de datos válida.');
        }
        $rows = $this->worksheetRows($sheet, $strings);
        if (count($rows) < 2) {
            throw new \RuntimeException('El archivo no contiene eventos para importar.');
        }
        return $this->eventRows($rows, $year);
    }

    private function eventRows(array $rows, int $year): array
    {
        foreach ($rows as $index => $headers) {
            $normalized = collect($headers)->map(fn ($header) => $this->header((string) $header));
            $hasDate = $normalized->contains('fecha');
            $hasEvent = $normalized->intersect(['evento', 'nombredelaactividadevento', 'actividad', 'titulo'])->isNotEmpty();
            if (! $hasDate || ! $hasEvent) {
                continue;
            }

            return collect(array_slice($rows, $index + 1))->map(fn (array $values, int $row) => $this->mapRow($headers, $values, $index + $row + 2, $year))
                ->filter(fn (array $row) => collect($row)->except(['_fila', '_anio'])->filter()->isNotEmpty())->values()->all();
        }

        throw new \RuntimeException('No se encontraron las columnas Fecha y Nombre de la Actividad / Evento en el archivo.');
    }

    private function mapRow(array $headers, array $values, int $line, int $year): array
    {
        $mapped = ['_fila' => $line, '_anio' => $year];
        foreach ($headers as $index => $header) {
            $mapped[$this->header((string) $header)] = trim((string) ($values[$index] ?? ''));
        }

        return $mapped;
    }

    private function eventFromRow(array $row): array
    {
        $date = $this->value($row, ['fecha', 'fechaevento', 'fechainicio', 'dia', 'inicio']);
        $title = $this->value($row, ['evento', 'nombre', 'nombreevento', 'nombredelaactividadevento', 'actividad', 'titulo', 'descripcion']);
        if ($date === '' || $title === '') {
            return ['valid' => false, 'error' => 'debe contener al menos las columnas Fecha y Evento.'];
        }
        try {
            $start = $this->date($date, (int) $row['_anio']);
        } catch (\Throwable) {
            return ['valid' => false, 'error' => 'la fecha no tiene un formato válido.'];
        }
        $schedule = $this->value($row, ['horario', 'horainicio', 'hora', 'iniciohora']);
        [$startTime, $endTime] = $this->timeRange($schedule);
        $endDate = $this->value($row, ['fechatermino', 'fechafin', 'termino', 'fin']);
        $endTime = $endTime ?: $this->value($row, ['horatermino', 'horafin', 'terminohora', 'finhora']);
        $allDay = $startTime === '';
        if ($allDay) {
            $start->startOfDay();
        } else {
            $start->setTimeFromTimeString($startTime);
        }
        $end = $endDate !== '' ? $this->date($endDate, (int) $row['_anio']) : $start->copy();
        if ($allDay) {
            $end->endOfDay();
        } else {
            $end->setTimeFromTimeString($endTime !== '' ? $endTime : $startTime);
        }
        if ($end->lessThan($start)) {
            return ['valid' => false, 'error' => 'la fecha u hora de término es anterior al inicio.'];
        }

        $details = collect([
            $this->value($row, ['descripcion', 'detalle', 'observacion']),
            $this->value($row, ['responsable']) !== '' ? 'Responsable: '.$this->value($row, ['responsable']) : null,
            $schedule !== '' ? 'Horario informado: '.$schedule : null,
            $this->value($row, ['requerimientoslogisticostecnicosyobservaciones', 'requerimientos', 'logistica']) !== '' ? 'Requerimientos: '.$this->value($row, ['requerimientoslogisticostecnicosyobservaciones', 'requerimientos', 'logistica']) : null,
        ])->filter()->implode("\n\n");

        return ['valid' => true, 'titulo' => mb_substr($title, 0, 160), 'descripcion' => $this->blankToNull($details), 'lugar' => $this->blankToNull($this->value($row, ['lugar', 'lugarespacio', 'ubicacion', 'sala'])), 'inicio_at' => $start, 'termino_at' => $end, 'todo_el_dia' => $allDay];
    }

    private function worksheetRows(string $xml, array $sharedStrings): array
    {
        $document = new DOMDocument;
        $document->loadXML($xml);
        $xpath = new DOMXPath($document);
        $xpath->registerNamespace('s', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');

        return collect($xpath->query('//s:sheetData/s:row'))->map(function ($row) use ($xpath, $sharedStrings): array {
            $values = [];
            foreach ($xpath->query('./s:c', $row) as $cell) {
                preg_match('/[A-Z]+/', $cell->getAttribute('r'), $matches);
                $column = $this->columnNumber($matches[0] ?? 'A');
                $type = $cell->getAttribute('t');
                $value = $type === 'inlineStr' ? $xpath->evaluate('string(s:is)', $cell) : $xpath->evaluate('string(s:v)', $cell);
                $values[$column] = $type === 's' ? ($sharedStrings[(int) $value] ?? '') : $value;
            }
            ksort($values);

            return array_values(array_replace(array_fill(1, max(1, count($values)), ''), $values));
        })->values()->all();
    }

    private function sharedStrings(ZipArchive $zip): array
    {
        $xml = $zip->getFromName('xl/sharedStrings.xml');
        if ($xml === false) {
            return [];
        }
        $document = new DOMDocument;
        $document->loadXML($xml);
        $xpath = new DOMXPath($document);
        $xpath->registerNamespace('s', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');

        return collect($xpath->query('//s:si'))->map(fn ($item) => trim($xpath->evaluate('string(.)', $item)))->all();
    }

    private function date(string $value, int $year): Carbon
    {
        if (is_numeric($value)) {
            return Carbon::create(1899, 12, 30)->addDays((int) $value);
        }
        foreach (['d/m/Y', 'd-m-Y', 'Y-m-d'] as $format) {
            try {
                return Carbon::createFromFormat($format, $value);
            } catch (\Throwable) {
            }
        }

        $months = ['enero' => 1, 'febrero' => 2, 'marzo' => 3, 'abril' => 4, 'mayo' => 5, 'junio' => 6, 'julio' => 7, 'agosto' => 8, 'septiembre' => 9, 'setiembre' => 9, 'octubre' => 10, 'noviembre' => 11, 'diciembre' => 12];
        $normalized = mb_strtolower(trim($value));
        if (preg_match('/^(\d{1,2})\s+de\s+([[:alpha:]áéíóúñ]+)(?:\s+de\s+(\d{4}))?$/u', $normalized, $matches) && isset($months[$matches[2]])) {
            return Carbon::create((int) (($matches[3] ?? '') ?: $year), $months[$matches[2]], (int) $matches[1]);
        }

        return Carbon::parse($value);
    }

    private function timeRange(string $schedule): array
    {
        preg_match_all('/\b([01]?\d|2[0-3]):[0-5]\d\b/', $schedule, $matches);
        if (count($matches[0]) === 2) {
            return [$matches[0][0], $matches[0][1]];
        }

        return ['', ''];
    }

    private function header(string $value): string
    {
        $value = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', trim($value)) ?: $value;

        return strtolower((string) preg_replace('/[^A-Za-z0-9]+/', '', $value));
    }

    private function value(array $row, array $keys): string
    {
        foreach ($keys as $key) {
            if (($row[$key] ?? '') !== '') {
                return (string) $row[$key];
            }
        }

        return '';
    }

    private function columnNumber(string $letters): int
    {
        return array_reduce(str_split($letters), fn (int $number, string $letter) => $number * 26 + ord($letter) - 64, 0);
    }

    private function blankToNull(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function notifyUsers(string $title, string $message, string $url): void
    {
        $now = now();
        $notifications = DB::table('users')->where('activo', true)->pluck('id')->map(fn (int $userId) => [
            'usuario_id' => $userId, 'titulo' => $title, 'mensaje' => $message, 'url' => $url, 'creado_at' => $now,
        ])->all();
        if ($notifications) {
            DB::table('notificaciones')->insert($notifications);
        }
    }
}
