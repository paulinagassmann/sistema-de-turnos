(() => {
    const calendario = document.querySelector('.grilla-seleccion-turno');
    const formulario = document.querySelector('form[action="../procesos/secretario/procesar-dar-turno.php"]');
    if (!calendario || !formulario) return;

    const grilla = calendario.querySelector('.grilla-dias');
    const encabezadoMes = calendario.querySelector('.cabecera-mes h3');
    const encabezadoFecha = calendario.querySelector('.cabecera-horarios h3');
    const listaHoras = calendario.querySelector('.lista-horas');
    const inputFecha = formulario.querySelector('#inputFechaSeleccionada');
    const inputHora = formulario.querySelector('#inputHoraSeleccionada');
    const botonEnviar = formulario.querySelector('button[type="submit"]');
    const [mesAnterior, mesSiguiente] = calendario.querySelectorAll('.btn-nav-mes');
    const horariosPorFecha = JSON.parse(grilla.dataset.slots || '{}');
    const fechas = Object.keys(horariosPorFecha).sort();
    const [anioHoy, mesHoy, diaHoy] = grilla.dataset.hoy.split('-').map(Number);
    const hoy = new Date(anioHoy, mesHoy - 1, diaHoy);
    const fechaISO = (fecha) => [
        fecha.getFullYear(),
        String(fecha.getMonth() + 1).padStart(2, '0'),
        String(fecha.getDate()).padStart(2, '0')
    ].join('-');
    const fechaDesdeISO = (valor) => {
        const [anio, mes, dia] = valor.split('-').map(Number);
        return new Date(anio, mes - 1, dia);
    };
    let fechaSeleccionada = fechas.length ? fechaDesdeISO(fechas[0]) : hoy;

    const mostrarHorarios = () => {
        const iso = fechaISO(fechaSeleccionada);
        encabezadoFecha.textContent = new Intl.DateTimeFormat('es-AR', {
            weekday: 'long',
            day: 'numeric',
            month: 'long'
        }).format(fechaSeleccionada);
        inputFecha.value = iso;
        inputHora.value = '';
        listaHoras.replaceChildren();

        const horarios = horariosPorFecha[iso] || [];
        if (!horarios.length) {
            const aviso = document.createElement('p');
            aviso.textContent = 'No hay horarios disponibles para esta fecha. Elegí otra.';
            listaHoras.append(aviso);
            return;
        }

        horarios.forEach((hora) => {
            const boton = document.createElement('button');
            boton.type = 'button';
            boton.className = 'btn-hora';
            boton.textContent = hora;
            boton.setAttribute('aria-pressed', 'false');
            boton.addEventListener('click', () => {
                listaHoras.querySelectorAll('.btn-hora').forEach((opcion) => {
                    const seleccionado = opcion === boton;
                    opcion.classList.toggle('hora-seleccionada', seleccionado);
                    opcion.setAttribute('aria-pressed', String(seleccionado));
                });
                inputHora.value = hora;
            });
            listaHoras.append(boton);
        });
    };

    const mostrarCalendario = () => {
        const anio = fechaSeleccionada.getFullYear();
        const mes = fechaSeleccionada.getMonth();
        encabezadoMes.textContent = new Intl.DateTimeFormat('es-AR', {
            month: 'long',
            year: 'numeric'
        }).format(fechaSeleccionada);
        grilla.replaceChildren();

        for (let espacio = 0; espacio < new Date(anio, mes, 1).getDay(); espacio += 1) {
            grilla.append(document.createElement('span'));
        }

        const cantidadDias = new Date(anio, mes + 1, 0).getDate();
        for (let dia = 1; dia <= cantidadDias; dia += 1) {
            const fecha = new Date(anio, mes, dia);
            const iso = fechaISO(fecha);
            const disponible = fecha >= hoy && (horariosPorFecha[iso] || []).length > 0;
            const boton = document.createElement('button');
            boton.type = 'button';
            boton.className = disponible ? 'dia-disponible' : 'dia-inactivo';
            boton.textContent = String(dia);
            boton.disabled = !disponible;
            boton.setAttribute('aria-pressed', String(iso === fechaISO(fechaSeleccionada)));
            if (iso === fechaISO(fechaSeleccionada)) boton.classList.add('dia-seleccionado');
            boton.addEventListener('click', () => {
                fechaSeleccionada = fecha;
                mostrarCalendario();
                mostrarHorarios();
            });
            grilla.append(boton);
        }

        mesAnterior.disabled = anio === hoy.getFullYear() && mes <= hoy.getMonth();
    };

    mesAnterior.addEventListener('click', () => {
        fechaSeleccionada = new Date(fechaSeleccionada.getFullYear(), fechaSeleccionada.getMonth() - 1, 1);
        mostrarCalendario();
        mostrarHorarios();
    });
    mesSiguiente.addEventListener('click', () => {
        fechaSeleccionada = new Date(fechaSeleccionada.getFullYear(), fechaSeleccionada.getMonth() + 1, 1);
        mostrarCalendario();
        mostrarHorarios();
    });

    formulario.addEventListener('submit', (evento) => {
        const paciente = formulario.querySelector('[name="paciente_id"]').value;
        const profesional = formulario.querySelector('[name="profesional_id"]').value;
        if (!paciente || !profesional || !inputFecha.value || !inputHora.value) {
            evento.preventDefault();
            window.alert('Seleccioná un paciente, un profesional, una fecha y un horario disponible.');
        }
    });

    botonEnviar.disabled = !formulario.querySelector('[name="profesional_id"]').value;
    mostrarCalendario();
    mostrarHorarios();
})();
