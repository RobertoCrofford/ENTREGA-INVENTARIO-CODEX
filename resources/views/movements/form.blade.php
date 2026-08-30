@extends('layouts.app')
@section('content')
<h1 class="h3">Registrar movimiento</h1>
@if($products->isEmpty() || $warehouses->isEmpty())
    <div class="alert alert-warning">
        @if($products->isEmpty())
            Primero registra al menos un producto.
        @endif
        @if($warehouses->isEmpty())
            Registra una sede y una ubicación de tipo bodega antes de publicar movimientos.
        @endif
    </div>
@endif
<form method="POST" class="card" action="{{ route('movements.store') }}"><div class="card-body">@csrf @if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif<input type="hidden" name="idempotency_key" value="{{ (string) Str::uuid() }}"><div class="row g-3"><div class="col-md-4"><label class="form-label">Tipo</label><select name="tipo" class="form-select">@foreach(['entrada','salida','devolucion','traslado','ajuste','baja'] as $type)<option>{{ $type }}</option>@endforeach</select></div><div class="col-md-5"><label class="form-label" for="buscar_producto">Buscar producto</label><input class="form-control" id="buscar_producto" type="search" placeholder="Nombre o código interno" autocomplete="off" @disabled($products->isEmpty())><div id="resultado_busqueda_producto" class="form-text" aria-live="polite">Escribe para buscar por nombre o código.</div></div><div class="col-md-3"><label class="form-label">Cantidad</label><input class="form-control" type="number" min="1" name="cantidad" required></div><div class="col-md-7"><label class="form-label" for="producto_id">Producto seleccionado</label><select id="producto_id" name="producto_id" class="form-select" size="5" required @disabled($products->isEmpty())><option value="">Selecciona un producto</option>@foreach($products as $product)<option value="{{ $product->id }}" data-search="{{ $product->nombre }} {{ $product->codigo_interno }}" @selected(old('producto_id') == $product->id)>{{ $product->nombre }} ({{ $product->codigo_interno }})</option>@endforeach</select><div id="producto_seleccionado" class="form-text">{{ old('producto_id') ? 'Producto preparado para el movimiento.' : 'El producto elegido aquí recibirá la entrada, salida, traslado o ajuste.' }}</div>@error('producto_id')<div class="text-danger small mt-1">{{ $message }}</div>@enderror</div><div class="col-md-5"><label class="form-label">Bodega origen</label><select class="form-select" name="origen_id"><option value="">No aplica</option>@foreach($warehouses as $warehouse)<option value="{{ $warehouse->id }}">{{ $warehouse->nombre }}</option>@endforeach</select><label class="form-label mt-3">Bodega destino</label><select class="form-select" name="destino_id"><option value="">No aplica</option>@foreach($warehouses as $warehouse)<option value="{{ $warehouse->id }}">{{ $warehouse->nombre }}</option>@endforeach</select></div><div class="col-md-6"><label class="form-label">Tipo receptor (salida)</label><select name="receptor_tipo" class="form-select"><option value="">No aplica</option><option value="funcionario">Funcionario</option><option value="sala">Sala</option><option value="unidad">Unidad</option><option value="consumo_tecnico">Consumo técnico</option></select></div><div class="col-md-6"><label class="form-label">Nombre receptor</label><input class="form-control" name="receptor_nombre"></div><div class="col-md-6"><label class="form-label">Correo receptor</label><input class="form-control" type="email" name="receptor_email"></div><div class="col-md-6"><label class="form-label">Departamento</label><input class="form-control" name="receptor_departamento"></div><div class="col-12"><label class="form-label">Motivo</label><textarea class="form-control" name="motivo" required></textarea></div><div class="col-12"><label class="form-label">Observación</label><textarea class="form-control" name="observacion"></textarea></div></div></div><div class="card-footer"><button class="btn btn-primary" @disabled($products->isEmpty() || $warehouses->isEmpty())>Publicar</button><a class="btn btn-link" href="{{ route('movements.index') }}">Cancelar</a></div></form>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        const search = document.getElementById('buscar_producto');
        const products = document.getElementById('producto_id');
        if (!search || !products) return;

        const feedback = document.getElementById('resultado_busqueda_producto');
        const selectedFeedback = document.getElementById('producto_seleccionado');
        const options = Array.from(products.options).filter((option) => option.value);
        const normalize = (value) => value.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase();

        search.addEventListener('input', () => {
            const term = normalize(search.value.trim());
            const matches = options.filter((option) => term === '' || normalize(option.dataset.search).includes(term));
            options.forEach((option) => option.hidden = !matches.includes(option));

            if (term === '') {
                feedback.textContent = `${options.length} producto(s) disponible(s).`;
                return;
            }

            feedback.textContent = `${matches.length} resultado(s) encontrado(s).`;
            if (matches.length === 1) {
                products.value = matches[0].value;
                selectedFeedback.textContent = `Producto seleccionado: ${matches[0].text}.`;
            }
        });

        products.addEventListener('change', () => {
            const selected = products.options[products.selectedIndex];
            selectedFeedback.textContent = selected?.value ? `Producto seleccionado: ${selected.text}.` : 'Selecciona el producto que recibirá el movimiento.';
        });
    });
</script>
@endsection
