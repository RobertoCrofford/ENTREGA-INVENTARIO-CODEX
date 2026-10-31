<script>
    document.addEventListener('DOMContentLoaded', () => {
        const form = document.getElementById('groupMovementForm');
        const cartField = document.getElementById('itemsJson');
        const search = document.getElementById('groupSearch');
        const quantity = document.getElementById('itemQty');
        const addButton = document.getElementById('addItem');
        if (!form || !cartField || !search || !quantity || !addButton) return;

        const key = 'inventario-movimiento-grupal-borrador-{{ auth()->id() }}';
        const draftUrl = @json(route('movements.group.draft'));
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
        const fields = ['origen_id', 'destino_id', 'receptor_nombre', 'receptor_email', 'receptor_departamento', 'observacion'];
        const availableItems = [
            @foreach ($products as $product)
                { type: 'product', id: '{{ $product->id }}', search: @json($product->nombre . ' ' . $product->codigo_interno . ' ' . $product->numero_parte . ' ' . $product->marca . ' ' . $product->modelo) },
            @endforeach
            @foreach ($assets as $asset)
                { type: 'asset', id: '{{ $asset->id }}', search: @json($asset->activo_fijo . ' ' . $asset->numero_serie . ' ' . $asset->marca . ' ' . $asset->modelo) },
            @endforeach
        ];
        let draft = {};
        let syncTimer;
        try { draft = JSON.parse(localStorage.getItem(key) || '{}'); } catch (_) { draft = {}; }

        const save = () => {
            let items = [];
            try { items = JSON.parse(cartField.value || '[]'); } catch (_) { items = []; }
            const values = {};
            fields.forEach(name => { values[name] = form.elements[name]?.value || ''; });
            const record = { items, values };
            localStorage.setItem(key, JSON.stringify(record));
            window.clearTimeout(syncTimer);
            syncTimer = window.setTimeout(() => {
                fetch(draftUrl, {
                    method: 'PUT',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken },
                    credentials: 'same-origin',
                    body: JSON.stringify({ draft: record }),
                }).catch(() => {});
            }, 300);
        };
        const restoreValues = () => {
            Object.entries(draft.values || {}).forEach(([name, value]) => {
                if (form.elements[name]) form.elements[name].value = value;
            });
        };
        const restoreCart = () => {
            const storedItems = Array.isArray(draft.items) ? draft.items : [];
            storedItems.forEach(stored => {
                const index = availableItems.findIndex(item => item.type === stored.type && String(item.id) === String(stored.id));
                if (index < 0) return;
                search.value = availableItems[index].search;
                search.dispatchEvent(new Event('input', { bubbles: true }));
                const option = document.querySelector(`#groupResults [data-i="${index}"]`);
                if (!option) return;
                option.click();
                quantity.value = stored.type === 'asset' ? 1 : Math.max(1, Number(stored.quantity) || 1);
                addButton.click();
            });
        };

        restoreValues();
        restoreCart();
        save();
        if (!Array.isArray(draft.items) || draft.items.length === 0) {
            fetch(draftUrl, { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
                .then(response => response.ok ? response.json() : null)
                .then(response => {
                    if (!response?.draft || !Array.isArray(response.draft.items) || response.draft.items.length === 0) return;
                    draft = response.draft;
                    restoreValues();
                    restoreCart();
                    save();
                })
                .catch(() => {});
        }
        form.addEventListener('input', save);
        form.addEventListener('change', save);
        window.addEventListener('pagehide', save);
        document.addEventListener('visibilitychange', () => { if (document.hidden) save(); });
        window.setInterval(save, 750);
    });
</script>
