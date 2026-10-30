<?php

namespace Tests\Feature;

use App\Livewire\Dashboard;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use ReflectionMethod;
use Tests\TestCase;

/**
 * Auditoría de los filtros de ropa contra la corrida real de la base de desarrollo (solo lectura).
 * Se omite si no hay corridas de ropa. Cada opción de cada filtro debe devolver filas coherentes.
 */
class AuditFiltrosRopaTest extends TestCase
{
    private function filas($comp): array
    {
        $inst = $comp->instance();
        $m = new ReflectionMethod($inst, 'buildTablaQuery');
        $m->setAccessible(true);
        return $m->invoke($inst)->limit(5000)->get()->all();
    }

    private function nuevo()
    {
        session()->flush();   // los filtros se guardan en sesión por rubro: cada caso arranca limpio
        return Livewire::test(Dashboard::class, ['activeTab' => 'tabla', 'rubro' => 'ropa']);
    }

    public function test_cada_opcion_de_cada_filtro_funciona_con_todas_las_tiendas(): void
    {
        if (config('database.default') !== 'mysql') {
            $this->markTestSkipped('Auditoría manual contra la base real: DB_CONNECTION=mysql DB_DATABASE=perfumes_descuentos php artisan test --filter=AuditFiltrosRopa (tarda ~3 min).');
        }
        if (!DB::table('corridas')->where('rubro', 'ropa')->exists()) {
            $this->markTestSkipped('No hay corridas de ropa.');
        }
        $comp = $this->nuevo();
        $base = $this->filas($comp);
        $this->assertNotEmpty($base);
        $tiendas = $comp->get('tiendas');
        $this->assertGreaterThanOrEqual(2, count($tiendas), 'se esperan varias tiendas de ropa');

        // 1) Cada tienda sola
        foreach (array_keys($tiendas) as $id) {
            $c = $this->nuevo()->set('tiendasSeleccionadas', [$id]);
            $filas = $this->filas($c);
            $this->assertNotEmpty($filas, "tienda {$tiendas[$id]} sin filas");
            $this->assertSame([$tiendas[$id]], array_values(array_unique(array_map(fn($r) => $r->tienda, $filas))), "tienda {$tiendas[$id]}");
        }

        // 2) Categorías
        foreach (['ropa', 'calzado', 'accesorios'] as $cat) {
            $c = $this->nuevo()->call('setCategoria', $cat);
            $filas = $this->filas($c);
            $this->assertNotEmpty($filas, "categoria $cat");
        }

        // 3) Tipo / disciplina / línea: dejar solo una opción => filas de esa opción (o sin dato)
        foreach ([['tiposDisponibles', 'tiposExcluidos', 'tipo'], ['disciplinasDisponibles', 'disciplinasExcluidas', 'disciplina'], ['lineasDisponibles', 'lineasExcluidas', 'linea']] as [$disp, $prop, $col]) {
            $opciones = array_column($comp->get($disp), 'v');
            $this->assertNotEmpty($opciones, "sin opciones de $col");
            foreach (array_slice($opciones, 0, 12) as $op) {   // muestra: recorrerlas todas tarda minutos
                $c = $this->nuevo()->set($prop, array_values(array_diff($opciones, [$op])));
                $filas = $this->filas($c);
                $this->assertNotEmpty($filas, "$col = $op sin filas");
                foreach ($filas as $r) {
                    if (!property_exists($r, $col)) {
                        break;
                    }
                    $this->assertTrue($r->$col === null || $r->$col === $op, "$col = $op devolvio {$r->$col}");
                }
            }
        }

        // 4) Talles: seleccionar uno => todas las filas lo traen
        $probados = 0;
        foreach ($comp->get('tallesDisponibles') as $g) {
            foreach (array_slice($g['items'], 0, 5) as $t) {
                $c = $this->nuevo()->set('tallesSeleccionados', [$t['v']]);
                $filas = $this->filas($c);
                $this->assertNotEmpty($filas, "talle {$t['v']} sin filas");
                $probados++;
            }
        }
        $this->assertGreaterThan(5, $probados);

        // 5) Precio, búsqueda, marca y agrupar colores
        $c = $this->nuevo()->set('precioMin', 20000)->set('precioMax', 40000);
        $filas = $this->filas($c);
        $this->assertNotEmpty($filas);
        foreach ($filas as $r) {
            $this->assertGreaterThanOrEqual(20000, (float) $r->precio_actual);
            $this->assertLessThanOrEqual(40000, (float) $r->precio_actual);
        }
        $c = $this->nuevo()->set('tablaFiltroBuscar', 'zapatillas');
        $this->assertNotEmpty($this->filas($c));
        $c = $this->nuevo()->call('toggleTablaFiltroMarca', 'Nike');
        $this->assertSame(['Nike'], array_values(array_unique(array_map(fn($r) => $r->marca, $this->filas($c)))));
        $c = $this->nuevo()->set('agruparColores', true);
        $this->assertNotEmpty($this->filas($c));
    }
}
