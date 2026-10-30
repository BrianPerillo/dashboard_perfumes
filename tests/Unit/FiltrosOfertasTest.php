<?php

namespace Tests\Unit;

use App\Models\Alerta;
use App\Models\Observacion;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class FiltrosOfertasTest extends TestCase
{
    public function test_filtro_de_fuentes_usa_like_para_badge_y_acepta_null(): void
    {
        $query = DB::table('observaciones as o');
        Observacion::filtrarFuentesValidas($query, 'o.fuente');

        $sql = $query->toSql();
        $this->assertStringContainsString('fuente` is null', str_replace('"', '`', $sql));
        $this->assertStringContainsString('not like', $sql);
        $this->assertSame(['dos_precios', 'badge_%'], $query->getBindings());
    }

    public function test_marcas_excluidas_se_comparan_en_minusculas(): void
    {
        $this->assertSame(['rimmel'], Observacion::MARCAS_EXCLUIDAS);
    }

    public function test_escapar_markdown_de_telegram(): void
    {
        $this->assertSame('Eros\_Pour \*Femme\* \[x] \`y\`', Alerta::escaparMarkdown('Eros_Pour *Femme* [x] `y`'));
        $this->assertSame('Dylan Blue', Alerta::escaparMarkdown('Dylan Blue'));
    }
}
