<?php

/**
 * Rubros (verticales) del sitio. Cada rubro tiene sus propias tiendas, corridas y productos
 * (columna `rubro` en tiendas/corridas/alertas). Para sumar un rubro nuevo: agregarlo acá,
 * crear su scraper y (si hace falta) sus pestañas.
 *
 *  - slug        : valor de la columna `rubro` y prefijo de URL (perfumes = sin prefijo, por compatibilidad)
 *  - tabs        : pestañas visibles en los menús => [etiqueta, icono, en_sidebar]
 *  - tabs_rutas  : pestañas válidas por URL (incluye las ocultas de los menús)
 *  - tab_inicial : pestaña a la que redirige la raíz del rubro
 *  - generos     : valores de productos.genero => etiqueta (ropa usa los mismos valores + 'ninos')
 */
return [
    'default' => 'perfumes',

    'rubros' => [
        'perfumes' => [
            'nombre'      => 'Perfumes',
            'icono'       => '🧴',
            'items_label' => 'Perfumes en oferta',
            'prefijo'     => '',
            'tab_inicial' => 'joyas',
            'tabs' => [
                'joyas'        => ['Destacados',    '💎', true],
                'tabla'        => ['Tabla completa', '📋', true],
                'cambios'      => ['Cambios recientes', '🔁', true],
                'distribucion' => ['Distribución',  '📊', false],
                'evolucion'    => ['Evolución',     '📈', true],
                'transfer'     => ['Transferencia', '🏦', false],
                'marcas'       => ['Marcas',        '🏷️', false],
            ],
            'tabs_rutas' => ['joyas', 'tabla', 'cambios', 'comparar', 'seguir', 'tiendas', 'distribucion', 'evolucion', 'transfer', 'marcas', 'conclusiones'],
            'generos' => ['masculino' => 'Masculino', 'femenino' => 'Femenino', 'unisex' => 'Unisex'],
            'filtro_ml'  => true,
            'filtro_categoria' => false,
        ],

        'ropa' => [
            'nombre'      => 'Ropa y calzado',
            'icono'       => '👕',
            'items_label' => 'Productos en oferta',
            'prefijo'     => 'ropa',
            'tab_inicial' => 'tabla',
            'tabs' => [
                'tabla'     => ['Ofertas',            '📋', true],
                'cambios'   => ['Cambios recientes',  '🔁', true],
                'evolucion' => ['Evolución',          '📈', true],
                'comparar'  => ['Comparar tiendas',   '⚖️', true],
                'seguir'    => ['Seguir producto',    '🔎', true],
                'marcas'    => ['Marcas',             '🏷️', true],
            ],
            'tabs_rutas' => ['tabla', 'cambios', 'comparar', 'tiendas', 'evolucion', 'seguir', 'marcas', 'distribucion'],
            'generos' => ['masculino' => 'Hombre', 'femenino' => 'Mujer', 'ninos' => 'Niños', 'unisex' => 'Unisex'],
            'filtro_ml'  => false,
            'filtro_categoria' => true,
        ],
    ],
];
