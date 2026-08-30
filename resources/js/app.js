import 'bootstrap';

const fieldRules = {
    numero_parte: { placeholder: 'Ej: SN-AB12-3456', pattern: '[A-Za-z0-9-]+', maxLength: 40, title: 'Solo letras, números y guiones.' },
    codigo: { placeholder: 'Ej: PRD-0000123 o ABC123', pattern: '[A-Za-z0-9-]+', maxLength: 40, title: 'Solo letras, números y guiones.' },
    activo_fijo: { placeholder: 'Ej: AF-2026-001', pattern: '[A-Za-z0-9-]+', maxLength: 40, title: 'Solo letras, números y guiones.' },
    numero_serie: { placeholder: 'Ej: SN-AB12-3456', pattern: '[A-Za-z0-9-]+', maxLength: 40, title: 'Solo letras, números y guiones.' },
    nombre: { placeholder: 'Ej: Notebook Lenovo ThinkPad', maxLength: 255 },
    marca: { placeholder: 'Ej: Lenovo', maxLength: 80 },
    modelo: { placeholder: 'Ej: ThinkPad E14 Gen 5', maxLength: 80 },
    descripcion: { placeholder: 'Ej: Equipo destinado a laboratorio de informática.', maxLength: 2000 },
    observacion: { placeholder: 'Ej: Entregado con cargador y bolso.', maxLength: 2000 },
    motivo: { placeholder: 'Ej: Reposición por daño del equipo anterior.', maxLength: 1000 },
    receptor_nombre: { placeholder: 'Ej: Sala B-201 o Ana Pérez', maxLength: 120 },
    receptor_departamento: { placeholder: 'Ej: Escuela de Informática', maxLength: 120 },
    responsable_nombre: { placeholder: 'Ej: Ana Pérez', maxLength: 120 },
    responsable_departamento: { placeholder: 'Ej: Soporte TI', maxLength: 120 },
    email: { placeholder: 'Ej: nombre@institucion.cl', maxLength: 255 },
    receptor_email: { placeholder: 'Ej: ana.perez@institucion.cl', maxLength: 255 },
    responsable_email: { placeholder: 'Ej: ana.perez@institucion.cl', maxLength: 255 },
    username: { placeholder: 'Ej: aperez', pattern: '[A-Za-z0-9_-]+', minLength: 3, maxLength: 80, title: 'Usa letras, números, guiones o guion bajo.' },
    password: { placeholder: 'Mínimo 12 caracteres, mayúscula y número', minLength: 12 },
    password_confirmation: { placeholder: 'Repite la contraseña', minLength: 12 },
    cantidad: { placeholder: 'Ej: 10', min: 1, max: 100000 },
    costo_neto_actual: { placeholder: 'Ej: 459990', min: 0, step: '0.01' },
};

document.querySelectorAll('input[name], textarea[name]').forEach((field) => {
    const isUserForm = field.form?.action.includes('/users');
    const rule = field.name === 'name' && isUserForm
        ? { placeholder: 'Ej: Ana Pérez', maxLength: 120, pattern: "[A-Za-zÀ-ÿ .'’-]+", title: 'Usa solo letras, espacios, apóstrofes o guiones.' }
        : fieldRules[field.name];
    if (!rule) return;
    Object.entries(rule).forEach(([attribute, value]) => {
        field[attribute] = value;
    });
});

document.querySelectorAll('[data-notification-read-url]').forEach((button) => {
    button.addEventListener('shown.bs.dropdown', () => {
        const badge = button.querySelector('.notification-dot');
        if (!badge) return;

        fetch(button.dataset.notificationReadUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
            },
        }).then((response) => {
            if (response.ok) badge.remove();
        }).catch(() => {});
    });
});
