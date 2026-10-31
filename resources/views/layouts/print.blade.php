<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Documento de inventario')</title>
    <style>
        :root { color-scheme: light; }
        * { box-sizing: border-box; }
        body { color: #1f2937; font: 14px/1.45 Arial, sans-serif; margin: 0; background: #f3f4f6; }
        .sheet { max-width: 210mm; min-height: 297mm; margin: 20px auto; padding: 18mm; background: #fff; }
        .toolbar { max-width: 210mm; margin: 16px auto 0; display: flex; justify-content: flex-end; gap: 8px; }
        button, .back { border: 1px solid #374151; border-radius: 5px; color: #fff; background: #374151; cursor: pointer; font-size: 14px; padding: 9px 13px; text-decoration: none; }
        .back { color: #374151; background: #fff; }
        .header { border-bottom: 3px solid #7c3aed; margin-bottom: 22px; padding-bottom: 14px; }
        h1 { font-size: 24px; margin: 0 0 4px; } h2 { font-size: 17px; margin: 22px 0 10px; }
        .muted { color: #6b7280; margin: 0; } .meta { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px 24px; }
        .meta dt { color: #6b7280; font-size: 12px; font-weight: bold; text-transform: uppercase; } .meta dd { margin: 2px 0 0; font-size: 15px; }
        .summary { display: flex; gap: 12px; margin: 18px 0; } .summary div { border: 1px solid #d1d5db; border-radius: 5px; flex: 1; padding: 10px; } .summary strong { display: block; font-size: 23px; }
        table { border-collapse: collapse; width: 100%; } th, td { border: 1px solid #d1d5db; padding: 7px; text-align: left; vertical-align: top; } th { background: #f3f4f6; font-size: 12px; } .ok { color: #047857; font-weight: bold; } .warning { color: #b45309; font-weight: bold; } .danger { color: #b91c1c; font-weight: bold; }
        .footer { border-top: 1px solid #d1d5db; color: #6b7280; font-size: 11px; margin-top: 30px; padding-top: 10px; }
        @media print { body { background: #fff; } .toolbar { display: none; } .sheet { margin: 0; max-width: none; min-height: 0; padding: 12mm; } @page { margin: 0; size: A4; } }
    </style>
</head>
<body>
    <div class="toolbar"><a class="back" href="{{ url()->previous() }}">Volver</a><button type="button" onclick="window.print()">Imprimir / Guardar PDF</button></div>
    <main class="sheet">@yield('content')</main>
</body>
</html>
