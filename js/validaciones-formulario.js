// Validaciones en vivo para formularios de alta. Se activan con el atributo data-validar:
//   data-validar="dni"       -> solo números, máximo 8
//   data-validar="matricula" -> solo números, entre 4 y 6
//   data-validar="alfanumerico" -> letras, números y espacios (sin caracteres especiales)
//   data-validar="letras"  -> solo letras y espacios, con mayúscula inicial en cada palabra
//   data-validar="telefono"  -> +54 precargado (borrable) y luego solo números (10 a 13),
//                               en el formato único del sistema: +543436985148
//   data-validar="correo"    -> no deja escribir espacios
// La validación definitiva se hace en el servidor (config/validaciones.php).

(() => {
    // Reemplaza el valor del campo intentando mantener el cursor donde estaba.
    const actualizarValor = (input, nuevoValor) => {
        if (input.value === nuevoValor) return;
        let posicion = null;
        try { posicion = input.selectionStart; } catch (e) { posicion = null; }
        const diferencia = input.value.length - nuevoValor.length;
        input.value = nuevoValor;
        if (posicion !== null) {
            const nuevaPosicion = Math.max(0, Math.min(nuevoValor.length, posicion - diferencia));
            try { input.setSelectionRange(nuevaPosicion, nuevaPosicion); } catch (e) { /* tipos sin selección */ }
        }
    };

    const capitalizar = (texto) => texto
        .toLocaleLowerCase('es')
        .replace(/(^|\s)(\p{L})/gu, (_, espacio, letra) => espacio + letra.toLocaleUpperCase('es'));

    const inicializarDni = (input) => {
        input.addEventListener('input', () => {
            actualizarValor(input, input.value.replace(/\D/g, '').slice(0, 8));
        });
    };

    const inicializarMatricula = (input) => {
        input.addEventListener('input', () => {
            actualizarValor(input, input.value.replace(/\D/g, '').slice(0, 6));
        });
    };

    // Letras, números y espacios (sin caracteres especiales). Ej: obra social "OSDE 210".
    const inicializarAlfanumerico = (input) => {
        input.addEventListener('input', () => {
            const limpio = input.value
                .replace(/[^\p{L}\d ]/gu, '')
                .replace(/ {2,}/g, ' ')
                .replace(/^ /, '');
            actualizarValor(input, limpio);
        });
        input.addEventListener('blur', () => {
            input.value = input.value.trim();
        });
    };

    const inicializarLetras = (input) => {
        input.addEventListener('input', () => {
            const limpio = input.value
                .replace(/[^\p{L} ]/gu, '')
                .replace(/ {2,}/g, ' ')
                .replace(/^ /, '');
            actualizarValor(input, capitalizar(limpio));
        });
        input.addEventListener('blur', () => {
            input.value = capitalizar(input.value.trim());
        });
    };

    const inicializarTelefono = (input) => {
        const limpiar = (valor) => {
            if (valor.startsWith('+54')) {
                return '+54' + valor.slice(3).replace(/\D/g, '').slice(0, 13);
            }
            if (valor.startsWith('+')) {
                // El usuario está borrando o escribiendo el prefijo.
                return '+' + valor.slice(1).replace(/\D/g, '').slice(0, 2);
            }
            return valor.replace(/\D/g, '').slice(0, 13);
        };

        const validar = () => {
            const valor = input.value.trim();
            const vacio = valor === '' || valor === '+54';
            let mensaje = '';
            if (vacio) {
                if (input.required) mensaje = 'Ingresá el número de teléfono.';
            } else if (!/^(\+54 ?)?\d{10,13}$/.test(valor)) {
                mensaje = 'Ingresá solo números: entre 10 y 13 después del +54.';
            }
            input.setCustomValidity(mensaje);
        };

        input.addEventListener('input', () => {
            actualizarValor(input, limpiar(input.value));
            validar();
        });
        validar();
    };

    const inicializarCorreo = (input) => {
        input.addEventListener('input', () => {
            actualizarValor(input, input.value.replace(/\s/g, ''));
        });
    };

    const inicializadores = {
        dni: inicializarDni,
        matricula: inicializarMatricula,
        letras: inicializarLetras,
        alfanumerico: inicializarAlfanumerico,
        telefono: inicializarTelefono,
        correo: inicializarCorreo,
    };

    // Fechas que no pueden ser futuras (ej: nacimiento). Con un "max" de 4 dígitos
    // el navegador tampoco deja escribir años de 5 cifras.
    document.querySelectorAll('input[type="date"][data-max-hoy]').forEach((input) => {
        const hoy = new Date();
        const dosDigitos = (n) => String(n).padStart(2, '0');
        input.max = `${hoy.getFullYear()}-${dosDigitos(hoy.getMonth() + 1)}-${dosDigitos(hoy.getDate())}`;
    });

    document.querySelectorAll('[data-validar]').forEach((input) => {
        const inicializar = inicializadores[input.dataset.validar];
        if (inicializar) inicializar(input);
    });
})();
