<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Las páginas de retorno de MercadoPago deben renderizar (antes fallaban con "Undefined variable $slot").
 */
class PagoVistasTest extends TestCase
{
    public function test_pagina_de_pago_exito_renderiza(): void
    {
        $this->get('/pago/exito')->assertOk()->assertSee('Pago aprobado');
    }

    public function test_pagina_de_pago_pendiente_renderiza(): void
    {
        $this->get('/pago/pendiente')->assertOk()->assertSee('Pago pendiente');
    }

    public function test_pagina_de_pago_error_renderiza(): void
    {
        $this->get('/pago/error')->assertOk()->assertSee('no se pudo completar');
    }
}
