const CLAVES_PACIENTE = {
    borrador: 'pausa.turnoBorrador',
    turno: 'pausa.turnoActivo',
    turnoCancelado: 'pausa.turnoCancelado'
};

function leerDato(storage, clave, valorPredeterminado = null) {
    const valor = storage.getItem(clave);
    return valor ? JSON.parse(valor) : valorPredeterminado;
}

function leerBorrador() {
    return leerDato(sessionStorage, CLAVES_PACIENTE.borrador, {});
}

function guardarBorrador(cambios) {
    sessionStorage.setItem(
        CLAVES_PACIENTE.borrador,
        JSON.stringify({ ...leerBorrador(), ...cambios })
    );
}

function capitalizar(texto) {
    return texto.charAt(0).toLocaleUpperCase('es-AR') + texto.slice(1);
}

function fechaLocal(fecha) {
    const [anio, mes, dia] = fecha.split('-').map(Number);
    return new Date(anio, mes - 1, dia);
}

function fechaISO(fecha) {
    const anio = fecha.getFullYear();
    const mes = String(fecha.getMonth() + 1).padStart(2, '0');
    const dia = String(fecha.getDate()).padStart(2, '0');
    return `${anio}-${mes}-${dia}`;
}

function formatearFecha(fecha, opciones) {
    return capitalizar(new Intl.DateTimeFormat('es-AR', opciones).format(fecha));
}

function inicializarSeleccionProfesional() {
    const tarjetas = [...document.querySelectorAll('.tarjeta-profesional')];
    if (!tarjetas.length) return;

    const borrador = leerBorrador();
    const marcarSeleccion = (tarjeta) => {
        tarjetas.forEach((opcion) => {
            const seleccionada = opcion === tarjeta;
            opcion.classList.toggle('profesional-seleccionado', seleccionada);
            opcion.setAttribute('aria-pressed', String(seleccionada));
        });
        const siguiente = document.querySelector('.footer-pasos .btn-siguiente-paso');
        if (siguiente) {
            const destino = new URL(siguiente.href, window.location.href);
            destino.searchParams.set('profesional_id', tarjeta.dataset.professionalId);
            siguiente.href = destino.toString();
        }
    };

    const tarjetaGuardada = tarjetas.find((tarjeta) =>
        tarjeta.dataset.professionalId === String(borrador.profesional?.id)
    ) || tarjetas.find((tarjeta) =>
        tarjeta.querySelector('h3')?.textContent.trim() === borrador.profesional?.nombre
    );
    if (tarjetaGuardada) marcarSeleccion(tarjetaGuardada);

    tarjetas.forEach((tarjeta) => {
        tarjeta.addEventListener('click', () => {
            marcarSeleccion(tarjeta);
            guardarBorrador({
                profesional: {
                    id: tarjeta.dataset.professionalId,
                    nombre: tarjeta.querySelector('h3').textContent.trim(),
                    especialidad: tarjeta.querySelector('p').textContent.trim()
                },
                fecha: null,
                hora: null
            });
        });
    });

    const siguiente = document.querySelector('.footer-pasos .btn-siguiente-paso');
    siguiente?.addEventListener('click', (evento) => {
        const seleccionada = tarjetas.find((tarjeta) =>
            tarjeta.classList.contains('profesional-seleccionado')
        );
        if (seleccionada) return;

        evento.preventDefault();
        window.alert('Seleccioná un profesional para continuar.');
    });
}

function inicializarCalendario() {
    const grilla = document.querySelector('.grilla-dias');
    const encabezadoMes = document.querySelector('.cabecera-mes h3');
    const encabezadoHorarios = document.querySelector('.cabecera-horarios h3');
    if (!grilla || !encabezadoMes || !encabezadoHorarios) return;

    let horariosPorFecha;
    try {
        horariosPorFecha = JSON.parse(grilla.dataset.slots || '{}');
    } catch (error) {
        console.error('No se pudieron leer los horarios disponibles.', error);
        encabezadoHorarios.textContent = 'No se pudieron cargar los horarios.';
        return;
    }

    const fechasDisponibles = Object.keys(horariosPorFecha).sort();
    const fechaFinDisponibilidad = fechasDisponibles[fechasDisponibles.length - 1];
    const ultimoMesDisponible = fechaFinDisponibilidad
        ? fechaLocal(fechaFinDisponibilidad)
        : new Date();
    const borrador = leerBorrador();
    let fechaSeleccionada = fechasDisponibles.includes(borrador.fecha)
        ? borrador.fecha
        : fechasDisponibles[0] || null;
    let horaSeleccionada = fechaSeleccionada &&
        horariosPorFecha[fechaSeleccionada]?.includes(borrador.hora)
        ? borrador.hora
        : null;
    const fechaInicial = fechaSeleccionada ? fechaLocal(fechaSeleccionada) : new Date();
    let anio = fechaInicial.getFullYear();
    let mes = fechaInicial.getMonth();
    const campoFecha = document.querySelector('#inputFechaSeleccionada');
    const campoHora = document.querySelector('#inputHoraSeleccionada');
    const listaHoras = document.querySelector('.lista-horas');

    const actualizarEncabezadoFecha = () => {
        encabezadoHorarios.textContent = fechaSeleccionada
            ? formatearFecha(fechaLocal(fechaSeleccionada), {
                weekday: 'long',
                day: 'numeric',
                month: 'long',
                year: 'numeric'
            })
            : 'Seleccioná una fecha disponible';
    };

    const dibujarHorarios = () => {
        if (!listaHoras) return;
        listaHoras.replaceChildren();

        const horarios = fechaSeleccionada ? horariosPorFecha[fechaSeleccionada] || [] : [];
        if (horarios.length === 0) {
            const aviso = document.createElement('p');
            aviso.textContent = fechaSeleccionada
                ? 'No hay horarios disponibles para esta fecha.'
                : 'No hay fechas disponibles para este profesional.';
            listaHoras.append(aviso);
        } else {
            horarios.forEach((horario) => {
                const boton = document.createElement('button');
                boton.type = 'button';
                boton.className = 'btn-hora';
                boton.textContent = horario;
                boton.setAttribute('aria-pressed', String(horario === horaSeleccionada));
                if (horario === horaSeleccionada) boton.classList.add('hora-seleccionada');
                boton.addEventListener('click', () => {
                    horaSeleccionada = horario;
                    if (campoHora) campoHora.value = horario;
                    guardarBorrador({ fecha: fechaSeleccionada, hora: horario });
                    dibujarHorarios();
                });
                listaHoras.append(boton);
            });
        }

        if (campoFecha) campoFecha.value = fechaSeleccionada || '';
        if (campoHora) campoHora.value = horaSeleccionada || '';
        actualizarEncabezadoFecha();
    };

    const dibujarCalendario = () => {
        encabezadoMes.textContent = formatearFecha(new Date(anio, mes, 1), {
            month: 'long',
            year: 'numeric'
        });
        const mesActual = new Date(anio, mes, 1);
        const fechaActual = new Date();
        const mesInicialDisponible = new Date(fechaActual.getFullYear(), fechaActual.getMonth(), 1);
        const mesFinalDisponible = new Date(ultimoMesDisponible.getFullYear(), ultimoMesDisponible.getMonth(), 1);
        const botonesNavegacion = document.querySelectorAll('.btn-nav-mes');
        if (botonesNavegacion[0]) botonesNavegacion[0].disabled = mesActual <= mesInicialDisponible;
        if (botonesNavegacion[1]) botonesNavegacion[1].disabled = mesActual >= mesFinalDisponible;
        grilla.replaceChildren();

        const primerDia = new Date(anio, mes, 1).getDay();
        for (let indice = 0; indice < primerDia; indice += 1) {
            const espacio = document.createElement('span');
            espacio.setAttribute('aria-hidden', 'true');
            grilla.append(espacio);
        }

        const cantidadDias = new Date(anio, mes + 1, 0).getDate();
        for (let dia = 1; dia <= cantidadDias; dia += 1) {
            const fecha = fechaISO(new Date(anio, mes, dia));
            const boton = document.createElement('button');
            boton.type = 'button';
            boton.textContent = String(dia);
            boton.setAttribute('aria-label', formatearFecha(fechaLocal(fecha), {
                weekday: 'long',
                day: 'numeric',
                month: 'long',
                year: 'numeric'
            }));

            if (fechaEsDisponible(fecha)) {
                boton.className = 'dia-disponible';
                boton.setAttribute('aria-pressed', String(fecha === fechaSeleccionada));
                if (fecha === fechaSeleccionada) boton.classList.add('dia-seleccionado');
                boton.addEventListener('click', () => {
                    fechaSeleccionada = fecha;
                    horaSeleccionada = null;
                    guardarBorrador({ fecha, hora: null });
                    dibujarCalendario();
                    dibujarHorarios();
                });
            } else {
                boton.className = 'dia-inactivo';
                boton.disabled = true;
            }
            grilla.append(boton);
        }
    };

    const fechaEsDisponible = (fecha) =>
        Array.isArray(horariosPorFecha[fecha]) && horariosPorFecha[fecha].length > 0;

    document.querySelectorAll('.btn-nav-mes').forEach((boton, indice) => {
        boton.setAttribute('aria-label', indice === 0 ? 'Mes anterior' : 'Mes siguiente');
        boton.addEventListener('click', () => {
            const nuevoMes = new Date(anio, mes + (indice === 0 ? -1 : 1), 1);
            const mesInicialDisponible = new Date(new Date().getFullYear(), new Date().getMonth(), 1);
            const mesFinalDisponible = new Date(ultimoMesDisponible.getFullYear(), ultimoMesDisponible.getMonth(), 1);
            if (nuevoMes < mesInicialDisponible || nuevoMes > mesFinalDisponible) return;
            anio = nuevoMes.getFullYear();
            mes = nuevoMes.getMonth();
            dibujarCalendario();
        });
    });

    const siguiente = document.querySelector('.footer-pasos .btn-siguiente-paso');
    siguiente?.closest('form')?.addEventListener('submit', (evento) => {
        const horarioValido = fechaEsDisponible(fechaSeleccionada)
            && horariosPorFecha[fechaSeleccionada].includes(horaSeleccionada);
        if (horarioValido) return;

        evento.preventDefault();
        window.alert('Seleccioná una fecha y un horario disponible para continuar.');
    });

    if (fechaSeleccionada) {
        guardarBorrador({
            profesional: borrador.profesional,
            fecha: fechaSeleccionada,
            hora: horaSeleccionada
        });
    }
    dibujarCalendario();
    dibujarHorarios();
}

function inicializarConfirmacion() {
    const resumen = document.querySelector('.panel-resumen');
    if (!resumen) return;

    const borrador = leerBorrador();
    const volver = document.querySelector('.panel-resumen')?.closest('.tarjeta-blanca')
        ?.querySelector('.footer-pasos .btn-cancelar-paso');
    if (volver && borrador.profesional?.id) {
        const destinoPasoDos = new URL('paciente2.php', window.location.href);
        destinoPasoDos.searchParams.set('profesional_id', borrador.profesional.id);
        volver.href = destinoPasoDos.toString();
    }

    const valores = resumen.querySelectorAll('.item-resumen strong');
    if (borrador.profesional?.nombre) valores[0].textContent = borrador.profesional.nombre;
    if (borrador.fecha) {
        valores[1].textContent = formatearFecha(fechaLocal(borrador.fecha), {
            weekday: 'long',
            day: 'numeric',
            month: 'long',
            year: 'numeric'
        });
    }
    if (borrador.hora) valores[2].textContent = `${borrador.hora} hs`;

    const confirmar = document.querySelector('[data-modal-open="modalExito"]');
    confirmar?.addEventListener('click', (evento) => {
        const estado = leerBorrador();
        if (!estado.profesional?.nombre || !estado.fecha || !estado.hora) {
            evento.preventDefault();
            evento.stopImmediatePropagation();
            window.alert('Faltan datos del turno. Volvé a los pasos anteriores y completá la selección.');
            if (estado.profesional?.id) {
                const destinoPasoDos = new URL('paciente2.php', window.location.href);
                destinoPasoDos.searchParams.set('profesional_id', estado.profesional.id);
                window.location.href = destinoPasoDos.toString();
            } else {
                window.location.href = 'paciente1.php';
            }
            return;
        }

        localStorage.setItem(CLAVES_PACIENTE.turno, JSON.stringify(estado));
        localStorage.removeItem(CLAVES_PACIENTE.turnoCancelado);
        sessionStorage.removeItem(CLAVES_PACIENTE.borrador);
    }, true);
}

function actualizarTarjetaTurno(turno) {
    const tarjeta = document.querySelector('.tarjeta-turnos');
    if (!tarjeta) return;

    const profesional = tarjeta.querySelector('p strong');
    const especialidad = profesional?.parentElement.nextElementSibling;
    const chips = tarjeta.querySelectorAll('.chip-info');
    if (profesional && turno.profesional?.nombre) {
        profesional.textContent = `Profesional: ${turno.profesional.nombre}`;
    }
    if (especialidad && turno.profesional?.especialidad) {
        especialidad.textContent = turno.profesional.especialidad;
    }
    if (chips[0] && turno.fecha) {
        chips[0].lastChild.textContent = ` ${formatearFecha(fechaLocal(turno.fecha), {
            weekday: 'long',
            day: 'numeric',
            month: 'short'
        })}`;
    }
    if (chips[1] && turno.hora) chips[1].lastChild.textContent = ` ${turno.hora} hs`;
}

function inicializarInicioPaciente() {
    const tarjetaTurno = document.querySelector('.tarjeta-turnos');
    if (!tarjetaTurno) return;

    if (tarjetaTurno.dataset.source === 'database') return;

    const turno = leerDato(localStorage, CLAVES_PACIENTE.turno);
    if (turno) actualizarTarjetaTurno(turno);
    if (localStorage.getItem(CLAVES_PACIENTE.turnoCancelado) === 'true') {
        tarjetaTurno.hidden = true;
        const aviso = document.createElement('p');
        aviso.className = 'aviso-sin-turno';
        aviso.textContent = 'No tenés turnos próximos. Podés agendar una nueva sesión cuando lo necesites.';
        tarjetaTurno.insertAdjacentElement('afterend', aviso);
    }

    document.querySelector('.btn-peligro-modal')?.addEventListener('click', () => {
        localStorage.removeItem(CLAVES_PACIENTE.turno);
        localStorage.setItem(CLAVES_PACIENTE.turnoCancelado, 'true');
        tarjetaTurno.hidden = true;
        if (!tarjetaTurno.nextElementSibling?.classList.contains('aviso-sin-turno')) {
            const aviso = document.createElement('p');
            aviso.className = 'aviso-sin-turno';
            aviso.textContent = 'No tenés turnos próximos. Podés agendar una nueva sesión cuando lo necesites.';
            tarjetaTurno.insertAdjacentElement('afterend', aviso);
        }
    }, true);
}

inicializarSeleccionProfesional();
inicializarCalendario();
inicializarConfirmacion();
inicializarInicioPaciente();
