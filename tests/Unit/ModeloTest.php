<?php

namespace Tests\Unit;

use App\Support\Modelo;
use PHPUnit\Framework\TestCase;

class ModeloTest extends TestCase
{
    public function test_dexter_pierde_el_prefijo_de_marca_y_cruza_con_nike(): void
    {
        $this->assertSame('at5732-004', Modelo::clave('nike', 'AT5732-004'));
        $this->assertSame('at5732-004', Modelo::clave('dexter', 'NIAT5732-004'));
    }

    public function test_puma_y_topper(): void
    {
        $this->assertSame('107154-01', Modelo::clave('puma', '107154_01'));
        $this->assertSame('107154-01', Modelo::clave('dexter', 'PU107154-01'));
        $this->assertSame('163713', Modelo::clave('topper', '163713'));
        $this->assertSame('163713', Modelo::clave('dexter', 'TO163713'));
    }

    public function test_referencia_vacia(): void
    {
        $this->assertSame('', Modelo::clave('nike', null));
    }
}
