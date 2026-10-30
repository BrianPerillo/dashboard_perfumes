<?php

namespace App\Support;

/**
 * Clave para reconocer el MISMO modelo de ropa/calzado en tiendas distintas (comparador entre tiendas).
 * Cada tienda guarda su propio código en productos.referencia:
 *   - Nike:   AT5732-004          - Puma:   107154_01          - Topper: 163713
 *   - Dexter: prefijo de marca de 2 letras + código de la marca: NIAT5732-004, PU107154-01, TO163713
 * La clave es el código de la marca en minúsculas, con "_" como "-" y sin espacios. Si el código no sigue ese formato
 * la clave no cruzará con otra tienda (no pasa nada: simplemente no se compara).
 */
class Modelo
{
    public static function clave(?string $tienda, ?string $referencia): string
    {
        $r = mb_strtolower(trim((string) $referencia));
        if ($tienda === 'dexter' && mb_strlen($r) > 4) {
            $r = mb_substr($r, 2);
        }
        return str_replace(['_', ' '], ['-', ''], $r);
    }
}
