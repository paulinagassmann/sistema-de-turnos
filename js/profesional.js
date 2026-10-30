const CLAVES_PROFESIONAL = {
    estadosTurnos: 'pausa.estadosTurnosProfesional',
    agenda: 'pausa.diaAgendaProfesional',
    cuenta: 'pausa.cuentaProfesional'
};

function leerDatoProfesional(clave, valorPredeterminado) {
    const valor = localStorage.getItem(clave);
    return valor ? JSON.parse(valor) : valorPredeterminado;
}

function obtenerNombreNormalizado(texto) {
    return texto.trim().normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLocaleLowerCase('es');
}

function obtenerFechaInicialAgenda(encabezado) {
    const coincidencia = encabezado.textContent.trim().match(/(\d{1,2})\s+de\s+(\S+)(?:\s+de\s+(\d{4}))?/i);
    if (!coincidencia) return null;

    const meses = Array.from({ length: 12 }, (_, indice) =>
        new Intl.DateTimeFormat('es-AR', { month: 'long' }).format(new Date(2026, indice, 1))
    );
    const mes = meses.indexOf(coincidencia[2].toLocaleLowerCase('es-AR'));
    if (mes < 0) return null;

    return new Date(Number(coincidencia[3] || new Date().getFullYear()), mes, Number(coincidencia[1]));
}

function fechaISOProfesional(fecha) {
    const anio = fecha.getFullYear();
    const mes = String(fecha.getMonth() + 1).padStart(2, '0');
    const dia = String(fecha.getDate()).padStart(2, '0');
    return `${anio}-${mes}-${dia}`;
}

function formatearFechaProfesional(fecha, opciones) {
    const texto = new Intl.DateTimeFormat('es-AR', opciones).format(fecha);
    return texto.charAt(0).toLocaleUpperCase('es-AR') + texto.slice(1);
}

function obtenerClaveTurno(fecha, paciente, hora) {
    return `${fecha}|${obtenerNombreNormalizado(paciente)}|${hora.trim().toLocaleLowerCase('es')}`;
}

function agregarSelectorEstadoTurno(insignia, fecha, actualizarResumen) {
    if (insignia.nextElementSibling?.classList.contains('selector-estado-turno')) return;

    const fila = insignia.closest('.fila-turno, .item-turno-agenda');
    const paciente = fila?.querySelector('.info-paciente-turno strong, .nombre-paciente');
    const hora = fila?.querySelector('.chip-horario, .hora-turno-agenda strong');
    if (!paciente || !hora || !fecha) return;

    const clave = obtenerClaveTurno(fecha, paciente.textContent, hora.textContent);
    const estadosGuardados = leerDatoProfesional(CLAVES_PROFESIONAL.estadosTurnos, {});
    const estadoAlmacenado = estadosGuardados[clave];
    const estadoInicial = ['pendiente', 'atendido', 'ausente'].includes(estadoAlmacenado)
        ? estadoAlmacenado
        : insignia.classList.contains('estado-atendido')
            ? 'atendido'
            : insignia.classList.contains('estado-ausente')
                ? 'ausente'
                : 'pendiente';
    const selector = document.createElement('select');
    selector.className = 'selector-estado-turno';
    selector.setAttribute('aria-label', `Cambiar estado del turno de ${paciente.textContent.trim()}`);
    selector.style.cssText = 'margin-inline-start:8px; padding:4px 6px; border:1px solid #cbd5e1; border-radius:6px; background:#fff; font:inherit; font-size:.8rem;';

    [
        ['pendiente', 'Pendiente'],
        ['atendido', 'Atendido'],
        ['ausente', 'Ausente']
    ].forEach(([valor, etiqueta]) => {
        const opcion = document.createElement('option');
        opcion.value = valor;
        opcion.textContent = etiqueta;
        selector.append(opcion);
    });

    const aplicarEstado = (estado) => {
        insignia.classList.remove('estado-pendiente', 'estado-atendido', 'estado-ausente');
        insignia.classList.add(`estado-${estado}`);
        insignia.textContent = estado.charAt(0).toLocaleUpperCase('es-AR') + estado.slice(1);
        selector.value = estado;
    };

    selector.addEventListener('change', () => {
        const estadosActualizados = leerDatoProfesional(CLAVES_PROFESIONAL.estadosTurnos, {});
        estadosActualizados[clave] = selector.value;
        localStorage.setItem(CLAVES_PROFESIONAL.estadosTurnos, JSON.stringify(estadosActualizados));
        aplicarEstado(selector.value);
        actualizarResumen?.();
    });

    insignia.insertAdjacentElement('afterend', selector);
    aplicarEstado(estadoInicial);
}

function inicializarEstadosTurnos() {
    const turnos = [
        ...document.querySelectorAll('.fila-turno .chip-estado, .lista-turnos-dia-agenda .chip-estado')
    ];
    if (!turnos.length) return;

    const fechaDashboard = document.querySelector('.fecha-hoy time')?.dateTime;
    const encabezadoAgenda = document.querySelector('.encabezado-detalle-dia h2');
    const fechaAgendaInicial = encabezadoAgenda && obtenerFechaInicialAgenda(encabezadoAgenda);
    const fechaAgenda = fechaAgendaInicial && fechaISOProfesional(fechaAgendaInicial);

    const actualizarResumen = () => {
        const filas = [...document.querySelectorAll('.fila-turno')];
        const total = document.querySelector('.resumen-dia .valor-resumen');
        const completados = document.querySelector('.resumen-dia .valor-completado');
        const pendientes = document.querySelector('.resumen-dia .valor-pendiente');
        if (!total || !completados || !pendientes) return;

        total.textContent = String(filas.length);
        completados.textContent = String(filas.filter((fila) =>
            fila.querySelector('.chip-estado')?.classList.contains('estado-atendido')
        ).length);
        pendientes.textContent = String(filas.filter((fila) =>
            fila.querySelector('.chip-estado')?.classList.contains('estado-pendiente')
        ).length);
    };

    turnos.forEach((insignia) => {
        const fila = insignia.closest('.fila-turno, .item-turno-agenda');
        const fecha = fila?.classList.contains('fila-turno') ? fechaDashboard : fechaAgenda;
        agregarSelectorEstadoTurno(insignia, fecha, actualizarResumen);
    });

    actualizarResumen();
}

function inicializarAgendaProfesional() {
    const grilla = document.querySelector('.grilla-dias-agenda');
    const encabezadoMes = document.querySelector('.cabecera-mes h3');
    const encabezadoDia = document.querySelector('.encabezado-detalle-dia h2');
    const subtitulo = document.querySelector('.subtitulo-detalle');
    const listaTurnos = document.querySelector('.lista-turnos-dia-agenda');
    if (!grilla || !encabezadoMes || !encabezadoDia || !subtitulo || !listaTurnos) return;

    const meses = Array.from({ length: 12 }, (_, indice) =>
        new Intl.DateTimeFormat('es-AR', { month: 'long' }).format(new Date(2026, indice, 1))
    );
    const partesMes = encabezadoMes.textContent.trim().split(/\s+/);
    let mes = meses.indexOf(partesMes[0].toLocaleLowerCase('es-AR'));
    let anio = Number(partesMes[1]);
    if (mes < 0 || !Number.isInteger(anio)) return;

    const turnosOriginales = [...listaTurnos.children].map((turno) => turno.cloneNode(true));
    const fechaInicial = obtenerFechaInicialAgenda(encabezadoDia);
    if (!fechaInicial) return;

    const conteosIniciales = new Map(
        [...grilla.querySelectorAll('.btn-dia')].flatMap((dia) => {
            const cantidad = dia.querySelector('.badge-turnos-dia')?.textContent.trim().match(/\d+/)?.[0];
            return cantidad
                ? [[Number(dia.querySelector('.num-dia').textContent.trim()), Number(cantidad)]]
                : [];
        })
    );
    const fechaTurnosOriginales = fechaISOProfesional(fechaInicial);
    const fechaGuardada = sessionStorage.getItem(CLAVES_PROFESIONAL.agenda);
    let fechaSeleccionada = fechaGuardada || fechaTurnosOriginales;
    const mensajeSinTurnos = document.createElement('p');
    mensajeSinTurnos.className = 'mensaje-agenda-vacia';
    mensajeSinTurnos.setAttribute('role', 'status');
    mensajeSinTurnos.style.cssText = 'margin:0; color:#64748b;';

    const obtenerTurnosDelDia = (fecha) => {
        if (fecha !== fechaTurnosOriginales) return [];
        return turnosOriginales.map((turno) => turno.cloneNode(true));
    };

    const aplicarEstados = (fecha, turnos) => {
        const estadosGuardados = leerDatoProfesional(CLAVES_PROFESIONAL.estadosTurnos, {});
        turnos.forEach((turno) => {
            const insignia = turno.querySelector('.chip-estado');
            const paciente = turno.querySelector('.nombre-paciente');
            const hora = turno.querySelector('.hora-turno-agenda strong');
            if (!insignia || !paciente || !hora) return;
            const estadoGuardado = estadosGuardados[obtenerClaveTurno(fecha, paciente.textContent, hora.textContent)];
            const estado = ['pendiente', 'atendido', 'ausente'].includes(estadoGuardado)
                ? estadoGuardado
                : null;
            if (estado) {
                insignia.classList.remove('estado-pendiente', 'estado-atendido', 'estado-ausente');
                insignia.classList.add(`estado-${estado}`);
                insignia.textContent = estado.charAt(0).toLocaleUpperCase('es-AR') + estado.slice(1);
            }
        });
    };

    const renderizarDia = () => {
        const fecha = new Date(`${fechaSeleccionada}T12:00:00`);
        encabezadoDia.textContent = formatearFechaProfesional(fecha, {
            weekday: 'long',
            day: 'numeric',
            month: 'long',
            year: 'numeric'
        });

        const turnos = obtenerTurnosDelDia(fechaSeleccionada);
        const cantidadIndicada = fecha.getFullYear() === Number(partesMes[1]) &&
            fecha.getMonth() === meses.indexOf(partesMes[0].toLocaleLowerCase('es-AR'))
            ? conteosIniciales.get(fecha.getDate()) || 0
            : 0;
        const cantidad = Math.max(cantidadIndicada, turnos.length);
        subtitulo.textContent = `${cantidad} ${cantidad === 1 ? 'turno programado' : 'turnos programados'}`;
        listaTurnos.replaceChildren();

        if (turnos.length) {
            aplicarEstados(fechaSeleccionada, turnos);
            listaTurnos.append(...turnos);
            turnos.forEach((turno) => {
                const insignia = turno.querySelector('.chip-estado');
                if (insignia) agregarSelectorEstadoTurno(insignia, fechaSeleccionada);
            });
        } else {
            mensajeSinTurnos.textContent = cantidad
                ? 'El detalle de estos turnos no está cargado en esta vista.'
                : 'No hay turnos programados para esta fecha.';
            listaTurnos.append(mensajeSinTurnos);
        }
    };

    const dibujarCalendario = () => {
        encabezadoMes.textContent = formatearFechaProfesional(new Date(anio, mes, 1), {
            month: 'long',
            year: 'numeric'
        });
        grilla.replaceChildren();

        for (let espacio = 0; espacio < new Date(anio, mes, 1).getDay(); espacio += 1) {
            const vacio = document.createElement('span');
            vacio.className = 'dia-vacio';
            vacio.setAttribute('aria-hidden', 'true');
            grilla.append(vacio);
        }

        const cantidadDias = new Date(anio, mes + 1, 0).getDate();
        for (let dia = 1; dia <= cantidadDias; dia += 1) {
            const fecha = fechaISOProfesional(new Date(anio, mes, dia));
            const boton = document.createElement('button');
            boton.type = 'button';
            boton.className = 'btn-dia';
            boton.setAttribute('aria-pressed', String(fecha === fechaSeleccionada));
            boton.setAttribute('aria-label', formatearFechaProfesional(new Date(anio, mes, dia), {
                weekday: 'long',
                day: 'numeric',
                month: 'long',
                year: 'numeric'
            }));

            const numero = document.createElement('span');
            numero.className = 'num-dia';
            numero.textContent = String(dia);
            boton.append(numero);

            const cantidad = anio === Number(partesMes[1]) &&
                mes === meses.indexOf(partesMes[0].toLocaleLowerCase('es-AR'))
                ? conteosIniciales.get(dia) || 0
                : 0;
            if (cantidad) {
                const insignia = document.createElement('span');
                insignia.className = 'badge-turnos-dia';
                insignia.textContent = `${cantidad} ${cantidad === 1 ? 'turno' : 'turnos'}`;
                boton.append(insignia);
            }

            if (fecha === fechaSeleccionada) boton.classList.add('dia-seleccionado');
            boton.addEventListener('click', () => {
                fechaSeleccionada = fecha;
                sessionStorage.setItem(CLAVES_PROFESIONAL.agenda, fecha);
                dibujarCalendario();
                renderizarDia();
            });
            grilla.append(boton);
        }
    };

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
            fechaSeleccionada = fechaISOProfesional(new Date(anio, mes, 1));
            sessionStorage.setItem(CLAVES_PROFESIONAL.agenda, fechaSeleccionada);
            dibujarCalendario();
            renderizarDia();
        });
    });

    const fechaRestaurada = new Date(`${fechaSeleccionada}T12:00:00`);
    if (!Number.isFinite(fechaRestaurada.getTime())) fechaSeleccionada = fechaTurnosOriginales;
    else {
        anio = fechaRestaurada.getFullYear();
        mes = fechaRestaurada.getMonth();
    }

    dibujarCalendario();
    renderizarDia();
}

function inicializarListaPacientes() {
    const lista = document.querySelector('.lista-pacientes-resumen');
    if (!lista) return;

    const tarjetas = [...lista.querySelectorAll('.tarjeta-paciente-item')];
    const panel = document.querySelector('.contenedor-tarjetas-pacientes > section:last-child');
    const campos = [...(panel?.querySelectorAll('.ficha-datos-contacto p') || [])];
    const valoresIniciales = new Map(campos.map((campo) => [
        campo.querySelector('strong')?.textContent.replace(':', '').trim(),
        campo.lastChild.textContent.trim()
    ]));
    const historialInicial = [...(panel?.querySelectorAll('.lista-historial-paciente .item-turno-agenda') || [])]
        .map((item) => item.cloneNode(true));
    const turnoInicial = panel?.querySelector('.item-turno-agenda')?.cloneNode(true);
    const primerNombre = tarjetas[0]?.querySelector('.info-paciente-mini strong')?.textContent.trim();
    const datosPacienteGuardados = leerDatoProfesional('pausa.fichasPacientesProfesional', {});

    const actualizarDetalle = (tarjeta) => {
        const nombre = tarjeta.querySelector('.info-paciente-mini strong').textContent.trim();
        const datos = datosPacienteGuardados[obtenerNombreNormalizado(nombre)];
        const datosDisponibles = nombre === primerNombre;

        tarjetas.forEach((opcion) => {
            const seleccionado = opcion === tarjeta;
            opcion.classList.toggle('paciente-seleccionado', seleccionado);
            opcion.setAttribute('aria-pressed', String(seleccionado));
        });

        campos.forEach((campo) => {
            const etiqueta = campo.querySelector('strong')?.textContent.replace(':', '').trim();
            if (!etiqueta) return;
            const valor = datos?.[etiqueta] ?? (datosDisponibles ? valoresIniciales.get(etiqueta) : null);
            campo.lastChild.textContent = ` ${valor || 'No disponible'}`;
        });

        const proximoTurno = panel.querySelector('.item-turno-agenda');
        const fechaTurno = tarjeta.querySelector('.info-paciente-mini span')?.textContent
            .replace(/^Próximo turno:\s*/i, '').trim();
        const textoFechaTurno = proximoTurno.querySelector('strong');
        const estadoTurno = proximoTurno.querySelector('.chip-estado');
        textoFechaTurno.textContent = fechaTurno
            ? datos?.hora
                ? `${fechaTurno} - ${datos.hora}`
                : datosDisponibles && turnoInicial
                    ? turnoInicial.querySelector('strong').textContent
                    : fechaTurno
            : 'No hay turno próximo registrado';
        if (!fechaTurno && estadoTurno) estadoTurno.hidden = true;
        else if (estadoTurno) estadoTurno.hidden = false;
        if (datos?.estado && estadoTurno) {
            estadoTurno.classList.remove('estado-pendiente', 'estado-atendido', 'estado-ausente');
            estadoTurno.classList.add(`estado-${datos.estado}`);
            estadoTurno.textContent = datos.estado.charAt(0).toLocaleUpperCase('es-AR') + datos.estado.slice(1);
        } else if (estadoTurno && fechaTurno) {
            estadoTurno.classList.remove('estado-atendido', 'estado-ausente');
            estadoTurno.classList.add('estado-pendiente');
            estadoTurno.textContent = 'Pendiente';
        }

        const historial = panel.querySelector('.lista-historial-paciente');
        if (historial) {
            historial.replaceChildren();
            const registros = datos?.historial || (datosDisponibles
                ? historialInicial.map((item) => item.cloneNode(true))
                : []);
            if (registros.length) {
                registros.forEach((registro) => {
                    if (registro instanceof Node) historial.append(registro);
                    else {
                        const item = document.createElement('div');
                        item.className = 'item-turno-agenda';
                        item.textContent = registro.fecha || 'Registro de turno';
                        if (registro.estado) {
                            const estado = document.createElement('span');
                            estado.className = `chip-estado estado-${registro.estado}`;
                            estado.textContent = registro.estado;
                            item.append(estado);
                        }
                        historial.append(item);
                    }
                });
            } else {
                const aviso = document.createElement('p');
                aviso.textContent = 'No hay historial disponible para este paciente.';
                historial.append(aviso);
            }
        }
    };

    const buscador = document.querySelector('.input-busqueda-paciente');
    let mensajeSinResultados = lista.querySelector('.mensaje-sin-pacientes');
    if (!mensajeSinResultados) {
        mensajeSinResultados = document.createElement('p');
        mensajeSinResultados.className = 'mensaje-sin-pacientes';
        mensajeSinResultados.setAttribute('role', 'status');
        mensajeSinResultados.textContent = 'No se encontraron pacientes.';
        mensajeSinResultados.hidden = true;
        lista.append(mensajeSinResultados);
    }

    const filtrar = () => {
        const busqueda = obtenerNombreNormalizado(buscador?.value || '');
        let visibles = 0;
        tarjetas.forEach((tarjeta) => {
            const dni = tarjeta.dataset.dni || '';
            const coincide = obtenerNombreNormalizado(`${tarjeta.textContent} ${dni}`).includes(busqueda);
            tarjeta.hidden = !coincide;
            tarjeta.style.display = coincide ? '' : 'none';
            if (coincide) visibles += 1;
        });
        mensajeSinResultados.hidden = visibles > 0;
    };

    buscador?.addEventListener('input', filtrar);
    tarjetas.forEach((tarjeta) => {
        tarjeta.setAttribute('aria-pressed', String(tarjeta.classList.contains('paciente-seleccionado')));
        tarjeta.addEventListener('click', () => actualizarDetalle(tarjeta));
    });

    const inicial = tarjetas.find((tarjeta) => tarjeta.classList.contains('paciente-seleccionado')) || tarjetas[0];
    if (inicial) actualizarDetalle(inicial);
}

function inicializarConfiguracionProfesional() {
    const formularios = [...document.querySelectorAll('.main-profesional-menu .form-perfil')];
    if (!formularios.length) return;

    const cuenta = leerDatoProfesional(CLAVES_PROFESIONAL.cuenta, {});
    const formularioDatos = formularios[0];
    const correo = formularioDatos.querySelector('#email');
    if (correo) {
        correo.required = true;
        if (cuenta.email) correo.value = cuenta.email;
    }

    formularioDatos.querySelector('button[type="button"]')?.addEventListener('click', () => {
        if (!formularioDatos.reportValidity()) return;
        const nuevosDatos = { ...cuenta, email: correo.value.trim() };
        localStorage.setItem(CLAVES_PROFESIONAL.cuenta, JSON.stringify(nuevosDatos));
        window.alert('Datos del profesional actualizados con éxito.');
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
    formularioClave.querySelector('button[type="button"]')?.addEventListener('click', () => {
        if (claveNueva.value !== confirmarClave.value) {
            confirmarClave.setCustomValidity('Las contraseñas nuevas no coinciden.');
        }
        if (!formularioClave.reportValidity()) return;

        formularioClave.reset();
        window.alert('No se modificó: falta conectar el servicio de autenticación.');
    });
}

inicializarAgendaProfesional();
inicializarEstadosTurnos();
inicializarListaPacientes();
inicializarConfiguracionProfesional();
