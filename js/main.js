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

function mostrarErrorLogin() {
    const mensaje = document.getElementById('mensajeErrorLogin');
    if (!mensaje) return;

    const urlParams = new URLSearchParams(window.location.search);
    const error = urlParams.get('error');
    if (!error) return;

    const textos = {
        credenciales_invalidas: 'El usuario o la contraseña son incorrectos. Verifique sus datos e intente nuevamente.',
        rol_desconocido: 'Su cuenta no tiene un rol válido asignado. Contacte a la clínica.',
        solicitud_invalida: 'La página venció. Intente iniciar sesión nuevamente.'
    };

    mensaje.textContent = textos[error] || 'No se pudo iniciar sesión. Intente nuevamente.';
    mensaje.hidden = false;

    // limpio el parámetro para que el aviso no reaparezca al recargar
    urlParams.delete('error');
    const query = urlParams.toString();
    const urlLimpia = `${window.location.pathname}${query ? `?${query}` : ''}${window.location.hash}`;
    window.history.replaceState(window.history.state, document.title, urlLimpia);

    // al volver a escribir, oculto el aviso
    ['dni', 'password'].forEach((id) => {
        const input = document.getElementById(id);
        if (input) input.addEventListener('input', () => { mensaje.hidden = true; }, { once: true });
    });
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', mostrarErrorLogin, { once: true });
} else {
    mostrarErrorLogin();
}
// ===== Token CSRF para páginas .html estáticas =====
// Las páginas PHP escriben el token directamente en sus formularios. Las páginas
// .html no pueden, así que sus formularios traen un campo vacío marcado con
// data-csrf-dinamico y acá se completa pidiendo el token al servidor por POST.
const rutaObtenerCsrf = (() => {
    const scriptMain = document.querySelector('script[src*="js/main.js"]');
    const base = scriptMain ? scriptMain.src : window.location.href;
    return new URL('../procesos/obtener-csrf.php', base).toString();
})();

let promesaTokenCsrf = null;

function obtenerTokenCsrf() {
    if (!promesaTokenCsrf) {
        promesaTokenCsrf = fetch(rutaObtenerCsrf, {
            method: 'POST',
            credentials: 'same-origin',
            cache: 'no-store'
        })
            .then((respuesta) => {
                if (!respuesta.ok) throw new Error(`HTTP ${respuesta.status}`);
                return respuesta.json();
            })
            .then((datos) => datos.csrf_token || '')
            .catch((error) => {
                console.error('No se pudo obtener el token de seguridad.', error);
                promesaTokenCsrf = null; // permito reintentar
                return '';
            });
    }
    return promesaTokenCsrf;
}
window.obtenerTokenCsrf = obtenerTokenCsrf;

function inicializarTokensCsrfDinamicos() {
    const campos = document.querySelectorAll('input[name="csrf_token"][data-csrf-dinamico]');
    if (campos.length === 0) return;

    obtenerTokenCsrf().then((token) => {
        campos.forEach((campo) => { campo.value = token; });
    });

    // si el usuario envía el formulario antes de que llegue el token, espero y reenvío
    campos.forEach((campo) => {
        const formulario = campo.form;
        if (!formulario) return;
        formulario.addEventListener('submit', async (evento) => {
            if (campo.value) return;
            evento.preventDefault();
            campo.value = await obtenerTokenCsrf();
            if (campo.value) formulario.requestSubmit();
        });
    });
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', inicializarTokensCsrfDinamicos, { once: true });
} else {
    inicializarTokensCsrfDinamicos();
}
