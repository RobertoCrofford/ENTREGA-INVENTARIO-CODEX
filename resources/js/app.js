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
    const menu = button.closest('[data-notification-refresh-url]');
    const items = menu?.querySelector('[data-notification-items]');
    const headerCount = menu?.querySelector('.notification-dropdown-header span');
    const refreshNotifications = async () => {
        if (!menu || !items) return;

        try {
            const response = await fetch(menu.dataset.notificationRefreshUrl, {
                credentials: 'same-origin',
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            });
            if (!response.ok) return;

            const data = await response.json();
            let badge = button.querySelector('.notification-dot');
            if (data.unread > 0) {
                if (!badge) {
                    badge = document.createElement('span');
                    badge.className = 'notification-dot';
                    button.append(badge);
                }
                badge.textContent = data.unread;
            } else {
                badge?.remove();
            }
            if (headerCount) headerCount.textContent = `Últimas ${data.notifications.length}`;
            items.replaceChildren();
            if (data.notifications.length === 0) {
                const empty = document.createElement('div');
                empty.className = 'notification-dropdown-empty';
                empty.textContent = 'No tienes notificaciones.';
                items.append(empty);
                return;
            }
            data.notifications.forEach((notification) => {
                const item = document.createElement(notification.open_url ? 'a' : 'div');
                item.className = `notification-dropdown-item${notification.leido_at ? '' : ' is-unread'}`;
                if (notification.open_url) {
                    item.classList.add('notification-dropdown-link');
                    item.href = notification.open_url;
                }
                const heading = document.createElement('div');
                heading.className = 'd-flex justify-content-between gap-2';
                const title = document.createElement('strong');
                title.textContent = notification.titulo;
                const time = document.createElement('time');
                time.textContent = new Intl.DateTimeFormat('es-CL', { day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' }).format(new Date(notification.creado_at));
                const message = document.createElement('p');
                message.textContent = notification.mensaje;
                heading.append(title, time);
                item.append(heading, message);
                if (notification.open_url) {
                    const label = document.createElement('span');
                    label.className = 'notification-open-label';
                    label.textContent = 'Abrir evento →';
                    item.append(label);
                }
                items.append(item);
            });
        } catch (_) {}
    };
    refreshNotifications();
    window.setInterval(refreshNotifications, 15000);
});

document.querySelectorAll('form').forEach((form) => {
    form.addEventListener('submit', (event) => {
        if (form.dataset.confirmMessage && !window.confirm(form.dataset.confirmMessage)) {
            event.preventDefault();
            return;
        }
        const submitButton = form.querySelector('button[type="submit"], button:not([type])');
        if (!submitButton || submitButton.disabled) return;

        submitButton.disabled = true;
        submitButton.insertAdjacentHTML('afterbegin', '<span class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>');
    }, { once: true });
});

document.querySelectorAll('form[data-long-running-form]').forEach((form) => {
    form.addEventListener('submit', () => {
        form.querySelectorAll('[data-long-running-message]').forEach((message) => message.classList.remove('d-none'));
        const warnBeforeLeaving = (event) => {
            event.preventDefault();
            event.returnValue = '';
        };
        window.addEventListener('beforeunload', warnBeforeLeaving, { once: true });
    }, { once: true });
});

document.querySelectorAll('[data-password-toggle]').forEach((button) => {
    const input = document.getElementById(button.getAttribute('aria-controls'));
    const icon = button.querySelector('i');
    if (!input || !icon) return;

    button.addEventListener('click', () => {
        const showPassword = input.type === 'password';
        input.type = showPassword ? 'text' : 'password';
        icon.classList.toggle('bi-eye', !showPassword);
        icon.classList.toggle('bi-eye-slash', showPassword);
        button.setAttribute('aria-label', showPassword ? 'Ocultar contraseña' : 'Mostrar contraseña');
        button.setAttribute('title', showPassword ? 'Ocultar contraseña' : 'Mostrar contraseña');
    });
});
