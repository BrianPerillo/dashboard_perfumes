<?php

namespace Tests\Unit;

use App\Support\Talles;
use PHPUnit\Framework\TestCase;

class TallesTest extends TestCase
{
    public function test_calzado_de_hombre_usa_talle_h(): void
    {
        $this->assertSame('H8, H8.5, H10', Talles::normalizar('masculino', 'calzado', '8, 8.5, 10'));
    }

    public function test_calzado_de_mujer_usa_talle_m(): void
    {
        $this->assertSame('M5, M6.5', Talles::normalizar('femenino', 'calzado', '5, 6.5'));
    }

    public function test_formato_doble_se_parte_en_h_y_m(): void
    {
        $this->assertSame('H10, M11.5', Talles::normalizar('unisex', 'calzado', 'H 10 / M 11.5'));
        $this->assertSame('H5.5, M7, H6, M7.5', Talles::normalizar('femenino', 'calzado', 'H 5.5 / M 7, H 6 / M 7.5'));
    }

    public function test_unisex_con_numero_simple_se_asume_hombre(): void
    {
        $this->assertSame('H9', Talles::normalizar('unisex', 'calzado', '9'));
    }

    public function test_talle_argentino_no_lleva_prefijo(): void
    {
        $this->assertSame('44, 45', Talles::normalizar('masculino', 'calzado', '44, 45'));
        $this->assertSame('36.5, 38', Talles::normalizar('femenino', 'calzado', '36.5, 38'));
        $this->assertSame('M7', Talles::normalizar('femenino', 'calzado', '7'));
    }

    public function test_ninos_no_cambian(): void
    {
        $this->assertSame('5.5Y, 13C', Talles::normalizar('ninos', 'calzado', '5.5Y, 13C'));
        $this->assertSame('8, 9', Talles::normalizar('ninos', 'calzado', '8, 9'));
    }

    public function test_ropa_y_accesorios_no_se_modifican(): void
    {
        $this->assertSame('S, M, XL', Talles::normalizar('masculino', 'ropa', 'S, M, XL'));
        $this->assertSame('4, 5', Talles::normalizar('masculino', 'accesorios', '4, 5'));
        $this->assertSame('', Talles::normalizar('masculino', 'calzado', ''));
    }

    public function test_sin_repetidos(): void
    {
        $this->assertSame('H10, M11.5', Talles::normalizar('masculino', 'calzado', 'H 10 / M 11.5, 10'));
    }
}
