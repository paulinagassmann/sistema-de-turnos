<?php
// Validaciones compartidas por los formularios de alta (secretario, profesional, ...).
// El navegador también valida (js/validaciones-formulario.js), pero la validación
// definitiva es siempre esta, porque la del navegador se puede saltear.

// Pone en mayúscula la primera letra de cada palabra ("maría JOSÉ" -> "María José").
function capitalizar_nombre(string $texto): string
{
    $texto = preg_replace('/\s+/u', ' ', trim($texto));
    return mb_convert_case(mb_strtolower($texto, 'UTF-8'), MB_CASE_TITLE, 'UTF-8');
}

// DNI: 7 u 8 dígitos (sin puntos ni espacios).
function es_dni_valido(string $dni): bool
{
    return preg_match('/^\d{7,8}$/', $dni) === 1;
}

// Matrícula profesional: solo números, entre 2 y 7 dígitos.
function es_matricula_valida(string $matricula): bool
{
    return preg_match('/^\d{2,7}$/', $matricula) === 1;
}

// Nombre o apellido: solo letras (con tildes y ñ), un espacio entre palabras
// y al menos 2 letras en CADA palabra (ej: "Li" o "Ana María" sí; "Juan P" no).
function es_nombre_valido(string $texto): bool
{
    return preg_match('/^\p{L}{2,}(?: \p{L}{2,})*$/u', $texto) === 1;
}

// Solo letras (con tildes y ñ), permitiendo un espacio entre palabras, sin largo mínimo
// por palabra. Se usa para textos como la especialidad ("Psicología y Neurociencia").
function es_letras_valido(string $texto): bool
{
    return preg_match('/^\p{L}+(?: \p{L}+)*$/u', $texto) === 1;
}

// Letras (con tildes y ñ) y números, permitiendo un espacio entre palabras. Ej: "OSDE 210".
function es_alfanumerico_valido(string $texto): bool
{
    return preg_match('/^[\p{L}\d]+(?: [\p{L}\d]+)*$/u', $texto) === 1;
}

// Fecha AAAA-MM-DD real (año de 4 dígitos) dentro del rango indicado.
function es_fecha_valida(string $fecha, string $minima, string $maxima): bool
{
    $objeto = DateTime::createFromFormat('!Y-m-d', $fecha);
    return $objeto
        && $objeto->format('Y-m-d') === $fecha
        && $fecha >= $minima
        && $fecha <= $maxima;
}

// Teléfono: +54 opcional y luego entre 10 y 13 números.
// Devuelve el teléfono en el formato único del sistema (+543436985148) o null si no es válido.
function normalizar_telefono(string $telefono): ?string
{
    if (preg_match('/^\+54 ?(\d{10,13})$/', $telefono, $partes)) {
        return '+54' . $partes[1];
    }
    if (preg_match('/^\d{10,13}$/', $telefono)) {
        return $telefono;
    }
    return null;
}

// Correo: usuario + @ + dominio + extensión final permitida.
// - Usuario: letras, números y . _ % + - (sin punto al inicio, al final ni dos seguidos).
// - Dominio: el nombre tras el @ tiene al menos 3 caracteres (ej: gmail; no "m.com").
//   Cada parte empieza y termina con letra o número (no "-gmail" ni "gmail-").
//   Puede tener partes intermedias (ej: uader.edu.ar, mail.uner.edu.ar).
// - Extensión final: solo com, org, net, edu, mil, gov, ar, com.ar u org.ar
//   (rechaza cosas como "pablo@gmail.com.ar.pablo").
// - Máximo 150 caracteres (tamaño de la columna en la base de datos).
// Este mismo patrón está copiado en el atributo pattern de todos los inputs de correo
// (allí en minúsculas: el JS pasa el correo a minúsculas mientras se escribe).
const PATRON_CORREO = '[A-Za-z0-9_%+\-]+(\.[A-Za-z0-9_%+\-]+)*@[A-Za-z0-9][A-Za-z0-9\-]+[A-Za-z0-9](\.[A-Za-z0-9]([A-Za-z0-9\-]*[A-Za-z0-9])?)*\.(com\.ar|org\.ar|com|org|net|edu|mil|gov|ar)';

function es_correo_valido(string $correo): bool
{
    // La "i" final ignora mayúsculas: "Juan@Gmail.COM" también es válido.
    return mb_strlen($correo, 'UTF-8') <= 150
        && filter_var($correo, FILTER_VALIDATE_EMAIL) !== false
        && preg_match('/^' . PATRON_CORREO . '$/i', $correo) === 1;
}
