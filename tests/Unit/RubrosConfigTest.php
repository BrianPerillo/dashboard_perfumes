<?php

namespace Tests\Unit;

use Tests\TestCase;

/**
 * config/rubros.php es la fuente de verdad de los rubros (menús, rutas, géneros): debe ser consistente.
 */
class RubrosConfigTest extends TestCase
{
    public function test_el_rubro_por_defecto_existe(): void
    {
        $this->assertArrayHasKey(config('rubros.default'), config('rubros.rubros'));
    }

    public function test_cada_rubro_tiene_la_estructura_minima(): void
    {
        foreach (config('rubros.rubros') as $slug => $r) {
            foreach (['nombre', 'icono', 'prefijo', 'tab_inicial', 'tabs', 'tabs_rutas', 'generos'] as $k) {
                $this->assertArrayHasKey($k, $r, "rubro {$slug} sin '{$k}'");
            }
            $this->assertContains($r['tab_inicial'], $r['tabs_rutas'], "tab_inicial de {$slug} no es una ruta válida");
            foreach (array_keys($r['tabs']) as $tab) {
                $this->assertContains($tab, $r['tabs_rutas'], "pestaña {$tab} de {$slug} sin ruta");
            }
        }
    }

    public function test_solo_el_rubro_por_defecto_va_sin_prefijo(): void
    {
        foreach (config('rubros.rubros') as $slug => $r) {
            if ($slug === config('rubros.default')) {
                $this->assertSame('', $r['prefijo']);
            } else {
                $this->assertNotSame('', $r['prefijo'], "rubro {$slug} necesita prefijo de URL");
            }
        }
    }

    public function test_la_raiz_de_cada_rubro_redirige_a_su_pestana_inicial(): void
    {
        foreach (config('rubros.rubros') as $r) {
            if ($r['prefijo']) {
                $this->get('/' . $r['prefijo'])->assertRedirect('/' . $r['prefijo'] . '/' . $r['tab_inicial']);
            }
        }
    }

    public function test_generos_de_ropa_incluyen_ninos(): void
    {
        $this->assertArrayHasKey('ninos', config('rubros.rubros.ropa.generos'));
        $this->assertArrayNotHasKey('ninos', config('rubros.rubros.perfumes.generos'));
    }
}
