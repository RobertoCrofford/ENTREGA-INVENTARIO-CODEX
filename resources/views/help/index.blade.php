@extends('layouts.app')

@section('title', 'Centro de ayuda')

@section('content')
@php($canManageInventory = auth()->user()->can('gestionar-inventario'))
@php($canManageEvents = auth()->user()->can('gestionar-eventos'))
@php($canRequestDisposal = auth()->user()->can('solicitar-baja-activo'))
<div class="page-heading mb-4">
    <div><div class="eyebrow">Guías de uso</div><h1>Centro de ayuda</h1><p>La ayuda está disponible para todos. Los permisos de tu cuenta determinan qué acciones puedes realizar.</p></div>
</div>

<section class="card mb-4" id="tutorial-guiado">
    <div class="card-header"><i class="bi bi-play-circle me-2"></i>Tutorial guiado</div>
    <div class="card-body">
        <p class="text-body-secondary">Elige una tarea y sigue sus pasos. Puedes avanzar a tu ritmo.</p>
        <div class="btn-group flex-wrap mb-4" role="group" aria-label="Elegir tutorial">
            <button class="btn btn-primary" type="button" data-tutorial="scanner">Escáner</button>
            <button class="btn btn-outline-primary" type="button" data-tutorial="activos">Activos</button>
            <button class="btn btn-outline-primary" type="button" data-tutorial="productos">Productos</button>
            <button class="btn btn-outline-primary" type="button" data-tutorial="excel">Importar Excel</button>
            <button class="btn btn-outline-primary" type="button" data-tutorial="movimientos">Movimientos</button>
            <button class="btn btn-outline-primary" type="button" data-tutorial="reparaciones">Reparaciones</button>
            <button class="btn btn-outline-primary" type="button" data-tutorial="bajas">Bajas</button>
        </div>
        <div class="border rounded p-3 p-md-4" aria-live="polite">
            <div class="d-flex justify-content-between align-items-center gap-2 mb-2"><span class="eyebrow mb-0" id="tutorial-progreso"></span><span class="badge text-bg-secondary" id="tutorial-nombre"></span></div>
            <h2 class="h4" id="tutorial-titulo"></h2>
            <p class="mb-0" id="tutorial-descripcion"></p>
        </div>
        <div class="d-flex flex-wrap align-items-center gap-2 mt-3">
            <button class="btn btn-outline-light" id="tutorial-anterior" type="button"><i class="bi bi-arrow-left me-1"></i>Anterior</button>
            <button class="btn btn-primary" id="tutorial-siguiente" type="button">Siguiente<i class="bi bi-arrow-right ms-1"></i></button>
            <a class="btn btn-success d-none" id="tutorial-ir" href="#">Ir a la función<i class="bi bi-box-arrow-up-right ms-1"></i></a>
        </div>
    </div>
</section>

<div class="row g-3">
    <div class="col-lg-6" id="escanear"><article class="card h-100"><div class="card-header d-flex justify-content-between gap-2"><span><i class="bi bi-upc-scan me-2"></i>Usar el escáner</span><span class="badge text-bg-success">Disponible para todos</span></div><div class="card-body"><ol class="mb-0 ps-3"><li>Abre <strong>Escanear</strong> desde el menú.</li><li>Escanea o escribe el código, activo fijo o número de serie.</li><li>Presiona <strong>Buscar</strong>.</li><li>El sistema muestra la ficha del producto o activo encontrado.</li></ol><p class="text-body-secondary small mt-3 mb-0">Ejemplo: el número de serie <strong>5CG3510TJM</strong> abre el activo correspondiente.</p></div></article></div>
    <div class="col-lg-6" id="activos"><article class="card h-100"><div class="card-header d-flex justify-content-between gap-2"><span><i class="bi bi-pc-display me-2"></i>Registrar un activo</span><span class="badge {{ $canManageInventory ? 'text-bg-success' : 'text-bg-secondary' }}">{{ $canManageInventory ? 'Disponible con tu rol' : 'Técnico, director técnico o superadmin' }}</span></div><div class="card-body"><ol class="mb-0 ps-3"><li>Entra a <strong>Activos</strong> y selecciona <strong>Nuevo activo</strong>.</li><li>Completa activo fijo, tipo, uso, ubicación o responsable.</li><li>Guarda el registro.</li><li>Usa el activo fijo o número de serie para encontrarlo desde Escáner.</li></ol></div></article></div>
    <div class="col-lg-6" id="productos"><article class="card h-100"><div class="card-header d-flex justify-content-between gap-2"><span><i class="bi bi-box-seam me-2"></i>Controlar productos</span><span class="badge {{ $canManageInventory ? 'text-bg-success' : 'text-bg-secondary' }}">{{ $canManageInventory ? 'Disponible con tu rol' : 'Técnico, director técnico o superadmin' }}</span></div><div class="card-body"><ol class="mb-0 ps-3"><li>Busca el producto por nombre, código o número de parte.</li><li>Revisa la cantidad disponible antes de realizar una salida.</li><li>Registra entradas, salidas o traslados desde <strong>Movimientos</strong>.</li><li>El historial conservará el detalle del movimiento.</li></ol></div></article></div>
    <div class="col-lg-6" id="excel"><article class="card h-100"><div class="card-header d-flex justify-content-between gap-2"><span><i class="bi bi-file-earmark-excel me-2"></i>Importar Excel</span><span class="badge {{ $canManageEvents ? 'text-bg-success' : 'text-bg-secondary' }}">{{ $canManageEvents ? 'Disponible con tu rol' : 'Técnico, director técnico o superadmin' }}</span></div><div class="card-body"><ol class="mb-0 ps-3"><li>Abre <strong>Eventos</strong> y elige <strong>Importar Excel</strong>.</li><li>Selecciona el archivo y el año de la planificación.</li><li>Presiona <strong>Registrar eventos</strong>.</li><li>Haz clic en una fecha del calendario para revisar el detalle importado.</li></ol></div></article></div>
    <div class="col-lg-6" id="reparaciones"><article class="card h-100"><div class="card-header d-flex justify-content-between gap-2"><span><i class="bi bi-tools me-2"></i>Registrar reparaciones</span><span class="badge {{ $canManageInventory ? 'text-bg-success' : 'text-bg-secondary' }}">{{ $canManageInventory ? 'Disponible con tu rol' : 'Técnico, director técnico o superadmin' }}</span></div><div class="card-body"><ol class="mb-0 ps-3"><li>Abre <strong>Reparaciones</strong> y selecciona <strong>Registrar reparación</strong>.</li><li>Busca y selecciona el activo.</li><li>Describe la falla y adjunta la ficha técnica Word obligatoria.</li><li>Guarda para que el equipo técnico pueda revisar el caso.</li></ol></div></article></div>
    <div class="col-lg-6" id="bajas"><article class="card h-100"><div class="card-header d-flex justify-content-between gap-2"><span><i class="bi bi-clipboard-x me-2"></i>Solicitar baja de un activo</span><span class="badge {{ $canRequestDisposal ? 'text-bg-success' : 'text-bg-secondary' }}">{{ $canRequestDisposal ? 'Disponible con tu rol' : 'Técnico, director técnico o superadmin' }}</span></div><div class="card-body"><ol class="mb-0 ps-3"><li>Abre <strong>Bajas de activos</strong>.</li><li>Busca el activo y selecciónalo en la lista.</li><li>Escribe el motivo y diagnóstico.</li><li>Envía la solicitud para su revisión y resolución.</li></ol></div></article></div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const tutorials = {
        scanner: {
            name: 'Escáner', url: @json(route('scan.index')), action: 'Ir a Escáner',
            steps: [
                ['Abrir Escáner', 'En el menú izquierdo selecciona Escanear.'],
                ['Ingresar el código', 'Escanea o escribe el código de barras, activo fijo o número de serie.'],
                ['Buscar', 'Presiona Buscar para consultar el registro.'],
                ['Revisar el resultado', 'Abre la ficha del activo o producto para ver su información completa.'],
            ],
        },
        activos: {
            name: 'Activos', url: @json(route('assets.index')), action: 'Ir a Activos',
            steps: [
                ['Abrir Activos', 'En el menú selecciona Activos.'],
                ['Crear un registro', 'Selecciona Nuevo activo para registrar un equipo individual.'],
                ['Completar información', 'Indica activo fijo, tipo, uso, ubicación o responsable, marca, modelo y número de serie.'],
                ['Guardar y verificar', 'Guarda el activo y búscalo luego desde Escáner usando su activo fijo o número de serie.'],
            ],
        },
        productos: {
            name: 'Productos', url: @json(route('products.index')), action: 'Ir a Productos',
            steps: [
                ['Abrir Productos', 'En el menú selecciona Productos.'],
                ['Buscar o crear', 'Busca por nombre, código o número de parte. Si no existe, selecciona Nuevo producto.'],
                ['Ingresar stock', 'Al crear un producto, indica la cantidad inicial y la bodega donde quedará disponible.'],
                ['Controlar existencias', 'Usa Movimientos para registrar entradas, salidas y traslados, manteniendo el stock actualizado.'],
            ],
        },
        excel: {
            name: 'Importar Excel', url: @json(route('events.index')), action: 'Ir a Eventos',
            steps: [
                ['Abrir Eventos', 'En el menú selecciona Eventos.'],
                ['Elegir Importar Excel', 'Presiona el botón Importar Excel.'],
                ['Seleccionar archivo y año', 'Carga la planificación, revisa que corresponda y selecciona el año.'],
                ['Registrar y revisar', 'Presiona Registrar eventos y luego selecciona una fecha del calendario para ver los detalles.'],
            ],
        },
        movimientos: {
            name: 'Movimientos', url: @json(route('movements.create')), action: 'Ir a Movimientos',
            steps: [
                ['Abrir Movimientos', 'En el menú selecciona Movimientos y luego Registrar movimiento.'],
                ['Buscar y seleccionar', 'Busca el producto o activo que vas a mover y selecciónalo.'],
                ['Completar el traslado', 'Indica tipo de movimiento, origen, destino, receptor y motivo.'],
                ['Guardar', 'Revisa los datos y registra el movimiento para que quede en el historial.'],
            ],
        },
        reparaciones: {
            name: 'Reparaciones', url: @json(route('repairs.index')), action: 'Ir a Reparaciones',
            steps: [
                ['Abrir Reparaciones', 'En el menú selecciona Reparaciones.'],
                ['Registrar reparación', 'Selecciona Registrar reparación y busca el activo afectado.'],
                ['Describir la falla', 'Indica prioridad, describe el problema y agrega imágenes como evidencia cuando sea necesario.'],
                ['Revisar el caso', 'El equipo técnico podrá ver, actualizar y finalizar la reparación desde su historial.'],
            ],
        },
        bajas: {
            name: 'Bajas de activos', url: @json(route('asset-disposals.index')), action: 'Ir a Bajas de activos',
            steps: [
                ['Abrir Bajas de activos', 'En el menú selecciona Bajas de activos.'],
                ['Buscar el activo', 'Escribe código, serie, marca o modelo y selecciona el activo correcto.'],
                ['Completar la solicitud', 'Indica el motivo y el diagnóstico que justifican la baja.'],
                ['Enviar para revisión', 'Envía la solicitud. El director técnico o superadministrador la revisará y registrará la resolución.'],
            ],
        },
    };
    const buttons = document.querySelectorAll('[data-tutorial]');
    const progress = document.getElementById('tutorial-progreso');
    const name = document.getElementById('tutorial-nombre');
    const title = document.getElementById('tutorial-titulo');
    const description = document.getElementById('tutorial-descripcion');
    const previous = document.getElementById('tutorial-anterior');
    const next = document.getElementById('tutorial-siguiente');
    const action = document.getElementById('tutorial-ir');
    let tutorial = 'scanner';
    let step = 0;

    const render = () => {
        const current = tutorials[tutorial];
        const [stepTitle, stepDescription] = current.steps[step];
        progress.textContent = `Paso ${step + 1} de ${current.steps.length}`;
        name.textContent = current.name;
        title.textContent = stepTitle;
        description.textContent = stepDescription;
        previous.disabled = step === 0;
        next.classList.toggle('d-none', step === current.steps.length - 1);
        action.classList.toggle('d-none', step !== current.steps.length - 1);
        action.href = current.url;
        action.firstChild.textContent = current.action;
    };

    buttons.forEach((button) => button.addEventListener('click', () => {
        tutorial = button.dataset.tutorial;
        step = 0;
        buttons.forEach((item) => item.classList.toggle('btn-primary', item === button));
        buttons.forEach((item) => item.classList.toggle('btn-outline-primary', item !== button));
        render();
    }));
    previous.addEventListener('click', () => { step = Math.max(0, step - 1); render(); });
    next.addEventListener('click', () => { step = Math.min(tutorials[tutorial].steps.length - 1, step + 1); render(); });
    render();
});
</script>
@endsection
