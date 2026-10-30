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