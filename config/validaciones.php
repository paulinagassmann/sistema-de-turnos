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

// DNI: exactamente 8 dígitos.
function es_dni_valido(string $dni): bool
{
    return preg_match('/^\d{8}$/', $dni) === 1;
}

// Matrícula profesional: solo números, entre 4 y 6 dígitos.
function es_matricula_valida(string $matricula): bool
{
    return preg_match('/^\d{4,6}$/', $matricula) === 1;
}

// Solo letras (con tildes y ñ), permitiendo un espacio entre palabras.
function es_nombre_valido(string $texto): bool
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

// Correo sin espacios, con @ y un dominio con punto (ej: .com, .com.ar).
function es_correo_valido(string $correo): bool
{
    return filter_var($correo, FILTER_VALIDATE_EMAIL) !== false
        && preg_match('/^[A-Za-z0-9._%+\-]+@[A-Za-z0-9\-]+(\.[A-Za-z0-9\-]+)*\.[A-Za-z]{2,}$/', $correo) === 1;
}
