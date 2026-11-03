function abrirModal(modal) {
    if (!modal) return;

    if (modal instanceof HTMLDialogElement) {
        if (!modal.open) modal.showModal();
        return;
    }

    modal.classList.add('mostrar');
}

function cerrarModal(modal) {
    if (!modal) return;

    if (modal instanceof HTMLDialogElement) {
        if (modal.open) modal.close();
        return;
    }

    modal.classList.remove('mostrar');
}

document.addEventListener('click', (event) => {
    const trigger = event.target.closest('[data-alert], [data-modal-open], [data-modal-close]');
    if (!trigger) return;

    if (trigger.dataset.alert) {
        window.alert(trigger.dataset.alert);
    }

    if (trigger.dataset.modalClose) {
        cerrarModal(document.getElementById(trigger.dataset.modalClose));
    }

    if (trigger.dataset.modalOpen) {
        abrirModal(document.getElementById(trigger.dataset.modalOpen));
    }
});

function crearIconoPasswordVisible(mostrar) {
    const icono = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
    icono.setAttribute('xmlns', 'http://www.w3.org/2000/svg');
    icono.setAttribute('width', '24');
    icono.setAttribute('height', '24');
    icono.setAttribute('viewBox', '0 0 24 24');
    icono.setAttribute('fill', 'none');
    icono.setAttribute('stroke', 'currentColor');
    icono.setAttribute('stroke-width', '1.25');
    icono.setAttribute('stroke-linecap', 'round');
    icono.setAttribute('stroke-linejoin', 'round');
    icono.setAttribute('class', `icon icon-tabler icons-tabler-outline icon-tabler-eye${mostrar ? '' : '-closed'}`);

    const trazos = mostrar
        ? [
            'M10 12a2 2 0 1 0 4 0a2 2 0 0 0 -4 0',
            'M21 12c-2.4 4 -5.4 6 -9 6c-3.6 0 -6.6 -2 -9 -6c2.4 -4 5.4 -6 9 -6c3.6 0 6.6 2 9 6'
        ]
        : [
            'M0 0h24v24H0z',
            'M21 9c-2.4 2.667 -5.4 4 -9 4c-3.6 0 -6.6 -1.333 -9 -4',
            'M3 15l2.5 -3.8',
            'M21 14.976l-2.492 -3.776',
            'M9 17l.5 -4',
            'M15 17l-.5 -4'
        ];

    trazos.forEach((trazo, indice) => {
        const path = document.createElementNS('http://www.w3.org/2000/svg', 'path');
        if (!mostrar && indice === 0) path.setAttribute('stroke', 'none');
        path.setAttribute('d', trazo);
        if (!mostrar && indice === 0) path.setAttribute('fill', 'none');
        icono.append(path);
    });

    return icono;
}

function inicializarControlesPassword() {
    document.querySelectorAll('input[type="password"][data-password-toggle]').forEach((input) => {
        const control = document.createElement('span');
        control.className = 'control-password';

        const boton = document.createElement('button');
        boton.type = 'button';
        boton.className = 'boton-ver-password';
        boton.setAttribute('aria-label', 'Mostrar contraseña');
        boton.title = 'Mostrar contraseña';
        boton.append(crearIconoPasswordVisible(false));

        input.parentNode.insertBefore(control, input);
        control.append(input, boton);

        boton.addEventListener('click', () => {
            const mostrar = input.type === 'password';
            input.type = mostrar ? 'text' : 'password';
            boton.setAttribute('aria-label', mostrar ? 'Ocultar contraseña' : 'Mostrar contraseña');
            boton.title = mostrar ? 'Ocultar contraseña' : 'Mostrar contraseña';
            boton.replaceChildren(crearIconoPasswordVisible(mostrar));
        });
    });
}

function mostrarAvisoPasswordActualizada() {
    const urlParams = new URLSearchParams(window.location.search);
    const esPanelAutorizado = document.querySelector('.layout-secretario, .layout-profesional, .body-dashboard-paciente');

    if (urlParams.get('password_actualizada') !== '1' || !esPanelAutorizado) return;

    const modal = document.getElementById('modalPasswordActualizada');
    if (!modal) {
        console.error('No se encontró el diálogo de confirmación de contraseña.');
        return;
    }

    modal.showModal();
    urlParams.delete('password_actualizada');
    const query = urlParams.toString();
    const urlLimpia = `${window.location.pathname}${query ? `?${query}` : ''}${window.location.hash}`;
    window.history.replaceState(window.history.state, document.title, urlLimpia);
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', inicializarControlesPassword, { once: true });
} else {
    inicializarControlesPassword();
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', mostrarAvisoPasswordActualizada, { once: true });
} else {
    mostrarAvisoPasswordActualizada();
}