<?php

namespace App\Support;

/**
 * Normalización de talles de CALZADO para poder filtrar/alertar de forma coherente.
 *
 * Nike publica el calzado con talles US cuyo significado depende del género del producto:
 *   - masculino: 8, 9.5, 10…               -> talle de HOMBRE  -> token "H8", "H9.5", "H10"
 *   - femenino : 5, 6.5, 7…                -> talle de MUJER   -> token "M5", "M6.5", "M7"
 *   - unisex   : "H 10 / M 11.5"            -> AMBOS talles     -> "H10" y "M11.5"
 *   - unisex con número simple              -> se asume talle de hombre (H)
 *   - niños: "5.5Y", "13C"                  -> sin cambios
 * Ropa y accesorios no se modifican. Equivalencia de referencia: talle M = talle H + 1,5.
 *
 * Hay una copia de esta lógica en Python (descuentos_ropa.py::normalizar_talles): mantenerlas iguales.
 */
class Talles
{
    /** @param string $talles lista separada por coma ("8, 8.5, 9"); devuelve la lista normalizada en el mismo formato */
    public static function normalizar(?string $genero, ?string $categoria, ?string $talles): string
    {
        $talles = (string) $talles;
        if ($categoria !== 'calzado' || trim($talles) === '') {
            return $talles;
        }

        $out = [];
        foreach (array_filter(array_map('trim', explode(',', $talles))) as $t) {
            if (preg_match('/^H\s*(\d+(?:\.\d)?)\s*\/\s*M\s*(\d+(?:\.\d)?)$/i', $t, $m)) {
                $out[] = 'H' . $m[1];
                $out[] = 'M' . $m[2];
            } elseif (preg_match('/^\d+(?:\.\d)?[CY]$/i', $t)) {
                $out[] = strtoupper($t);
            } elseif (preg_match('/^\d+(?:\.\d)?$/', $t)) {
                $out[] = match ($genero) {
                    'femenino' => 'M' . $t,
                    'ninos'    => $t,
                    default    => 'H' . $t,      // masculino y unisex
                };
            } else {
                $out[] = $t;
            }
        }

        return implode(', ', array_values(array_unique($out)));
    }
}
