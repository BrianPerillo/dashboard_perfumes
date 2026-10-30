<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * La raíz redirige a la pestaña por defecto del dashboard.
     */
    public function test_la_raiz_redirige_a_joyas(): void
    {
        $this->get('/')->assertRedirect('/joyas');
    }
}
