(() => {
    const modal = document.getElementById('modalCancelarTurnoPaciente');
    if (!modal) return;

    const turnoId = document.getElementById('turno-paciente-id');
    const fechaTurno = document.getElementById('fecha-cancelar-turno-paciente');
    const cerrarModal = document.getElementById('cerrar-modal-cancelar-turno-paciente');

    document.addEventListener('click', (evento) => {
        const boton = evento.target.closest('.btn-cancelar-turno-paciente');
        if (!boton) return;

        turnoId.value = boton.dataset.turnoId;
        fechaTurno.textContent = `${boton.dataset.turnoFecha} hs (Lic. ${boton.dataset.profesional})`;
        modal.showModal();
    });

    cerrarModal.addEventListener('click', () => modal.close());
})();

(() => {
    if (!(document.getElementById('modalCancelarTurnoExcepcion'))) return;

const modalCancelarTurnoExcepcion = document.getElementById('modalCancelarTurnoExcepcion');
        const nombrePacienteExcepcion = document.getElementById('nombre-paciente-excepcion');
        const textoModalCancelacionExcepcion = document.getElementById('texto-modal-excepcion');
        const enlaceWhatsappExcepcion = document.getElementById('enlace-whatsapp-excepcion');
        const modalBloquearDiaExcepcion = document.getElementById('modalBloquearDiaExcepcion');
        const listaPacientesAfectados = document.getElementById('lista-pacientes-afectados');
        const botonBloquearDia = document.querySelector('.btn-bloquear-dia-completo');
        const panelHorariosExcepcion = document.querySelector('.panel-horarios-excepcion');
        const estadoBloqueoDia = document.getElementById('estado-bloqueo-dia');
        const modalConfirmarEstadoFranja = document.getElementById('modalConfirmarEstadoFranja');
        const tituloConfirmacionFranja = document.getElementById('titulo-confirmacion-franja');
        const textoConfirmacionFranja = document.getElementById('texto-confirmacion-franja');
        const botonConfirmarEstadoFranja = document.getElementById('confirmar-estado-franja');
        const botonAgregarTurnoExtra = panelHorariosExcepcion.querySelector('.btn-dar-turno-mini');
        const modalTurnoExtra = document.getElementById('modalTurnoExtra');
        const formularioTurnoExtra = document.getElementById('formulario-turno-extra');
        const selectorEstadoTurnoExtra = document.getElementById('estado-turno-extra');
        const datosPacienteTurnoExtra = document.getElementById('datos-paciente-turno-extra');
        const errorTurnoExtra = document.getElementById('error-turno-extra');
        let franjaPendiente = null;
        let accionPendiente = null;

        const obtenerTurnosOcupados = () => Array.from(
            panelHorariosExcepcion.querySelectorAll('.franja-ocupada')
        );

        const bloquearDiaCompleto = () => {
            const cantidadTurnosOcupados = obtenerTurnosOcupados().length;
            panelHorariosExcepcion.querySelectorAll('.item-franja-estado:not(.franja-bloqueada)').forEach((franja) => {
                const turnoOcupado = franja.classList.contains('franja-ocupada');
                const botonCancelar = turnoOcupado
                    ? franja.querySelector('.btn-cancelar-turno-excepcion')
                    : null;
                const paciente = turnoOcupado
                    ? botonCancelar.dataset.paciente
                    : '';
                const enlaceWhatsapp = turnoOcupado ? crearEnlaceWhatsapp(botonCancelar) : null;
                const badge = franja.querySelector('.badge-estado');
                const botonAccion = franja.querySelector('.btn-accion-linea');

                franja.classList.remove('franja-ocupada');
                franja.classList.add('franja-bloqueada');
                badge.classList.remove('badge-disponible', 'badge-ocupado');
                badge.classList.add('badge-bloqueado');
                badge.textContent = 'Bloqueado';
                actualizarDetalleFranja(franja, turnoOcupado
                    ? `Turno cancelado: ${paciente}`
                    : 'Por bloqueo del día completo');
                if (botonAccion) botonAccion.remove();
                if (enlaceWhatsapp) {
                    enlaceWhatsapp.classList.add('btn-accion-linea');
                    franja.append(enlaceWhatsapp);
                }
            });
            panelHorariosExcepcion.querySelectorAll('.franja-bloqueada .btn-desbloquear').forEach((boton) => {
                boton.remove();
            });

            botonBloquearDia.disabled = true;
            botonAgregarTurnoExtra.disabled = true;
            botonBloquearDia.textContent = 'Día bloqueado';
            estadoBloqueoDia.textContent = cantidadTurnosOcupados > 0
                ? 'El día quedó bloqueado. Enviá los avisos a los pacientes desde los botones de WhatsApp en sus turnos cancelados.'
                : 'El día quedó bloqueado. No había turnos ocupados que deban notificarse.';
        };

        const crearEnlaceWhatsapp = (turno) => {
            const { paciente, telefono, fecha, hora, fin } = turno.dataset;
            if (!telefono) return null;

            const horario = fin ? `de ${hora} a ${fin}` : `a las ${hora}`;
            const mensaje = `Hola ${paciente}, te escribimos de Pausa para avisarte que tuvimos que cancelar tu turno del ${fecha} ${horario}. Podés solicitar otro turno cuando quieras. Disculpá las molestias.`;
            const enlace = document.createElement('a');
            enlace.className = 'btn-whatsapp-modal';
            enlace.href = `https://wa.me/${telefono}?text=${encodeURIComponent(mensaje)}`;
            enlace.target = '_blank';
            enlace.rel = 'noopener noreferrer';
            if (turno.dataset.turnoId) enlace.dataset.turnoId = turno.dataset.turnoId;
            enlace.textContent = 'Enviar WhatsApp de cancelación';
            enlace.setAttribute('aria-label', `Enviar WhatsApp de cancelación a ${paciente}`);
            return enlace;
        };

        const crearBotonAccion = (texto, clase) => {
            const boton = document.createElement('button');
            boton.type = 'button';
            boton.className = `btn-accion-linea ${clase}`;
            boton.textContent = texto;
            return boton;
        };

        const actualizarDetalleFranja = (franja, texto) => {
            const detalle = franja.querySelector('.detalle-estado');
            const etiqueta = detalle.querySelector('.etiqueta-turno-extra');
            detalle.replaceChildren(document.createTextNode(texto));
            if (etiqueta) detalle.append(etiqueta);
        };

        const crearBotonCancelarTurno = ({ paciente, telefono, fecha, inicio, fin, turnoId }) => {
            const boton = crearBotonAccion('Bloquear turno', 'btn-cancelar-turno-excepcion');
            Object.assign(boton.dataset, {
                paciente,
                telefono,
                fecha,
                hora: inicio,
                fin,
                turnoId: turnoId || ''
            });
            return boton;
        };

        const mostrarModalTurnoExtra = () => {
            if (botonAgregarTurnoExtra.disabled) return;

            formularioTurnoExtra.reset();
            selectorEstadoTurnoExtra.value = 'disponible';
            datosPacienteTurnoExtra.hidden = true;
            document.getElementById('nombre-paciente-extra').required = false;
            document.getElementById('fecha-turno-extra').textContent =
                panelHorariosExcepcion.querySelector('.cabecera-horarios-dia h2').textContent;
            errorTurnoExtra.hidden = true;
            errorTurnoExtra.textContent = '';
            modalTurnoExtra.showModal();
        };

        botonAgregarTurnoExtra.addEventListener('click', mostrarModalTurnoExtra);

        selectorEstadoTurnoExtra.addEventListener('change', () => {
            const asignado = selectorEstadoTurnoExtra.value === 'asignado';
            datosPacienteTurnoExtra.hidden = !asignado;
            document.getElementById('nombre-paciente-extra').required = asignado;
        });

        document.getElementById('cerrar-modal-turno-extra').addEventListener('click', () => {
            modalTurnoExtra.close();
        });

        panelHorariosExcepcion.addEventListener('click', (evento) => {
            const boton = evento.target.closest('.btn-cancelar-turno-excepcion');
            if (!boton) return;

            const { paciente, telefono, fecha, hora, fin, turnoId } = boton.dataset;
            nombrePacienteExcepcion.textContent = paciente;
            enlaceWhatsappExcepcion.dataset.turnoId = turnoId || '';
            enlaceWhatsappExcepcion.dataset.telefono = telefono || '';
            if (telefono) {
                const horario = fin ? `de ${hora} a ${fin}` : `a las ${hora}`;
                const mensaje = `Hola ${paciente}, te escribimos de Pausa para avisarte que tuvimos que cancelar tu turno del ${fecha} ${horario}. Podés solicitar otro turno cuando quieras. Disculpá las molestias.`;
                enlaceWhatsappExcepcion.href = `https://wa.me/${telefono.replace(/\D/g, '')}?text=${encodeURIComponent(mensaje)}`;
                enlaceWhatsappExcepcion.hidden = false;
                enlaceWhatsappExcepcion.style.removeProperty('display');
                enlaceWhatsappExcepcion.textContent = 'Abrir WhatsApp';
                textoModalCancelacionExcepcion.replaceChildren(
                    document.createTextNode('Se abrirá WhatsApp con un mensaje listo para '),
                    nombrePacienteExcepcion,
                    document.createTextNode('. Revisalo y envialo para avisarle que su turno fue cancelado y que ese horario quedó bloqueado.')
                );
            } else {
                enlaceWhatsappExcepcion.hidden = false;
                enlaceWhatsappExcepcion.style.removeProperty('display');
                enlaceWhatsappExcepcion.href = '#';
                enlaceWhatsappExcepcion.textContent = 'Confirmar cancelación';
                textoModalCancelacionExcepcion.replaceChildren(
                    document.createTextNode(`No hay un WhatsApp asociado a ${paciente}. Podés confirmar la cancelación y bloquear ese horario sin enviar un aviso.`)
                );
            }
            modalCancelarTurnoExcepcion.showModal();
        });

        const mostrarConfirmacionFranja = (franja, accion) => {
            franjaPendiente = franja;
            accionPendiente = accion;

            if (accion === 'bloquear') {
                tituloConfirmacionFranja.textContent = '¿Bloquear este turno?';
                textoConfirmacionFranja.textContent =
                    `El turno de las ${franja.querySelector('.hora-franja').textContent} se bloqueará y ningún paciente podrá tomarlo.`;
                botonConfirmarEstadoFranja.textContent = 'Bloquear turno';
                botonConfirmarEstadoFranja.classList.remove('btn-confirmar-desbloqueo');
            } else {
                tituloConfirmacionFranja.textContent = '¿Desbloquear este turno?';
                textoConfirmacionFranja.textContent =
                    `El turno de las ${franja.querySelector('.hora-franja').textContent} volverá a estar disponible para que los pacientes puedan tomarlo.`;
                botonConfirmarEstadoFranja.textContent = 'Desbloquear turno';
                botonConfirmarEstadoFranja.classList.add('btn-confirmar-desbloqueo');
            }

            modalConfirmarEstadoFranja.showModal();
        };

        panelHorariosExcepcion.addEventListener('click', (evento) => {
            const boton = evento.target.closest('.btn-bloquear-individual, .btn-desbloquear');
            if (!boton) return;

            const franja = boton.closest('.item-franja-estado');
            mostrarConfirmacionFranja(franja, boton.classList.contains('btn-bloquear-individual') ? 'bloquear' : 'desbloquear');
        });

        document.getElementById('cancelar-confirmacion-franja').addEventListener('click', () => {
            modalConfirmarEstadoFranja.close();
            franjaPendiente = null;
            accionPendiente = null;
        });

        botonConfirmarEstadoFranja.addEventListener('click', () => {
            if (!franjaPendiente || !accionPendiente) return;

            const badge = franjaPendiente.querySelector('.badge-estado');

            if (accionPendiente === 'bloquear') {
                franjaPendiente.classList.add('franja-bloqueada');
                badge.classList.remove('badge-disponible');
                badge.classList.add('badge-bloqueado');
                badge.textContent = 'Bloqueado';
                actualizarDetalleFranja(franjaPendiente, 'Por excepción manual');
                franjaPendiente.querySelector('.btn-bloquear-individual')?.remove();
                franjaPendiente.append(crearBotonAccion('Desbloquear', 'btn-desbloquear'));
            } else {
                franjaPendiente.classList.remove('franja-bloqueada');
                badge.classList.remove('badge-bloqueado');
                badge.classList.add('badge-disponible');
                badge.textContent = 'Disponible';
                actualizarDetalleFranja(franjaPendiente, 'Turno estándar');
                franjaPendiente.querySelector('.btn-desbloquear')?.remove();
                franjaPendiente.append(crearBotonAccion('Bloquear', 'btn-bloquear-individual'));
            }

            modalConfirmarEstadoFranja.close();
            franjaPendiente = null;
            accionPendiente = null;
        });

        botonBloquearDia.addEventListener('click', () => {
            const turnosOcupados = obtenerTurnosOcupados();

            if (turnosOcupados.length === 0) {
                bloquearDiaCompleto();
                return;
            }

            const cantidad = turnosOcupados.length;
            document.getElementById('titulo-modal-bloqueo').textContent =
                `Atención: este día tiene ${cantidad} ${cantidad === 1 ? 'turno ocupado' : 'turnos ocupados'}`;
            listaPacientesAfectados.replaceChildren();

            turnosOcupados.forEach((franja) => {
                const turno = franja.querySelector('.btn-cancelar-turno-excepcion');
                const item = document.createElement('li');
                const datos = document.createElement('span');
                const horario = turno.dataset.fin
                    ? `${turno.dataset.hora} a ${turno.dataset.fin}`
                    : turno.dataset.hora;
                datos.className = 'datos-paciente-afectado';
                datos.textContent = `${turno.dataset.paciente} (${horario} hs)`;
                item.append(datos);
                const enlaceWhatsapp = crearEnlaceWhatsapp(turno);
                if (enlaceWhatsapp) item.append(enlaceWhatsapp);
                listaPacientesAfectados.append(item);
            });

            modalBloquearDiaExcepcion.showModal();
        });

        document.getElementById('cancelar-bloqueo-dia').addEventListener('click', () => {
            modalBloquearDiaExcepcion.close();
        });

        document.getElementById('confirmar-bloqueo-dia').addEventListener('click', () => {
            bloquearDiaCompleto();
            modalBloquearDiaExcepcion.close();
        });
})();

(() => {
    const calendario = document.querySelector('.panel-calendario-excepcion');
    const panel = document.querySelector('.panel-horarios-excepcion');
    if (!calendario || !panel) return;

    const CLAVE_FECHA = 'pausa.secretaria.fechaExcepcion';
    const selectorProfesional = document.getElementById('profesional');
    const grilla = calendario.querySelector('.grilla-dias');
    const tituloMes = calendario.querySelector('.cabecera-mes h2');
    const tituloDia = panel.querySelector('.cabecera-horarios-dia h2');
    const listaFranjas = panel.querySelector('.lista-franjas-excepcion');
    const botonBloquearDia = calendario.querySelector('.btn-bloquear-dia-completo');
    const botonTurnoExtra = panel.querySelector('.btn-dar-turno-mini');
    const estadoBloqueo = calendario.querySelector('#estado-bloqueo-dia');
    const botonConfirmarEstadoFranja = document.getElementById('confirmar-estado-franja');
    const formularioTurnoExtra = document.getElementById('formulario-turno-extra');
    const errorTurnoExtra = document.getElementById('error-turno-extra');
    const modalTurnoExtra = document.getElementById('modalTurnoExtra');
    const meses = Array.from({ length: 12 }, (_, indice) =>
        new Intl.DateTimeFormat('es-AR', { month: 'long' }).format(new Date(new Date().getFullYear(), indice, 1))
    );
    const fechaOriginal = new Date();
    fechaOriginal.setHours(12, 0, 0, 0);
    let mes = fechaOriginal.getMonth();
    let anio = fechaOriginal.getFullYear();
    let fechaSeleccionada = fechaOriginal;
    const textoDetalle = (franja) => {
        const detalle = franja.querySelector('.detalle-estado').cloneNode(true);
        detalle.querySelector('.etiqueta-turno-extra')?.remove();
        return detalle.textContent.trim();
    };
    const botonesNavegacion = calendario.querySelectorAll('.btn-nav-mes');
    const tokenCsrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
    const rutaProcesador = '../procesos/secretario/procesar-excepciones.php';
    let accionFranjaPendiente = null;
    let diaCompletoBloqueado = false;
    let solicitudCargaDia = 0;
    let diasConTurnos = new Set();
    let solicitudCargaMes = 0;

    function fechaISO(fecha) {
        return `${fecha.getFullYear()}-${String(fecha.getMonth() + 1).padStart(2, '0')}-${String(fecha.getDate()).padStart(2, '0')}`;
    }

    function fechaDeHoy() {
        const hoy = new Date();
        hoy.setHours(0, 0, 0, 0);
        return hoy;
    }

    function esFechaPasada(fecha) {
        return fechaISO(fecha) < fechaISO(fechaDeHoy());
    }

    function fechaLegible(fecha, opciones) {
        const texto = new Intl.DateTimeFormat('es-AR', opciones).format(fecha);
        return texto.charAt(0).toLocaleUpperCase('es-AR') + texto.slice(1);
    }

    async function enviarAccion(datos) {
        const formulario = new URLSearchParams({
            ...datos,
            csrf_token: tokenCsrf,
            profesional_id: selectorProfesional.value,
            response: 'json'
        });
        const respuesta = await fetch(rutaProcesador, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8' },
            body: formulario
        });
        const resultado = await respuesta.json();
        if (!respuesta.ok || !resultado.ok) {
            const mensajes = {
                sesion_requerida: 'Tu sesión venció. Iniciá sesión nuevamente.',
                solicitud_invalida: 'La página venció. Recargala e intentá nuevamente.',
                datos_invalidos: 'La fecha o el horario seleccionado no es válido.',
                fecha_pasada: 'No se pueden agregar ni modificar excepciones en días pasados.',
                ocupado: 'Ese horario tiene un turno reservado y no se puede modificar.',
                superpuesto: 'Ese horario se superpone con otra excepción existente.',
                dentro_horario_base: 'El turno extra debe quedar completamente fuera del horario base.',
                paciente_invalido: 'No se encontró un paciente con ese nombre y DNI.',
                excepcion_no_encontrada: 'La excepción ya no existe o no puede modificarse.',
                turno_no_encontrado: 'El turno ya fue cancelado o no pertenece a esta agenda.',
                horario_invalido: 'No se pudo determinar la duración del turno para bloquearlo.',
                servidor: 'No se pudo guardar el cambio. Intentá nuevamente.'
            };
            throw new Error(mensajes[resultado.error] || 'No se pudo completar la operación.');
        }
        return resultado;
    }

    async function cargarMes() {
        const solicitudActual = ++solicitudCargaMes;
        if (!selectorProfesional.value) {
            diasConTurnos = new Set();
            renderizarCalendario();
            return;
        }

        try {
            const resultado = await enviarAccion({
                accion: 'cargar_mes',
                mes: `${anio}-${String(mes + 1).padStart(2, '0')}`
            });
            if (solicitudActual !== solicitudCargaMes) return;
            diasConTurnos = new Set(resultado.data.dias);
            renderizarCalendario();
        } catch (error) {
            if (solicitudActual !== solicitudCargaMes) return;
            crearAviso(error.message);
            console.error('No se pudieron cargar los días con turnos.', error);
        }
    }

    function crearAviso(texto) {
        estadoBloqueo.textContent = texto;
        estadoBloqueo.classList.add('estado-error-excepcion');
        window.setTimeout(() => estadoBloqueo.classList.remove('estado-error-excepcion'), 4000);
    }

    async function cargarDia() {
        const solicitudActual = ++solicitudCargaDia;
        if (!selectorProfesional.value) {
            listaFranjas.replaceChildren();
            listaFranjas.removeAttribute('aria-busy');
            return;
        }
        listaFranjas.setAttribute('aria-busy', 'true');
        listaFranjas.replaceChildren();
        try {
            const resultado = await enviarAccion({
                accion: 'cargar_dia',
                fecha: fechaISO(fechaSeleccionada)
            });
            if (solicitudActual !== solicitudCargaDia) return;
            listaFranjas.replaceChildren();
            resultado.data.franjas.forEach((franja) => {
                franja.fecha = fechaLegible(fechaSeleccionada, {
                    weekday: 'long',
                    day: 'numeric',
                    month: 'long'
                });
                listaFranjas.append(crearFranja(franja, resultado.data.bloqueadoDia));
            });
            if (!resultado.data.franjas.length) {
                const aviso = document.createElement('p');
                aviso.className = 'mensaje-sin-franjas-excepcion';
                aviso.textContent = 'No hay franjas registradas para este día.';
                listaFranjas.append(aviso);
            }
            diaCompletoBloqueado = resultado.data.bloqueadoDia;
            botonBloquearDia.disabled = false;
            botonBloquearDia.textContent = diaCompletoBloqueado
                ? 'Desbloquear día'
                : 'Bloquear día completo';
            botonTurnoExtra.disabled = resultado.data.bloqueadoDia;
            estadoBloqueo.textContent = resultado.data.textoBloqueo || '';
            if (resultado.data.bloqueadoDia) {
                (resultado.data.turnosCancelados || []).forEach((turno) => {
                    const aviso = document.createElement('span');
                    aviso.style.display = 'block';
                    const paciente = `${turno.nombre} ${turno.apellido}`.trim();
                    const telefono = String(turno.telefono || '').replace(/\D/g, '');
                    if (telefono) {
                        const horario = `a las ${turno.hora}`;
                        const mensaje = `Hola ${paciente}, te escribimos de Pausa para avisarte que tuvimos que cancelar tu turno del ${fechaLegible(fechaSeleccionada, {
                            day: 'numeric',
                            month: 'long',
                            year: 'numeric'
                        })} ${horario}. Podés solicitar otro turno cuando quieras. Disculpá las molestias.`;
                        const enlace = document.createElement('a');
                        enlace.className = 'btn-whatsapp-modal';
                        enlace.href = `https://wa.me/${telefono}?text=${encodeURIComponent(mensaje)}`;
                        enlace.target = '_blank';
                        enlace.rel = 'noopener noreferrer';
                        enlace.textContent = `Avisar a ${paciente} (${turno.hora}) por WhatsApp`;
                        aviso.append(enlace);
                    } else {
                        aviso.textContent = `${paciente} (${turno.hora}): no tiene teléfono registrado.`;
                    }
                    estadoBloqueo.append(aviso);
                });
            }
            estadoBloqueo.classList.remove('estado-error-excepcion');
        } catch (error) {
            if (solicitudActual !== solicitudCargaDia) return;
            listaFranjas.replaceChildren();
            crearAviso(error.message);
            console.error('No se pudieron cargar las excepciones de agenda.', error);
        } finally {
            if (solicitudActual === solicitudCargaDia) {
                listaFranjas.removeAttribute('aria-busy');
            }
        }
    }

    function crearAccion(texto, clase) {
        const boton = document.createElement('button');
        boton.type = 'button';
        boton.className = `btn-accion-linea ${clase}`;
        boton.textContent = texto;
        return boton;
    }

    function crearFranja(datos, bloqueadoDia) {
        const franja = document.createElement('div');
        franja.className = `item-franja-estado${datos.estado === 'ocupado' ? ' franja-ocupada' : ''}${datos.estado === 'bloqueado' ? ' franja-bloqueada' : ''}${datos.estado === 'pasado' ? ' franja-pasada' : ''}${datos.extra ? ' franja-turno-extra' : ''}`;
        franja.dataset.inicio = datos.inicio;
        franja.dataset.fin = datos.fin;
        if (datos.excepcionId) franja.dataset.excepcionId = datos.excepcionId;
        if (datos.turnoId) franja.dataset.turnoId = datos.turnoId;

        const hora = document.createElement('span');
        hora.className = 'hora-franja';
        hora.textContent = datos.extra ? `${datos.inicio} - ${datos.fin}` : datos.inicio;
        const badge = document.createElement('span');
        badge.className = `badge-estado ${datos.estado === 'ocupado' ? 'badge-ocupado' : datos.estado === 'bloqueado' ? 'badge-bloqueado' : datos.estado === 'pasado' ? 'badge-pasado' : 'badge-disponible'}`;
        badge.textContent = datos.estado === 'ocupado' ? 'Ocupado' : datos.estado === 'bloqueado' ? 'Bloqueado' : datos.estado === 'pasado' ? 'No disponible' : 'Disponible';
        const detalle = document.createElement('span');
        detalle.className = 'detalle-estado';
        if (datos.estado === 'ocupado') {
            detalle.append(document.createTextNode(datos.paciente || 'Paciente asignado'));
            if (datos.dni) {
                const dni = document.createElement('small');
                dni.textContent = `DNI: ${datos.dni}`;
                detalle.append(dni);
            }
        } else {
            detalle.textContent = datos.detalle || (
                datos.estado === 'bloqueado' ? 'Por excepción manual' : 'Turno estándar'
            );
        }
        if (datos.extra) {
            const etiqueta = document.createElement('span');
            etiqueta.className = 'etiqueta-turno-extra';
            etiqueta.textContent = 'Turno extra';
            detalle.append(etiqueta);
        }
        franja.append(hora, badge, detalle);

        if (datos.estado === 'ocupado') {
            const cancelar = document.createElement('button');
            cancelar.type = 'button';
            cancelar.className = 'btn-accion-linea btn-cancelar-turno-excepcion';
            cancelar.textContent = 'Bloquear turno';
            Object.assign(cancelar.dataset, {
                paciente: datos.paciente,
                telefono: datos.telefono,
                fecha: datos.fecha,
                hora: datos.inicio,
                fin: datos.fin,
                turnoId: datos.turnoId || ''
            });
            franja.append(cancelar);
        } else if (datos.estado === 'bloqueado' && datos.whatsappHref) {
            const enlace = document.createElement('a');
            enlace.className = 'btn-whatsapp-modal btn-accion-linea';
            enlace.href = datos.whatsappHref;
            enlace.target = '_blank';
            enlace.rel = 'noopener noreferrer';
            enlace.textContent = 'Enviar WhatsApp de cancelación';
            franja.append(enlace);
        } else if (!bloqueadoDia && datos.estado !== 'pasado') {
            franja.append(crearAccion(
                datos.estado === 'bloqueado' ? 'Desbloquear' : 'Bloquear',
                datos.estado === 'bloqueado' ? 'btn-desbloquear' : 'btn-bloquear-individual'
            ));
        }
        return franja;
    }

    async function guardarBloqueoDia() {
        try {
            await enviarAccion({
                accion: 'bloquear_dia',
                fecha: fechaISO(fechaSeleccionada)
            });
            await cargarDia();
            await cargarMes();
        } catch (error) {
            await cargarDia();
            crearAviso(error.message);
        }
    }

    panel.addEventListener('click', (evento) => {
        const boton = evento.target.closest('.btn-bloquear-individual, .btn-desbloquear');
        if (!boton) return;
        const franja = boton.closest('.item-franja-estado');
        accionFranjaPendiente = {
            accion: boton.classList.contains('btn-bloquear-individual')
                ? 'bloquear_franja'
                : 'desbloquear_franja',
            inicio: franja.dataset.inicio,
            fin: franja.dataset.fin,
            excepcionId: franja.dataset.excepcionId || ''
        };
    });

    botonConfirmarEstadoFranja.addEventListener('click', async () => {
        if (!accionFranjaPendiente) return;
        const accion = accionFranjaPendiente;
        accionFranjaPendiente = null;
        try {
            await enviarAccion({
                accion: accion.accion,
                fecha: fechaISO(fechaSeleccionada),
                hora_inicio: accion.inicio,
                hora_fin: accion.fin,
                excepcion_id: accion.excepcionId
            });
            modalConfirmarEstadoFranja.close();
            await cargarDia();
            await cargarMes();
        } catch (error) {
            await cargarDia();
            crearAviso(error.message);
        }
    });
    document.getElementById('cancelar-confirmacion-franja').addEventListener('click', () => {
        accionFranjaPendiente = null;
    });

    botonBloquearDia.addEventListener('click', (evento) => {
        if (evento.target.disabled) return;
        if (diaCompletoBloqueado) {
            evento.preventDefault();
            evento.stopImmediatePropagation();
            enviarAccion({
                accion: 'desbloquear_dia',
                fecha: fechaISO(fechaSeleccionada)
            }).then(async () => {
                await cargarDia();
                await cargarMes();
            }).catch(async (error) => {
                await cargarDia();
                crearAviso(error.message);
            });
            return;
        }
        if (panel.querySelector('.franja-ocupada')) return;
        guardarBloqueoDia();
    }, true);

    document.getElementById('confirmar-bloqueo-dia').addEventListener('click', () => {
        guardarBloqueoDia();
    });

    document.getElementById('enlace-whatsapp-excepcion').addEventListener('click', async (evento) => {
        const turnoId = evento.currentTarget.dataset.turnoId;
        if (!turnoId) return;
        evento.preventDefault();
        const telefono = evento.currentTarget.dataset.telefono;
        const urlWhatsapp = evento.currentTarget.href;
        const ventanaWhatsapp = telefono ? window.open('about:blank', '_blank') : null;
        try {
            await enviarAccion({
                accion: 'cancelar_turno',
                fecha: fechaISO(fechaSeleccionada),
                turno_id: turnoId
            });
            if (ventanaWhatsapp) {
                ventanaWhatsapp.opener = null;
                ventanaWhatsapp.location.href = urlWhatsapp;
            }
            document.getElementById('modalCancelarTurnoExcepcion').close();
            await cargarDia();
            await cargarMes();
        } catch (error) {
            ventanaWhatsapp?.close();
            crearAviso(error.message);
        }
    });

    formularioTurnoExtra.addEventListener('submit', async (evento) => {
        evento.preventDefault();
        const datos = new FormData(formularioTurnoExtra);
        try {
            await enviarAccion({
                accion: 'crear_extra',
                fecha: fechaISO(fechaSeleccionada),
                hora_inicio: String(datos.get('horaInicio') || ''),
                estado: String(datos.get('estado') || 'disponible'),
                nombre_paciente: String(datos.get('nombrePaciente') || '').trim(),
                dni_paciente: String(datos.get('dniPaciente') || '').trim()
            });
            modalTurnoExtra.close();
            await cargarDia();
            await cargarMes();
        } catch (error) {
            errorTurnoExtra.textContent = error.message;
            errorTurnoExtra.hidden = false;
            if (!modalTurnoExtra.open) modalTurnoExtra.showModal();
            await cargarDia();
        }
    });

    function seleccionarFecha(fecha) {
        if (esFechaPasada(fecha)) return;
        fechaSeleccionada = fecha;
        sessionStorage.setItem(CLAVE_FECHA, fechaISO(fecha));
        tituloDia.textContent = fechaLegible(fecha, {
            weekday: 'long',
            day: 'numeric',
            month: 'long',
            year: 'numeric'
        });
        document.getElementById('fecha-turno-extra').textContent = tituloDia.textContent;
        renderizarCalendario();
        cargarDia();
    }

    function renderizarCalendario() {
        tituloMes.textContent = fechaLegible(new Date(anio, mes, 1), {
            month: 'long',
            year: 'numeric'
        });
        grilla.setAttribute('aria-label', `${tituloMes.textContent}`);
        grilla.replaceChildren();
        for (let espacio = 0; espacio < new Date(anio, mes, 1).getDay(); espacio += 1) {
            const vacio = document.createElement('span');
            vacio.setAttribute('aria-hidden', 'true');
            grilla.append(vacio);
        }

        const cantidadDias = new Date(anio, mes + 1, 0).getDate();
        for (let dia = 1; dia <= cantidadDias; dia += 1) {
            const fecha = new Date(anio, mes, dia);
            const id = fechaISO(fecha);
            const boton = document.createElement('button');
            boton.type = 'button';
            const seleccionado = id === fechaISO(fechaSeleccionada);
            boton.className = seleccionado ? 'dia-seleccionado' : 'dia-disponible';
            boton.disabled = esFechaPasada(fecha);
            if (boton.disabled) boton.classList.add('dia-inactivo');
            boton.textContent = String(dia);
            boton.setAttribute('aria-label', fechaLegible(fecha, {
                day: 'numeric',
                month: 'long',
                year: 'numeric'
            }));
            boton.setAttribute('aria-pressed', String(seleccionado));
            if (!boton.disabled) {
                boton.addEventListener('click', () => seleccionarFecha(fecha));
            }
            grilla.append(boton);
        }
    }

    botonesNavegacion.forEach((boton, indice) => {
        // El índice 0 es el botón de mes anterior (<) y el índice 1 es el siguiente (>)
        if (indice === 0) {
            boton.addEventListener('click', () => {
                const hoy = fechaDeHoy();
                // Verificamos si estamos en el mes actual del año actual para no permitir ir al pasado
                if (anio === hoy.getFullYear() && mes <= hoy.getMonth()) {
                    return; // No hace nada si intenta ir a un mes anterior al actual
                }
                mes -= 1;
                if (mes < 0) {
                    mes = 11;
                    anio -= 1;
                }
                const fechaMes = new Date(anio, mes, 1);
                if (esFechaPasada(fechaMes)) {
                    anio = hoy.getFullYear();
                    mes = hoy.getMonth();
                    seleccionarFecha(hoy);
                } else {
                    seleccionarFecha(fechaMes);
                }
                cargarMes();
            });
        } else {
            boton.addEventListener('click', () => {
                mes += 1;
                if (mes > 11) {
                    mes = 0;
                    anio += 1;
                }
                const fechaMes = new Date(anio, mes, 1);
                if (esFechaPasada(fechaMes)) {
                    const hoy = fechaDeHoy();
                    anio = hoy.getFullYear();
                    mes = hoy.getMonth();
                    seleccionarFecha(hoy);
                } else {
                    seleccionarFecha(fechaMes);
                }
                cargarMes();
            });
        }
    });

    selectorProfesional.addEventListener('change', () => {
        seleccionarFecha(fechaSeleccionada);
        cargarMes();
    });

    const fechaGuardada = sessionStorage.getItem(CLAVE_FECHA);
    let fechaInicial = fechaOriginal;
    if (fechaGuardada) {
        const restaurada = new Date(`${fechaGuardada}T12:00:00`);
        if (Number.isFinite(restaurada.getTime())) fechaInicial = restaurada;
    }
    if (esFechaPasada(fechaInicial)) fechaInicial = fechaOriginal;
    anio = fechaInicial.getFullYear();
    mes = fechaInicial.getMonth();
    seleccionarFecha(fechaInicial);
    cargarMes();
    window.setInterval(() => {
        if (document.visibilityState !== 'visible') return;
        if (esFechaPasada(fechaSeleccionada)) {
            const hoy = fechaDeHoy();
            anio = hoy.getFullYear();
            mes = hoy.getMonth();
            seleccionarFecha(hoy);
            cargarMes();
            return;
        }
        cargarDia();
    }, 60000);
})();

(() => {
    function normalizar(texto) {
        return String(texto || '')
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '')
            .toLocaleLowerCase('es')
            .trim();
    }

    function inicializarModalExitoProfesional() {
        const modal = document.getElementById('modalExitoProfesional');
        const parametros = new URLSearchParams(window.location.search);
        if (!modal || !parametros.has('exito')) return;

        const dni = document.getElementById('modal-prof-dni');
        const contrasena = document.getElementById('modal-prof-pass');
        if (dni) dni.textContent = parametros.get('dni') || '';
        if (contrasena) contrasena.textContent = parametros.get('pass') || '';
        modal.showModal();
    }

    function inicializarTurnosDelDia() {
        const lista = document.querySelector('.lista-turnos-operativa[data-source="database"]');
        if (!lista) return;

        const tokenCsrf = lista.dataset.csrfToken;
        window.setInterval(async () => {
            if (document.visibilityState !== 'visible') return;
            try {
                const respuesta = await fetch('../procesos/secretario/actualizar-turnos-vencidos.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8' },
                    body: new URLSearchParams({ csrf_token: tokenCsrf })
                });
                const resultado = await respuesta.json();
                if (!respuesta.ok || !resultado.ok) {
                    throw new Error('No se pudieron actualizar los turnos vencidos.');
                }
                if (resultado.actualizados > 0) window.location.reload();
            } catch (error) {
                console.error('No se pudieron actualizar los turnos vencidos.', error);
            }
        }, 60000);
    }

    function inicializarPacientes() {
        const lista = document.querySelector('.lista-pacientes-lateral[data-source="database"]');
        if (!lista) return;

        const busqueda = document.querySelector('.input-busqueda-paciente');
        busqueda?.addEventListener('input', () => {
            const texto = normalizar(busqueda.value);
            [...lista.querySelectorAll('.item-paciente-lista')].forEach((tarjeta) => {
                tarjeta.hidden = !normalizar(tarjeta.textContent).includes(texto);
            });
        });
    }

    function inicializarHorariosBase() {
        const tarjetaDias = document.querySelector('.tarjeta-dias-semana');
        const formulario = document.querySelector('.contenedor-config-agenda')?.closest('form');
        if (!tarjetaDias || !formulario) return;
        const filas = [...tarjetaDias.querySelectorAll('.fila-horario-dia')];
        const obtenerClaveDia = (fila) =>
            fila.querySelector('.switch-toggle input').name.match(/^dias\[([^\]]+)\]\[activo\]$/)?.[1];
        const sincronizarNombresFranjas = (fila) => {
            const dia = obtenerClaveDia(fila);
            if (!dia) return false;

            fila.querySelectorAll('.franja-item').forEach((franja) => {
                const entradas = franja.querySelectorAll('.input-time');
                if (entradas.length >= 2) {
                    entradas[0].name = `dias[${dia}][inicio][]`;
                    entradas[1].name = `dias[${dia}][fin][]`;
                }
            });
            return true;
        };

        filas.forEach((fila) => {
            const activo = fila.querySelector('.switch-toggle input').checked;
            const contenedor = fila.querySelector('.franjas-horarias-container');
            sincronizarNombresFranjas(fila);
            fila.classList.toggle('dia-inactivo', !activo);
            contenedor.hidden = !activo;
            contenedor.querySelectorAll('.franja-item').forEach((franja) => {
                if (franja.querySelector('.btn-eliminar-franja')) return;
                const eliminar = document.createElement('button');
                eliminar.type = 'button';
                eliminar.className = 'btn-accion-horario btn-eliminar-franja';
                eliminar.title = 'Eliminar franja';
                eliminar.setAttribute(
                    'aria-label',
                    `Eliminar franja de ${fila.querySelector('.nombre-dia').textContent}`
                );
                eliminar.textContent = '🗑️';
                franja.insertBefore(eliminar, franja.querySelector('.btn-accion-horario'));
            });
        });

        tarjetaDias.addEventListener('change', (evento) => {
            if (evento.target.matches('.switch-toggle input')) {
                const fila = evento.target.closest('.fila-horario-dia');
                fila.classList.toggle('dia-inactivo', !evento.target.checked);
                fila.querySelector('.franjas-horarias-container').hidden = !evento.target.checked;
            }
        });
        tarjetaDias.addEventListener('click', (evento) => {
            const boton = evento.target.closest('.btn-accion-horario');
            if (!boton) return;
            const fila = boton.closest('.fila-horario-dia');
            const contenedor = fila.querySelector('.franjas-horarias-container');
            if (boton.classList.contains('btn-eliminar-franja')) {
                boton.closest('.franja-item').remove();
                if (!contenedor.querySelector('.franja-item')) {
                    const aviso = document.createElement('span');
                    aviso.className = 'texto-sin-horarios';
                    aviso.textContent = 'Sin horarios asignados';
                    const agregar = document.createElement('button');
                    agregar.type = 'button';
                    agregar.className = 'btn-accion-horario';
                    agregar.title = 'Agregar franja horaria';
                    agregar.setAttribute('aria-label', `Agregar franja para ${fila.querySelector('.nombre-dia').textContent}`);
                    agregar.textContent = '➕';
                    contenedor.append(aviso, agregar);
                }
                const ultima = contenedor.querySelector('.franja-item:last-child');
                if (ultima && !ultima.querySelector('.btn-accion-horario:not(.btn-eliminar-franja)')) {
                    const agregar = document.createElement('button');
                    agregar.type = 'button';
                    agregar.className = 'btn-accion-horario';
                    agregar.title = 'Agregar otra franja horaria';
                    agregar.textContent = '➕';
                    ultima.append(agregar);
                }
                return;
            }
            const dia = fila.querySelector('.switch-toggle input').name.match(/^dias\[([^\]]+)\]\[activo\]$/)?.[1];
            if (!dia) return;
            contenedor.querySelector('.texto-sin-horarios')?.remove();
            boton.remove();
            const item = document.createElement('div');
            item.className = 'franja-item';
            const inicio = document.createElement('input');
            inicio.type = 'time';
            inicio.name = `dias[${dia}][inicio][]`;
            inicio.className = 'input-time';
            inicio.value = '08:00';
            const separador = document.createElement('span');
            separador.className = 'separador-a';
            separador.textContent = 'a';
            const fin = document.createElement('input');
            fin.type = 'time';
            fin.name = `dias[${dia}][fin][]`;
            fin.className = 'input-time';
            fin.value = '12:00';
            const eliminar = document.createElement('button');
            eliminar.type = 'button';
            eliminar.className = 'btn-accion-horario btn-eliminar-franja';
            eliminar.title = 'Eliminar franja';
            eliminar.textContent = '🗑️';
            const agregar = document.createElement('button');
            agregar.type = 'button';
            agregar.className = 'btn-accion-horario';
            agregar.title = 'Agregar otra franja horaria';
            agregar.textContent = '➕';
            item.append(inicio, separador, fin, eliminar, agregar);
            contenedor.append(item);
            fila.querySelector('.switch-toggle input').checked = true;
            fila.classList.remove('dia-inactivo');
            contenedor.hidden = false;
        });

        formulario.addEventListener('submit', (evento) => {
            for (const fila of filas) {
                if (!sincronizarNombresFranjas(fila)) {
                    window.alert('No se pudieron preparar los horarios para guardar. Recargá la página e intentá nuevamente.');
                    evento.preventDefault();
                    return;
                }
                if (!fila.querySelector('.switch-toggle input').checked) continue;
                const dia = fila.querySelector('.nombre-dia').textContent.trim();
                const franjas = [...fila.querySelectorAll('.franja-item')].map((franja) => {
                    const entradas = franja.querySelectorAll('.input-time');
                    return { inicio: entradas[0].value, fin: entradas[1].value };
                });

                if (!franjas.length) {
                    window.alert(`${dia}: agregá al menos una franja o desactivá el día.`);
                    evento.preventDefault();
                    return;
                }
                for (const franja of franjas) {
                    if (!franja.inicio || !franja.fin || franja.inicio >= franja.fin) {
                        window.alert(`${dia}: cada franja debe tener una hora de fin posterior a la de inicio.`);
                        evento.preventDefault();
                        return;
                    }
                }
                const ordenadas = [...franjas].sort((a, b) => a.inicio.localeCompare(b.inicio));
                if (ordenadas.some((franja, indice) =>
                    indice > 0 && franja.inicio < ordenadas[indice - 1].fin
                )) {
                    window.alert(`${dia}: hay franjas horarias superpuestas.`);
                    evento.preventDefault();
                    return;
                }
            }
        });
    }


    inicializarModalExitoProfesional();
    inicializarTurnosDelDia();
    inicializarPacientes();
    inicializarHorariosBase();
})();

(() => {
    if (!(document.getElementById('modalCancelarTurno'))) return;

const modalCancelarTurno = document.getElementById('modalCancelarTurno');

        modalCancelarTurno.addEventListener('close', () => {
            if (modalCancelarTurno.returnValue !== 'cancelar') return;

            const botonCancelar = document.querySelector('.btn-cancelar-turno-mini');
            if (!botonCancelar) return;

            const estadoCancelado = document.createElement('span');
            estadoCancelado.className = 'chip-estado estado-cancelado';
            estadoCancelado.textContent = 'Cancelado';
            botonCancelar.replaceWith(estadoCancelado);
        });
})();

(() => {
    if (!(document.getElementById('profesional-agenda'))) return;

    const pagina = document.querySelector('.vista-agendas');
    const selectorProfesional = document.getElementById('profesional-agenda');
    const grillaDias = document.getElementById('dias-agenda');
    const listaHorarios = document.getElementById('horarios-agenda');
    const nombresMeses = new Intl.DateTimeFormat('es-AR', { month: 'long', year: 'numeric' });
    const formatoFecha = new Intl.DateTimeFormat('es-AR', {
        weekday: 'long',
        day: 'numeric',
        month: 'long',
        year: 'numeric'
    });
    const tokenCsrf = pagina.dataset.csrfToken;
    const rutaProcesador = '../procesos/secretario/procesar-excepciones.php';
    const claveFecha = 'pausa.secretaria.fechaAgenda';
    let solicitudCarga = 0;
    let solicitudCargaMes = 0;
    let diasConTurnos = new Set();

    function fechaISO(fecha) {
        return `${fecha.getFullYear()}-${String(fecha.getMonth() + 1).padStart(2, '0')}-${String(fecha.getDate()).padStart(2, '0')}`;
    }

    function leerFechaGuardada() {
        const guardada = sessionStorage.getItem(claveFecha);
        if (!guardada || !/^\d{4}-\d{2}-\d{2}$/.test(guardada)) return new Date();
        const [anio, mes, dia] = guardada.split('-').map(Number);
        const fecha = new Date(anio, mes - 1, dia);
        return fecha.getFullYear() === anio && fecha.getMonth() === mes - 1 && fecha.getDate() === dia
            ? fecha
            : new Date();
    }

    let fechaSeleccionada = leerFechaGuardada();
    const fechaCalendario = new Date(fechaSeleccionada.getFullYear(), fechaSeleccionada.getMonth(), 1);

    function renderizarCalendario() {
        const nombreMes = nombresMeses.format(fechaCalendario);
        document.getElementById('titulo-calendario-agendas').textContent =
            `${nombreMes.charAt(0).toLocaleUpperCase('es-AR')}${nombreMes.slice(1)}`;
        grillaDias.replaceChildren();

        const primerDiaSemana = new Date(
            fechaCalendario.getFullYear(),
            fechaCalendario.getMonth(),
            1
        ).getDay();
        const diasDelMes = new Date(
            fechaCalendario.getFullYear(),
            fechaCalendario.getMonth() + 1,
            0
        ).getDate();

        for (let espacio = 0; espacio < primerDiaSemana; espacio += 1) {
            const celdaVacia = document.createElement('span');
            celdaVacia.setAttribute('aria-hidden', 'true');
            grillaDias.append(celdaVacia);
        }

        for (let dia = 1; dia <= diasDelMes; dia += 1) {
            const fechaDia = new Date(fechaCalendario.getFullYear(), fechaCalendario.getMonth(), dia);
            const fechaDiaISO = fechaISO(fechaDia);
            const seleccionado = fechaDiaISO === fechaISO(fechaSeleccionada);
            const botonDia = document.createElement('button');
            botonDia.type = 'button';
            botonDia.classList.toggle('dia-disponible', diasConTurnos.has(fechaDiaISO));
            botonDia.classList.toggle('dia-seleccionado', seleccionado);
            botonDia.textContent = String(dia);
            botonDia.setAttribute('aria-label', formatoFecha.format(fechaDia));
            botonDia.setAttribute('aria-pressed', String(seleccionado));
            botonDia.addEventListener('click', () => {
                fechaSeleccionada = fechaDia;
                sessionStorage.setItem(claveFecha, fechaISO(fechaDia));
                renderizarCalendario();
                cargarHorarios();
            });
            grillaDias.append(botonDia);
        }
    }

    async function cargarDiasConTurnos() {
        const solicitudActual = ++solicitudCargaMes;
        const profesionalId = selectorProfesional.value;
        diasConTurnos = new Set();
        renderizarCalendario();
        if (!profesionalId) return;

        const mes = `${fechaCalendario.getFullYear()}-${String(fechaCalendario.getMonth() + 1).padStart(2, '0')}`;
        const formulario = new URLSearchParams({
            accion: 'cargar_mes',
            profesional_id: profesionalId,
            mes,
            csrf_token: tokenCsrf
        });

        try {
            const respuesta = await fetch(rutaProcesador, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8' },
                body: formulario
            });
            const resultado = await respuesta.json();
            if (!respuesta.ok || !resultado.ok) {
                throw new Error('No se pudieron cargar los días con turnos.');
            }
            if (solicitudActual !== solicitudCargaMes) return;

            diasConTurnos = new Set(resultado.data.dias);
        } catch (error) {
            if (solicitudActual !== solicitudCargaMes) return;
            console.error('No se pudieron cargar los días con turnos desde la base de datos.', error);
        }

        renderizarCalendario();
    }

    function mostrarMensaje(texto, esError = false) {
        const mensaje = document.createElement('p');
        mensaje.className = esError ? 'mensaje-agenda-error' : 'mensaje-sin-franjas-excepcion';
        mensaje.setAttribute(esError ? 'role' : 'aria-live', esError ? 'alert' : 'polite');
        mensaje.textContent = texto;
        listaHorarios.replaceChildren(mensaje);
    }

    async function cargarHorarios() {
        const solicitudActual = ++solicitudCarga;
        const profesionalId = selectorProfesional.value;
        const fechaTexto = formatoFecha.format(fechaSeleccionada);
        const tituloFecha = document.getElementById('fecha-agenda-seleccionada');
        tituloFecha.textContent = `${fechaTexto.charAt(0).toLocaleUpperCase('es-AR')}${fechaTexto.slice(1)}`;

        if (!profesionalId) {
            mostrarMensaje('No hay profesionales disponibles para consultar.');
            return;
        }

        listaHorarios.setAttribute('aria-busy', 'true');
        mostrarMensaje('Cargando agenda...');
        try {
            const formulario = new URLSearchParams({
                accion: 'cargar_dia',
                profesional_id: profesionalId,
                fecha: fechaISO(fechaSeleccionada),
                csrf_token: tokenCsrf
            });
            const respuesta = await fetch(rutaProcesador, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8' },
                body: formulario
            });
            const resultado = await respuesta.json();
            if (!respuesta.ok || !resultado.ok) {
                throw new Error('No se pudo cargar la agenda de este día.');
            }
            if (solicitudActual !== solicitudCarga) return;

            listaHorarios.replaceChildren();
            resultado.data.franjas.forEach((turno) => {
                const franja = document.createElement('div');
                franja.className = `item-franja-estado${turno.estado === 'ocupado' ? ' franja-ocupada' : ''}${turno.estado === 'bloqueado' ? ' franja-bloqueada' : ''}${turno.estado === 'pasado' ? ' franja-pasada' : ''}${turno.extra ? ' franja-turno-extra' : ''}`;

                const hora = document.createElement('span');
                hora.className = 'hora-franja';
                hora.textContent = `${turno.inicio} - ${turno.fin}`;

                const estado = document.createElement('span');
                const ocupado = turno.estado === 'ocupado';
                const bloqueado = turno.estado === 'bloqueado';
                const pasado = turno.estado === 'pasado';
                estado.className = `badge-estado ${ocupado ? 'badge-ocupado' : bloqueado ? 'badge-bloqueado' : pasado ? 'badge-pasado' : 'badge-disponible'}`;
                estado.textContent = ocupado ? 'Ocupado' : bloqueado ? 'Bloqueado' : pasado ? 'No disponible' : 'Disponible';

                const detalle = document.createElement('span');
                detalle.className = 'detalle-estado';
                detalle.textContent = turno.paciente || turno.detalle || 'Horario libre';
                if (turno.dni) {
                    const dni = document.createElement('small');
                    dni.textContent = `DNI: ${turno.dni}`;
                    detalle.append(dni);
                }
                franja.append(hora, estado, detalle);
                listaHorarios.append(franja);
            });

            if (!resultado.data.franjas.length) {
                mostrarMensaje(resultado.data.bloqueadoDia
                    ? 'La agenda de este día está bloqueada.'
                    : 'No hay horarios base ni turnos extra registrados para este día.');
            }
        } catch (error) {
            if (solicitudActual !== solicitudCarga) return;
            mostrarMensaje(error.message, true);
            console.error('No se pudo cargar la agenda desde la base de datos.', error);
        } finally {
            if (solicitudActual === solicitudCarga) {
                listaHorarios.setAttribute('aria-busy', 'false');
            }
        }
    }

    function cambiarMes(cambio) {
        const diaDelMes = fechaSeleccionada.getDate();
        fechaCalendario.setMonth(fechaCalendario.getMonth() + cambio);
        const ultimoDiaMes = new Date(
            fechaCalendario.getFullYear(),
            fechaCalendario.getMonth() + 1,
            0
        ).getDate();
        fechaSeleccionada = new Date(
            fechaCalendario.getFullYear(),
            fechaCalendario.getMonth(),
            Math.min(diaDelMes, ultimoDiaMes)
        );
        sessionStorage.setItem(claveFecha, fechaISO(fechaSeleccionada));
        renderizarCalendario();
        cargarDiasConTurnos();
        cargarHorarios();
    }

    document.getElementById('mes-anterior').addEventListener('click', () => cambiarMes(-1));
    document.getElementById('mes-siguiente').addEventListener('click', () => cambiarMes(1));
    selectorProfesional.addEventListener('change', () => {
        cargarDiasConTurnos();
        cargarHorarios();
    });

    renderizarCalendario();
    cargarDiasConTurnos();
    cargarHorarios();
    window.setInterval(() => {
        if (document.visibilityState === 'visible') cargarHorarios();
    }, 60000);
})();
