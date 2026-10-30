const CLAVES_PACIENTE = {
    borrador: 'pausa.turnoBorrador',
    turno: 'pausa.turnoActivo',
    turnoCancelado: 'pausa.turnoCancelado',
    perfil: 'pausa.perfilPaciente'
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
    };

    const tarjetaGuardada = tarjetas.find((tarjeta) =>
        tarjeta.querySelector('h3')?.textContent.trim() === borrador.profesional?.nombre
    );
    if (tarjetaGuardada) marcarSeleccion(tarjetaGuardada);

    tarjetas.forEach((tarjeta) => {
        tarjeta.addEventListener('click', () => {
            marcarSeleccion(tarjeta);
            guardarBorrador({
                profesional: {
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

    const meses = Array.from({ length: 12 }, (_, indice) =>
        new Intl.DateTimeFormat('es-AR', { month: 'long' }).format(new Date(2026, indice, 1))
    );
    const mesInicial = encabezadoMes.textContent.trim().split(/\s+/);
    let mes = meses.indexOf(mesInicial[0].toLocaleLowerCase('es-AR'));
    let anio = Number(mesInicial[1]);
    if (mes < 0 || !Number.isInteger(anio)) {
        mes = new Date().getMonth();
        anio = new Date().getFullYear();
    }

    const diasDisponiblesIniciales = new Set(
        [...grilla.querySelectorAll('.dia-disponible, .dia-seleccionado')]
            .map((dia) => Number(dia.textContent.trim()))
    );
    const diaSeleccionadoInicial = grilla.querySelector('.dia-seleccionado');
    const borrador = leerBorrador();
    let fechaSeleccionada = borrador.fecha ||
        (diaSeleccionadoInicial
            ? fechaISO(new Date(anio, mes, Number(diaSeleccionadoInicial.textContent.trim())))
            : null);

    const horas = [...document.querySelectorAll('.btn-hora')];
    let horaSeleccionada = borrador.hora ||
        document.querySelector('.btn-hora.hora-seleccionada')?.textContent.trim() ||
        null;

    const hoy = fechaISO(new Date());
    const fechaEsDisponible = (fecha) => {
        if (fecha < hoy) return false;
        const fechaActual = fechaLocal(fecha);
        if (anio === Number(mesInicial[1]) &&
            mes === meses.indexOf(mesInicial[0].toLocaleLowerCase('es-AR'))) {
            return diasDisponiblesIniciales.has(fechaActual.getDate());
        }
        return fechaActual.getDay() !== 0 && fechaActual.getDay() !== 6;
    };

    const seleccionarHora = (boton) => {
        horas.forEach((opcion) => {
            const seleccionada = opcion === boton;
            opcion.classList.toggle('hora-seleccionada', seleccionada);
            opcion.setAttribute('aria-pressed', String(seleccionada));
        });
        horaSeleccionada = boton.textContent.trim();
        guardarBorrador({ hora: horaSeleccionada });
    };

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

    const dibujarCalendario = () => {
        encabezadoMes.textContent = formatearFecha(new Date(anio, mes, 1), {
            month: 'long',
            year: 'numeric'
        });
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
                    horas.forEach((hora) => {
                        hora.classList.remove('hora-seleccionada');
                        hora.setAttribute('aria-pressed', 'false');
                    });
                    guardarBorrador({ fecha, hora: null });
                    dibujarCalendario();
                    actualizarEncabezadoFecha();
                });
            } else {
                boton.className = 'dia-inactivo';
                boton.disabled = true;
            }
            grilla.append(boton);
        }
        actualizarEncabezadoFecha();
    };

    if (fechaSeleccionada && fechaLocal(fechaSeleccionada).getFullYear() === anio &&
        fechaLocal(fechaSeleccionada).getMonth() === mes) {
        guardarBorrador({ fecha: fechaSeleccionada, hora: horaSeleccionada });
    }

    horas.forEach((hora) => {
        hora.setAttribute('aria-pressed', String(hora.textContent.trim() === horaSeleccionada));
        hora.addEventListener('click', () => seleccionarHora(hora));
        hora.classList.toggle('hora-seleccionada', hora.textContent.trim() === horaSeleccionada);
    });

    document.querySelectorAll('.btn-nav-mes').forEach((boton, indice) => {
        boton.setAttribute('aria-label', indice === 0 ? 'Mes anterior' : 'Mes siguiente');
        boton.addEventListener('click', () => {
            mes += indice === 0 ? -1 : 1;
            if (mes < 0) {
                mes = 11;
                anio -= 1;
            } else if (mes > 11) {
                mes = 0;
                anio += 1;
            }
            dibujarCalendario();
        });
    });

    const siguiente = document.querySelector('.footer-pasos .btn-siguiente-paso');
    siguiente?.addEventListener('click', (evento) => {
        const fechaValida = fechaSeleccionada && fechaSeleccionada >= hoy;
        if (fechaValida && horaSeleccionada) return;

        evento.preventDefault();
        window.alert('Seleccioná una fecha y un horario disponible para continuar.');
    });

    dibujarCalendario();
}

function inicializarConfirmacion() {
    const resumen = document.querySelector('.panel-resumen');
    if (!resumen) return;

    const borrador = leerBorrador();
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
            window.location.href = estado.profesional?.nombre ? 'paciente2.html' : 'paciente1.html';
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

    const turno = leerDato(localStorage, CLAVES_PACIENTE.turno);
    if (turno) actualizarTarjetaTurno(turno);
    if (localStorage.getItem(CLAVES_PACIENTE.turnoCancelado) === 'true') {
        tarjetaTurno.hidden = true;
        const aviso = document.createElement('p');
        aviso.className = 'aviso-sin-turno';
        aviso.textContent = 'No tenés turnos próximos. Podés agendar una nueva sesión cuando lo necesites.';
        tarjetaTurno.insertAdjacentElement('afterend', aviso);
    }

    const perfil = leerDato(localStorage, CLAVES_PACIENTE.perfil);
    if (perfil) {
        const campos = {
            email: document.querySelector('#email'),
            telefono: document.querySelector('#telefono'),
            'obra-social': document.querySelector('#obra-social')
        };
        Object.entries(campos).forEach(([clave, campo]) => {
            if (campo && perfil[clave]) campo.value = perfil[clave];
        });
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

function inicializarPerfil() {
    const formularios = document.querySelectorAll('.form-perfil');
    if (!formularios.length) return;

    const formularioDatos = formularios[0];
    const camposPerfil = ['email', 'telefono', 'obra-social'];
    const perfil = leerDato(localStorage, CLAVES_PACIENTE.perfil, {});

    camposPerfil.forEach((id) => {
        const campo = formularioDatos.querySelector(`#${id}`);
        if (!campo) return;
        campo.required = true;
        if (perfil[id]) campo.value = perfil[id];
    });

    formularioDatos.addEventListener('submit', (evento) => {
        evento.preventDefault();
        if (!formularioDatos.reportValidity()) return;

        const nuevosDatos = Object.fromEntries(camposPerfil.map((id) => [
            id,
            formularioDatos.querySelector(`#${id}`).value.trim()
        ]));
        localStorage.setItem(CLAVES_PACIENTE.perfil, JSON.stringify(nuevosDatos));
        window.alert('Los datos de tu perfil se guardaron en este dispositivo.');
    });

    const formularioClave = formularios[1];
    if (!formularioClave) return;

    const claveActual = formularioClave.querySelector('#contra-actual');
    const claveNueva = formularioClave.querySelector('#contra-nueva');
    const confirmarClave = formularioClave.querySelector('#contra-confirmar');
    [claveActual, claveNueva, confirmarClave].forEach((campo) => {
        if (campo) campo.required = true;
    });
    if (claveNueva) claveNueva.minLength = 8;
    if (confirmarClave) confirmarClave.minLength = 8;

    confirmarClave?.addEventListener('input', () => confirmarClave.setCustomValidity(''));
    claveNueva?.addEventListener('input', () => confirmarClave?.setCustomValidity(''));
    formularioClave.addEventListener('submit', (evento) => {
        evento.preventDefault();
        if (claveNueva.value !== confirmarClave.value) {
            confirmarClave.setCustomValidity('Las contraseñas nuevas no coinciden.');
        }
        if (!formularioClave.reportValidity()) return;

        formularioClave.reset();
        window.alert('La solicitud es válida. Para actualizar la contraseña hace falta conectar un servicio de autenticación.');
    });
}

inicializarSeleccionProfesional();
inicializarCalendario();
inicializarConfirmacion();
inicializarInicioPaciente();
inicializarPerfil();
