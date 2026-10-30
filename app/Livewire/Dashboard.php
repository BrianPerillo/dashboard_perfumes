<?php

namespace App\Livewire;

use App\Models\Corrida;
use App\Models\Observacion;
use App\Models\Tienda;
use Livewire\Component;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class Dashboard extends Component
{
    // ── Filtros globales ──────────────────────────────────────────
    public array  $tiendasSeleccionadas = [];
    public array  $generosSeleccionados = ['masculino', 'femenino', 'unisex'];
    public array  $mlDisponibles        = [];
    public array  $mlSeleccionados      = [];
    public float  $descMin = 0;
    public float  $descMax = 90;
    public string $tabActiva = 'joyas';

    // ── Rubro (perfumes | ropa | ...; ver config/rubros.php) ──────
    public string $rubro = 'perfumes';
    public array  $generosDisponibles = [];
    public string $categoriaFiltro = '';   // ropa: '' = todas | ropa | calzado | accesorios
    public bool   $agruparColores      = true;   // ropa: un renglón por modelo+precio (los colores se agrupan)
    public ?int   $precioMin           = null;   // ropa: rango de precio actual
    public ?int   $precioMax           = null;
    public array  $tiposExcluidos  = [];   // ropa: tipos DESMARCADOS (vacío = todos seleccionados, como Género)
    public array  $tallesSeleccionados = [];   // ropa: talles (el producto debe tener alguno disponible)
    public array  $lineasExcluidas = [];        // líneas desmarcadas (vacío = todas)
    public array  $lineasDisponibles   = [];
    public array  $disciplinasExcluidas = [];   // deportes desmarcados (vacío = todos)
    public array  $disciplinasDisponibles   = [];   // [['v' => 'Running', 'n' => 12], ...]
    public array  $tiposDisponibles    = [];   // [['v' => 'Remera', 'n' => 161], ...]
    public array  $tallesDisponibles   = [];   // [['grupo' => 'Letras', 'items' => [['v' => 'S', 'n' => 365], ...]], ...]

    // ── Snapshot IDs y listas ─────────────────────────────────────
    public int    $corridaActualId    = 0;
    public ?int   $corridaAnteriorId  = null;
    public array  $tiendas  = [];
    public array  $corridas = [];

    // ── Filtros de tabla ──────────────────────────────────────────
    public string $tablaSnapshotTipo   = 'ultimo';
    public array  $tablaFiltroMarcas   = [];
    public string $tablaMarcaBuscar    = '';
    public string $tablaFiltroBuscar   = '';
    public array  $tablaFiltroTiendas  = [];

    // ── Ordenamiento de tabla (3 niveles) ─────────────────────────
    public string $tablaSortCol1 = 'descuento_pct';
    public string $tablaSortDir1 = 'desc';
    public string $tablaSortCol2 = '';
    public string $tablaSortDir2 = 'desc';
    public string $tablaSortCol3 = '';
    public string $tablaSortDir3 = 'desc';

    // ── Favoritos ─────────────────────────────────────────────────
    public string $favoritoNombre = '';
    public array  $favoritosLista = [];

    // ── Marcas disponibles para sugerencias ──────────────────────
    public array  $marcasDisponibles = [];

    // ── Joyas ─────────────────────────────────────────────────────
    public float  $joyasUmbral = 30;
    public string $joyasTiendaFiltro = '';
    public bool   $joyasOcultarFreq  = false;

    // ── Cambios entre corridas ────────────────────────────────────
    public int    $cambiosDesdeCorrida = 0;
    public int    $cambiosHastaCorrida = 0;

    // ── Seguir un perfume ─────────────────────────────────────────
    public string $seguirProdKey = '';
    public array  $seguirOpciones = [];

    // ── Tabla: infinite scroll ────────────────────────────────────
    public int    $tablaPagina        = 1;
    public bool   $tablaTieneMas      = false;
    public int    $tablaTotalCount    = 0;

    // ── JSON para vistas ──────────────────────────────────────────
    public string $serieEvolucionJson = '[]';
    public string $resumenTiendasJson = '[]';
    public string $distribucionJson   = '[]';
    public string $tablaDatosJson     = '[]';
    public string $joyasJson          = '[]';
    public string $marcasJson         = '[]';
    public string $cambiosJson        = '{"nuevos":[],"salidos":[],"subas":[],"bajas":[]}';
    public string $seguirJson         = '[]';
    public string $compararJson       = '[]';
    public string $transferJson       = '[]';
    public string $conclusionesJson   = '{"puntos":[],"joyas":[],"narrativa":""}';

    // ── Marcas excluidas (no son perfumes) ───────────────────────
    private const MARCAS_EXCLUIDAS = Observacion::MARCAS_EXCLUIDAS;

    // ── Catálogo de joyas ─────────────────────────────────────────
    private const CATALOGO = [
        'Versace'           => ['tokens' => ['versace'],                          'lineas' => ['Eros Pour Femme' => ['eros pour femme'], 'Eros' => ['eros'], 'Dylan Blue' => ['dylan'], 'Pour Homme' => ['pour homme'], 'The Dreamer' => ['dreamer'], 'Bright Crystal' => ['bright crystal'], 'Crystal Noir' => ['crystal noir'], 'Yellow Diamond' => ['yellow diamond']]],
        'Dior'              => ['tokens' => ['dior'],                              'lineas' => ['Sauvage' => ['sauvage'], 'Fahrenheit' => ['fahrenheit'], 'Dior Homme' => ['dior homme', 'homme intense', 'homme sport'], 'Miss Dior' => ['miss dior'], "J'adore" => ["j'adore", 'jadore'], 'Hypnotic Poison' => ['hypnotic poison'], 'Dior Addict' => ['dior addict']]],
        'Chanel'            => ['tokens' => ['chanel'],                            'lineas' => ['Bleu de Chanel' => ['bleu de chanel', 'bleu'], 'Allure' => ['allure'], 'Coco Mademoiselle' => ['mademoiselle'], 'Coco Noir' => ['coco noir'], "N°5" => ["n°5", 'no 5', 'n 5'], 'Chance' => ['chance'], 'Gabrielle' => ['gabrielle']]],
        'Armani'            => ['tokens' => ['armani', 'giorgio'],                 'lineas' => ["Acqua di Giò" => ['acqua di gio', 'acqua', 'profondo'], 'Armani Code' => ['code'], 'Stronger With You' => ['stronger'], 'Sì' => ['si '], 'My Way' => ['my way']]],
        'Yves Saint Laurent'=> ['tokens' => ['saint laurent', 'ysl', 'yves'],     'lineas' => ['MYSLF' => ['myslf'], "La Nuit de L'Homme" => ['nuit'], "L'Homme" => ["l'homme", 'l homme'], 'Libre' => ['libre'], 'Black Opium' => ['black opium', 'opium'], 'Mon Paris' => ['mon paris'], 'Elle' => ['elle'], 'Cinema' => ['cinema']]],
        'Rabanne'           => ['tokens' => ['rabanne', 'paco'],                   'lineas' => ['Lady Million' => ['lady million'], '1 Million' => ['1 million'], 'Invictus' => ['invictus'], 'Phantom' => ['phantom'], 'Olympéa' => ['olympea', 'olympéa'], 'Fame' => ['fame']]],
        'Carolina Herrera'  => ['tokens' => ['herrera'],                           'lineas' => ['212 Sexy' => ['212 sexy'], '212 VIP Rosé' => ['vip rose', 'vip rosé'], '212' => ['212'], 'Bad Boy' => ['bad boy'], 'Very Good Girl' => ['very good girl'], 'Good Girl' => ['good girl'], 'CH Femme' => ['ch femme'], 'Chic' => ['chic']]],
        'Jean Paul Gaultier'=> ['tokens' => ['gaultier', 'jean paul'],             'lineas' => ['Le Male' => ['le male'], 'Ultra Male' => ['ultra male', 'ultra'], 'Scandal' => ['scandal'], 'Classique' => ['classique'], 'La Belle' => ['la belle'], 'Gaultier Divine' => ['divine']]],
        'Dolce & Gabbana'   => ['tokens' => ['dolce', 'gabbana'],                  'lineas' => ['The One' => ['the one'], 'Light Blue' => ['light blue'], 'K' => [' k edt', ' k edp', ' k by'], 'Devotion' => ['devotion'], "3 L'Imperatrice" => ['imperatrice'], 'Pour Femme' => ['dolce pour femme', 'pour femme']]],
        'Calvin Klein'      => ['tokens' => ['calvin klein', 'ck'],                'lineas' => ['CK One' => ['ck one'], 'Eternity' => ['eternity'], 'Euphoria' => ['euphoria'], 'Defy' => ['defy'], 'Obsession' => ['obsession'], 'CK Be' => ['ck be']]],
        'Hugo Boss'         => ['tokens' => ['hugo boss', 'boss'],                 'lineas' => ['Boss Bottled' => ['bottled'], 'Boss The Scent' => ['the scent'], 'Hugo Man' => ['hugo man'], 'Boss Alive' => ['alive'], 'Ma Vie' => ['ma vie'], 'Hugo Woman' => ['hugo woman']]],
        'Montblanc'         => ['tokens' => ['montblanc', 'mont blanc'],           'lineas' => ['Legend' => ['legend'], 'Explorer' => ['explorer'], 'Lady Emblem' => ['lady emblem']]],
        'Azzaro'            => ['tokens' => ['azzaro'],                            'lineas' => ['Wanted Girl' => ['wanted girl'], 'Wanted' => ['wanted'], 'Chrome' => ['chrome'], 'Azzaro Now' => ['azzaro now']]],
        'Givenchy'          => ['tokens' => ['givenchy'],                          'lineas' => ['Gentleman' => ['gentleman'], "L'Interdit" => ['interdit'], 'Irresistible' => ['irresistible'], 'Dahlia Divin' => ['dahlia']]],
        'Lancôme'           => ['tokens' => ['lancome', 'lancôme'],                'lineas' => ['La Vie Est Belle' => ['vie est belle'], 'Idôle' => ['idole', 'idôle'], 'La Nuit Trésor' => ['nuit tresor'], 'Trésor' => ['tresor'], 'Miracle' => ['miracle']]],
        'Issey Miyake'      => ['tokens' => ['issey', 'miyake'],                   'lineas' => ["L'Eau d'Issey" => ["l'eau d'issey", 'issey pour'], "A Drop d'Issey" => ["drop d'issey", 'drop issey']]],
        'Bvlgari'           => ['tokens' => ['bvlgari', 'bulgari'],                'lineas' => ['Man in Black' => ['man in black'], 'Aqva' => ['aqva'], 'Omnia' => ['omnia'], 'Rose Goldea' => ['goldea', 'rose goldea'], 'Splendida' => ['splendida']]],
        'Guerlain'          => ['tokens' => ['guerlain'],                          'lineas' => ["L'Homme Idéal" => ['ideal', 'idéal'], 'Shalimar' => ['shalimar'], 'Mon Guerlain' => ['mon guerlain'], 'Insolence' => ['insolence'], 'La Petite Robe Noire' => ['petite robe'], 'Aqua Allegoria' => ['aqua allegoria']]],
    ];

    // ─────────────────────────────────────────────────────────────
    public function mount(string $activeTab = 'joyas', string $rubro = 'perfumes'): void
    {
        if (!config("rubros.rubros.$rubro")) {
            $rubro = config('rubros.default');
        }
        $this->rubro = $rubro;
        $this->generosDisponibles = array_keys(config("rubros.rubros.$rubro.generos"));
        $this->tabActiva = $activeTab;
        $this->tiendas = Tienda::deRubro($rubro)->orderBy('nombre')->pluck('nombre', 'id')->toArray();

        // Restaurar filtros globales desde sesión (o usar defaults si es la primera visita). Un set por rubro.
        $filtros = session("dashboard_filtros_{$rubro}", []);
        $todasLasTiendas = array_keys($this->tiendas);
        $this->tiendasSeleccionadas = isset($filtros['tiendas'])
            ? array_values(array_intersect($filtros['tiendas'], $todasLasTiendas))
            : $todasLasTiendas;
        $this->generosSeleccionados = isset($filtros['generos'])
            ? array_values(array_intersect($filtros['generos'], $this->generosDisponibles))
            : $this->generosDisponibles;
        $this->descMin = $filtros['descMin'] ?? 0;
        $this->tablaFiltroBuscar = (string) ($filtros['buscar'] ?? '');
        $this->descMax = $filtros['descMax'] ?? 90;

        // Cargar todas las corridas existentes
        $corridas = Corrida::deRubro($this->rubro)->orderByDesc('fecha_hora')->get();
        $this->corridas = $corridas->map(fn($c) => [
            'id'    => $c->id,
            'label' => Carbon::parse($c->fecha_hora)->format('d/m/Y H:i'),
        ])->toArray();

        $this->corridaActualId   = $this->corridas[0]['id'] ?? 0;
        $this->corridaAnteriorId = $this->corridas[1]['id'] ?? null;

        if (count($this->corridas) >= 2) {
            $this->cambiosDesdeCorrida = $this->corridas[count($this->corridas) - 1]['id'];
            $this->cambiosHastaCorrida = $this->corridaActualId;
        }

        $this->cargarMarcasDisponibles();
        $this->tablaFiltroMarcas = array_values(array_intersect($filtros['marcas'] ?? [], $this->marcasDisponibles));
        $this->cargarMlDisponibles();
        $this->mlSeleccionados = $this->mlDisponibles;
        $this->cargarFavoritosLista();
        $this->cargarOpcionesRopa();

        // Carga lazy: solo el tab solicitado en la URL
        $this->cargarDatosParaTab($this->tabActiva);
    }

    // ── Acciones ─────────────────────────────────────────────────
    public function toggleTienda(int $id): void
    {
        $sel = $this->tiendasSeleccionadas;
        if (($pos = array_search($id, $sel)) !== false) {
            array_splice($sel, $pos, 1);
        } else {
            $sel[] = $id;
        }
        $this->tiendasSeleccionadas = array_values($sel);
        $this->guardarFiltrosEnSession();
        $this->recargarTodo();
    }

    public function toggleTodasTiendas(): void
    {
        $this->tiendasSeleccionadas = count($this->tiendasSeleccionadas) === count($this->tiendas)
            ? []
            : array_keys($this->tiendas);
        $this->guardarFiltrosEnSession();
        $this->recargarTodo();
    }

    public function toggleGenero(string $genero): void
    {
        $sel = $this->generosSeleccionados;
        if (($pos = array_search($genero, $sel)) !== false) {
            array_splice($sel, $pos, 1);
        } else {
            $sel[] = $genero;
        }
        $this->generosSeleccionados = array_values($sel);
        $this->guardarFiltrosEnSession();
        $this->recargarTodo();
    }

    public function toggleTodosGeneros(): void
    {
        $this->generosSeleccionados = count($this->generosSeleccionados) === count($this->generosDisponibles)
            ? []
            : $this->generosDisponibles;
        $this->guardarFiltrosEnSession();
        $this->recargarTodo();
    }

    public function toggleMl(int $ml): void
    {
        $sel = $this->mlSeleccionados;
        if (($pos = array_search($ml, $sel)) !== false) {
            array_splice($sel, $pos, 1);
        } else {
            $sel[] = $ml;
        }
        $this->mlSeleccionados = array_values($sel);
        $this->recargarTodo();
    }

    public function toggleTodosMl(): void
    {
        $this->mlSeleccionados = count($this->mlSeleccionados) === count($this->mlDisponibles)
            ? []
            : $this->mlDisponibles;
        $this->recargarTodo();
    }

    private function guardarFiltrosEnSession(): void
    {
        session(["dashboard_filtros_{$this->rubro}" => [
            'tiendas' => $this->tiendasSeleccionadas,
            'generos' => $this->generosSeleccionados,
            'descMin' => $this->descMin,
            'descMax' => $this->descMax,
            'buscar'  => $this->tablaFiltroBuscar,
            'marcas'  => $this->tablaFiltroMarcas,
        ]]);
    }

    private function recargarTodo(): void
    {
        $this->cargarOpcionesRopa();
        // Invalida todos los datos — se recargarán lazily al visitar cada tab
        $this->serieEvolucionJson = '[]';
        $this->resumenTiendasJson = '[]';
        $this->distribucionJson   = '[]';
        $this->tablaDatosJson     = '[]';
        $this->joyasJson          = '[]';
        $this->marcasJson         = '[]';
        $this->cambiosJson        = '{"nuevos":[],"salidos":[],"subas":[],"bajas":[]}';
        $this->compararJson       = '[]';
        $this->transferJson       = '[]';
        $this->conclusionesJson   = '{"puntos":[],"joyas":[],"narrativa":""}';
        $this->seguirOpciones     = [];
        $this->seguirJson         = '[]';

        // Solo recarga el tab visible
        $this->cargarDatosParaTab($this->tabActiva);
        $this->dispatch('charts-refresh');
    }

    public function updatedDescMin(): void { $this->guardarFiltrosEnSession(); $this->recargarTodo(); }
    public function updatedDescMax(): void { $this->guardarFiltrosEnSession(); $this->recargarTodo(); }
    public function updatedTablaFiltroBuscar(): void { $this->guardarFiltrosEnSession(); $this->recargarTodo(); }
    public function updatedAgruparColores(): void    { $this->cargarTabla(); }
    public function updatedPrecioMin(): void         { $this->recargarTodo(); }
    public function updatedPrecioMax(): void         { $this->recargarTodo(); }
    public function updatedTablaSortCol1(): void     { $this->cargarTabla(); }
    public function updatedTablaSortDir1(): void     { $this->cargarTabla(); }
    public function updatedTablaSortCol2(): void     { $this->cargarTabla(); }
    public function updatedTablaSortDir2(): void     { $this->cargarTabla(); }
    public function updatedTablaSortCol3(): void     { $this->cargarTabla(); }
    public function updatedTablaSortDir3(): void     { $this->cargarTabla(); }

    public function updatedJoyasUmbral(): void       { $this->cargarJoyas(); }
    public function updatedJoyasTiendaFiltro(): void { $this->cargarJoyas(); }
    public function updatedJoyasOcultarFreq(): void  { $this->cargarJoyas(); }

    public function updatedCambiosDesdeCorrida(): void { $this->cargarCambios(); }
    public function updatedCambiosHastaCorrida(): void { $this->cargarCambios(); }

    public function updatedSeguirProdKey(): void { $this->cargarSeguir(); }

    public function updatedTabActiva(): void
    {
        $this->cargarDatosParaTab($this->tabActiva);
        $this->dispatch('charts-refresh');
    }

    // ── Carga lazy por tab ───────────────────────────────────────
    private function cargarDatosParaTab(string $tab): void
    {
        match ($tab) {
            'joyas'        => $this->joyasJson === '[]'         ? $this->cargarJoyas()          : null,
            'tabla'        => $this->tablaDatosJson === '[]'    ? $this->cargarTabla()           : null,
            'cambios'      => $this->cargarCambios(),
            'seguir'       => empty($this->seguirOpciones)      ? $this->cargarSeguirOpciones()  : null,
            'comparar'     => $this->compararJson === '[]'      ? $this->cargarComparar()        : null,
            'tiendas'      => $this->resumenTiendasJson === '[]'? $this->cargarResumenTiendas()  : null,
            'distribucion' => $this->distribucionJson === '[]'  ? $this->cargarDistribucion()    : null,
            'evolucion'    => $this->serieEvolucionJson === '[]'? $this->cargarEvolucion()       : null,
            'transfer'     => $this->transferJson === '[]'      ? $this->cargarTransfer()        : null,
            'marcas'       => $this->marcasJson === '[]'        ? $this->cargarMarcas()          : null,
            'conclusiones' => $this->cargarConclusionesCompletas(),
            default        => $this->joyasJson === '[]' ? $this->cargarJoyas() : null,
        };
    }

    private function cargarConclusionesCompletas(): void
    {
        if ($this->serieEvolucionJson === '[]') $this->cargarEvolucion();
        if ($this->resumenTiendasJson === '[]') $this->cargarResumenTiendas();
        if ($this->joyasJson === '[]')          $this->cargarJoyas();
        if ($this->marcasJson === '[]')         $this->cargarMarcas();
        $this->cargarConclusiones();
    }

    // ── Carga principal ───────────────────────────────────────────
    private function cargarDatos(): void
    {
        $this->cargarEvolucion();
        $this->cargarResumenTiendas();
        $this->cargarDistribucion();
        $this->cargarTabla();
    }

    private function cargarEvolucion(): void
    {
        $tiendaIds = $this->tiendaIds();

        $rows = DB::table('observaciones as o')
            ->join('productos as p', 'p.id', '=', 'o.producto_id')
            ->join('corridas as c',  'c.id', '=', 'o.corrida_id')
            ->where('o.tipo_descuento', 'rebaja')
            ->tap(fn($q) => Observacion::filtrarFuentesValidas($q, 'o.fuente'))
            ->whereBetween('o.descuento_pct', [$this->descMin, $this->descMax])
            ->when(!empty($tiendaIds), fn($q) => $q->whereIn('p.tienda_id', $tiendaIds))
            ->when(count($this->generosSeleccionados) < count($this->generosDisponibles), fn($q) => $q->whereIn('p.genero', $this->generosSeleccionados))
            ->tap(fn($q) => $this->filtrosRopa($q))
            ->tap(fn($q) => $this->filtrosBusqueda($q))
            ->when(!empty(self::MARCAS_EXCLUIDAS), fn($q) => $q->where(fn($q2) => $q2->whereNull('p.marca')->orWhereNotIn(DB::raw('LOWER(p.marca)'), self::MARCAS_EXCLUIDAS)))
            ->groupBy('o.corrida_id', 'c.fecha_hora')
            ->orderBy('c.fecha_hora')
            ->selectRaw('c.fecha_hora, COUNT(*) AS total, AVG(o.descuento_pct) AS desc_prom, MAX(o.descuento_pct) AS desc_max, SUM(o.ahorro) AS ahorro_total, COUNT(DISTINCT p.tienda_id) AS tiendas_n')
            ->get();

        $this->serieEvolucionJson = $rows->map(fn($r) => [
            'fecha'         => Carbon::parse($r->fecha_hora)->format('d/m H:i'),
            'fecha_display' => Carbon::parse($r->fecha_hora)->format('d/m/Y'),
            'fecha_dia'     => Carbon::parse($r->fecha_hora)->format('d/m'),
            'fecha_ts'      => Carbon::parse($r->fecha_hora)->timestamp,
            'total'         => (int) $r->total,
            'desc_prom'     => round($r->desc_prom, 1),
            'desc_max'      => round($r->desc_max, 1),
            'ahorro_total'  => round($r->ahorro_total),
            'tiendas_n'     => (int) $r->tiendas_n,
        ])->toJson();
    }

    private function cargarResumenTiendas(): void
    {
        if (!$this->corridaActualId) return;
        $tiendaIds = $this->tiendaIds();

        $rows = DB::table('observaciones as o')
            ->join('productos as p', 'p.id', '=', 'o.producto_id')
            ->join('tiendas as t',   't.id', '=', 'p.tienda_id')
            ->where('o.corrida_id', $this->corridaActualId)
            ->where('o.tipo_descuento', 'rebaja')
            ->tap(fn($q) => Observacion::filtrarFuentesValidas($q, 'o.fuente'))
            ->whereBetween('o.descuento_pct', [$this->descMin, $this->descMax])
            ->when(!empty($tiendaIds), fn($q) => $q->whereIn('p.tienda_id', $tiendaIds))
            ->when(count($this->generosSeleccionados) < count($this->generosDisponibles), fn($q) => $q->whereIn('p.genero', $this->generosSeleccionados))
            ->tap(fn($q) => $this->filtrosRopa($q))
            ->tap(fn($q) => $this->filtrosBusqueda($q))
            ->when(!empty($this->mlDisponibles) && count($this->mlSeleccionados) < count($this->mlDisponibles),
                fn($q) => empty($this->mlSeleccionados) ? $q->whereRaw('1 = 0') : $q->whereIn('p.tamano_ml', $this->mlSeleccionados))
            ->groupBy('t.nombre')
            ->orderByDesc('total')
            ->selectRaw('t.nombre AS tienda, COUNT(*) AS total, AVG(o.descuento_pct) AS desc_prom, MAX(o.descuento_pct) AS desc_max, SUM(o.ahorro) AS ahorro_total')
            ->get();

        $this->resumenTiendasJson = $rows->map(fn($r) => [
            'tienda'      => $r->tienda,
            'total'       => (int) $r->total,
            'desc_prom'   => round($r->desc_prom, 1),
            'desc_max'    => round($r->desc_max, 1),
            'ahorro_total'=> round($r->ahorro_total),
        ])->toJson();
    }

    private function cargarDistribucion(): void
    {
        if (!$this->corridaActualId) return;
        $tiendaIds = $this->tiendaIds();

        $rows = DB::table('observaciones as o')
            ->join('productos as p', 'p.id', '=', 'o.producto_id')
            ->where('o.corrida_id', $this->corridaActualId)
            ->where('o.tipo_descuento', 'rebaja')
            ->tap(fn($q) => Observacion::filtrarFuentesValidas($q, 'o.fuente'))
            ->whereBetween('o.descuento_pct', [$this->descMin, $this->descMax])
            ->when(!empty($tiendaIds), fn($q) => $q->whereIn('p.tienda_id', $tiendaIds))
            ->when(count($this->generosSeleccionados) < count($this->generosDisponibles), fn($q) => $q->whereIn('p.genero', $this->generosSeleccionados))
            ->tap(fn($q) => $this->filtrosRopa($q))
            ->tap(fn($q) => $this->filtrosBusqueda($q))
            ->when(!empty($this->mlDisponibles) && count($this->mlSeleccionados) < count($this->mlDisponibles),
                fn($q) => empty($this->mlSeleccionados) ? $q->whereRaw('1 = 0') : $q->whereIn('p.tamano_ml', $this->mlSeleccionados))
            ->selectRaw('FLOOR(o.descuento_pct / 5) * 5 AS rango, COUNT(*) AS n')
            ->groupBy('rango')
            ->orderBy('rango')
            ->get();

        $this->distribucionJson = $rows->map(fn($r) => [
            'rango' => "{$r->rango}–" . ($r->rango + 5) . '%',
            'n'     => (int) $r->n,
        ])->toJson();
    }

    private const TABLA_POR_PAGINA = 100;

    private function cargarTabla(): void
    {
        if (!$this->corridaActualId) return;

        $this->tablaPagina = 1;
        $q = $this->buildTablaQuery();

        $this->tablaTotalCount = DB::query()->fromSub(clone $q, 'x')->count();

        $rows = (clone $q)
            ->limit(self::TABLA_POR_PAGINA)
            ->offset(0)
            ->get();

        $mapped = $rows->map(fn($r) => $this->mapTablaRow($r))->toArray();
        $this->tablaDatosJson = json_encode($mapped);
        $this->tablaTieneMas  = count($mapped) >= self::TABLA_POR_PAGINA;
    }

    public function cargarMasTabla(): void
    {
        if (!$this->tablaTieneMas || !$this->corridaActualId) return;

        $this->tablaPagina++;
        $offset = ($this->tablaPagina - 1) * self::TABLA_POR_PAGINA;

        $rows = $this->buildTablaQuery()
            ->limit(self::TABLA_POR_PAGINA)
            ->offset($offset)
            ->get();

        $mapped    = $rows->map(fn($r) => $this->mapTablaRow($r))->toArray();
        $existente = json_decode($this->tablaDatosJson, true) ?? [];
        $this->tablaDatosJson = json_encode(array_merge($existente, $mapped));
        $this->tablaTieneMas  = count($mapped) >= self::TABLA_POR_PAGINA;
    }

    private function buildTablaQuery(): \Illuminate\Database\Query\Builder
    {
        $tiendaIds = $this->tiendaIds();

        $sortable = [
            'descuento_pct' => 'o.descuento_pct',
            'precio_actual' => 'o.precio_actual',
            'precio_lista'  => 'o.precio_lista',
            'ahorro'        => 'o.ahorro',
            'marca'         => 'p.marca',
            'tienda'        => 't.nombre',
            'producto'      => 'p.nombre',
            'fecha'         => 'c.fecha_hora',
        ];

        $query = DB::table('observaciones as o')
            ->join('productos as p', 'p.id', '=', 'o.producto_id')
            ->join('tiendas as t',   't.id', '=', 'p.tienda_id')
            ->join('corridas as c',  'c.id', '=', 'o.corrida_id')
            ->where('o.tipo_descuento', 'rebaja')
            ->tap(fn($q) => Observacion::filtrarFuentesValidas($q, 'o.fuente'))
            ->whereBetween('o.descuento_pct', [$this->descMin, $this->descMax])
            ->whereIn('p.tienda_id', $tiendaIds)
            ->when(count($this->generosSeleccionados) < count($this->generosDisponibles), fn($q) => $q->whereIn('p.genero', $this->generosSeleccionados))
            ->tap(fn($q) => $this->filtrosRopa($q))
            ->tap(fn($q) => $this->filtrosBusqueda($q))
            ->when(!empty($this->mlDisponibles) && count($this->mlSeleccionados) < count($this->mlDisponibles),
                fn($q) => empty($this->mlSeleccionados) ? $q->whereRaw('1 = 0') : $q->whereIn('p.tamano_ml', $this->mlSeleccionados))
            ->when(!empty($this->tablaFiltroTiendas), fn($q) => $q->whereIn('t.nombre', $this->tablaFiltroTiendas))
            ->selectRaw('c.fecha_hora, t.nombre AS tienda, p.marca, p.nombre AS producto, o.precio_lista, o.precio_actual, o.descuento_pct, o.ahorro, o.fuente, p.url, p.talles, p.categoria, p.tipo, p.color, p.imagen, p.id AS producto_id, '
                . 'IF(p.referencia IS NULL OR p.referencia = "", CONCAT("id", p.id), SUBSTRING_INDEX(p.referencia, "-", 1)) AS modelo');

        $query->where('o.corrida_id', $this->corridaActualId);

        if ($this->agruparColores && config("rubros.rubros.{$this->rubro}.filtro_categoria")) {
            return $this->agruparTablaPorModelo($query);
        }

        $sortApplied = false;
        foreach ([
            [$this->tablaSortCol1, $this->tablaSortDir1],
            [$this->tablaSortCol2, $this->tablaSortDir2],
            [$this->tablaSortCol3, $this->tablaSortDir3],
        ] as [$col, $dir]) {
            if ($col && isset($sortable[$col])) {
                $query->orderBy($sortable[$col], $dir === 'asc' ? 'asc' : 'desc');
                $sortApplied = true;
            }
        }
        if (!$sortApplied) {
            $query->orderByDesc('o.descuento_pct');
        }

        return $query;
    }

    /**
     * Agrupa los colores de un mismo modelo (productos.referencia = MODELO-COLOR, ej. IH0745-010) cuando tienen el
     * mismo precio: un renglón por modelo y precio, con los colores y la unión de talles. Mantiene los 3 niveles de orden.
     */
    private function agruparTablaPorModelo(\Illuminate\Database\Query\Builder $base): \Illuminate\Database\Query\Builder
    {
        $orden = [
            'descuento_pct' => 'descuento_pct', 'precio_actual' => 'precio_actual', 'precio_lista' => 'precio_lista',
            'ahorro' => 'ahorro', 'marca' => 'marca', 'tienda' => 'tienda', 'producto' => 'producto', 'fecha' => 'fecha_hora',
        ];

        $q = DB::query()->fromSub($base->reorder(), 'b')
            ->groupBy('b.tienda', 'b.modelo', 'b.precio_lista', 'b.precio_actual')
            ->selectRaw('MIN(b.fecha_hora) AS fecha_hora, b.tienda, MIN(b.marca) AS marca, MIN(b.producto) AS producto, '
                . 'b.precio_lista, b.precio_actual, MAX(b.descuento_pct) AS descuento_pct, MAX(b.ahorro) AS ahorro, '
                . 'MIN(b.fuente) AS fuente, MIN(b.url) AS url, GROUP_CONCAT(b.talles SEPARATOR ", ") AS talles, '
                . 'MIN(b.categoria) AS categoria, MIN(b.tipo) AS tipo, GROUP_CONCAT(DISTINCT b.color SEPARATOR " | ") AS color, '
                . 'MIN(b.imagen) AS imagen, COUNT(*) AS variantes');

        $aplicado = false;
        foreach ([[$this->tablaSortCol1, $this->tablaSortDir1], [$this->tablaSortCol2, $this->tablaSortDir2], [$this->tablaSortCol3, $this->tablaSortDir3]] as [$col, $dir]) {
            if ($col && isset($orden[$col])) {
                $q->orderBy($orden[$col], $dir === 'asc' ? 'asc' : 'desc');
                $aplicado = true;
            }
        }
        if (!$aplicado) {
            $q->orderByDesc('descuento_pct');
        }
        return $q;
    }

    private function mapTablaRow(object $r): array
    {
        return [
            'fecha'        => Carbon::parse($r->fecha_hora)->format('d/m H:i'),
            'tienda'       => $r->tienda,
            'marca'        => $r->marca ?? '',
            'producto'     => $r->producto,
            'precio_lista' => '$' . number_format($r->precio_lista, 0, ',', '.'),
            'precio_actual'=> '$' . number_format($r->precio_actual, 0, ',', '.'),
            'descuento'    => round($r->descuento_pct, 1) . '%',
            'ahorro'       => '$' . number_format($r->ahorro, 0, ',', '.'),
            'fuente'       => $r->fuente ?? '',
            'url'          => $r->url ?? '',
            'talles'       => $this->unirTalles($r->talles ?? ''),
            'variantes'    => (int) ($r->variantes ?? 1),
            'categoria'    => $r->categoria ?? '',
            'tipo'         => $r->tipo ?? '',
            'color'        => $r->color ?? '',
            'imagen'       => $r->imagen ?? '',
        ];
    }

    // ── Joyas ─────────────────────────────────────────────────────
    private function cargarJoyas(): void
    {
        if (!$this->corridaActualId) return;
        $tiendaIds = $this->tiendaIds();

        $rows = DB::table('observaciones as o')
            ->join('productos as p', 'p.id', '=', 'o.producto_id')
            ->join('tiendas as t',   't.id', '=', 'p.tienda_id')
            ->where('o.corrida_id', $this->corridaActualId)
            ->where('o.tipo_descuento', 'rebaja')
            ->tap(fn($q) => Observacion::filtrarFuentesValidas($q, 'o.fuente'))
            ->where('o.descuento_pct', '>=', $this->descMin)
            ->where('o.descuento_pct', '<=', $this->descMax)
            ->when(!empty($tiendaIds), fn($q) => $q->whereIn('p.tienda_id', $tiendaIds))
            ->when(count($this->generosSeleccionados) < count($this->generosDisponibles), fn($q) => $q->whereIn('p.genero', $this->generosSeleccionados))
            ->tap(fn($q) => $this->filtrosRopa($q))
            ->tap(fn($q) => $this->filtrosBusqueda($q))
            ->when(!empty($this->mlDisponibles) && count($this->mlSeleccionados) < count($this->mlDisponibles),
                fn($q) => empty($this->mlSeleccionados) ? $q->whereRaw('1 = 0') : $q->whereIn('p.tamano_ml', $this->mlSeleccionados))
            ->when($this->joyasTiendaFiltro, fn($q) => $q->where('t.nombre', $this->joyasTiendaFiltro))
            ->selectRaw('t.nombre AS tienda, p.marca, p.nombre AS producto, o.precio_actual, o.descuento_pct, p.url')
            ->orderByDesc('o.descuento_pct')
            ->get()
            ->toArray();

        $matched = $this->matchJoyas($rows, $this->joyasUmbral);

        // Frecuencia y antigüedad dentro de la ventana visible (ayer → hoy)
        $totalCorridas = DB::table('corridas')->where('rubro', $this->rubro)->count();
        if ($totalCorridas > 0 && !empty($matched)) {
            $prodNombres = array_unique(array_map(fn($j) => mb_strtolower($j['producto']), $matched));

            $freqData = DB::table('observaciones as o')
                ->join('productos as p', 'p.id', '=', 'o.producto_id')
                ->whereIn(DB::raw('LOWER(p.nombre)'), $prodNombres)
                ->where('o.tipo_descuento', 'rebaja')
                ->tap(fn($q) => Observacion::filtrarFuentesValidas($q, 'o.fuente'))
                ->when(!empty(self::MARCAS_EXCLUIDAS), fn($q) => $q->where(fn($q2) => $q2->whereNull('p.marca')->orWhereNotIn(DB::raw('LOWER(p.marca)'), self::MARCAS_EXCLUIDAS)))
                ->selectRaw('LOWER(p.nombre) AS pnorm, COUNT(DISTINCT o.corrida_id) AS cnt')
                ->groupBy('pnorm')
                ->pluck('cnt', 'pnorm')
                ->toArray();

            $primerFechaData = DB::table('observaciones as o')
                ->join('productos as p', 'p.id', '=', 'o.producto_id')
                ->join('corridas as c', 'c.id', '=', 'o.corrida_id')
                ->whereIn(DB::raw('LOWER(p.nombre)'), $prodNombres)
                ->where('o.tipo_descuento', 'rebaja')
                ->tap(fn($q) => Observacion::filtrarFuentesValidas($q, 'o.fuente'))
                ->when(!empty(self::MARCAS_EXCLUIDAS), fn($q) => $q->where(fn($q2) => $q2->whereNull('p.marca')->orWhereNotIn(DB::raw('LOWER(p.marca)'), self::MARCAS_EXCLUIDAS)))
                ->selectRaw('LOWER(p.nombre) AS pnorm, MIN(c.fecha_hora) AS primera_fecha')
                ->groupBy('pnorm')
                ->pluck('primera_fecha', 'pnorm')
                ->toArray();

            foreach ($matched as &$item) {
                $pnorm = mb_strtolower($item['producto']);
                $cnt = $freqData[$pnorm] ?? 0;
                $item['freq_pct'] = (int) round($cnt / $totalCorridas * 100);
                $primeraFecha = $primerFechaData[$pnorm] ?? null;
                $item['dias_en_oferta'] = $primeraFecha
                    ? (int) Carbon::parse($primeraFecha)->diffInDays(Carbon::now())
                    : 0;
            }
            unset($item);

            if ($this->joyasOcultarFreq && $totalCorridas >= 3) {
                $matched = array_filter($matched, fn($j) => ($j['freq_pct'] ?? 0) < 85);
            }
        }

        $this->joyasJson = json_encode(array_values($matched));
    }

    private function cargarConclusiones(): void
    {
        $serie   = json_decode($this->serieEvolucionJson, true) ?? [];
        $resumen = json_decode($this->resumenTiendasJson, true) ?? [];
        $joyas   = array_slice(json_decode($this->joyasJson, true) ?? [], 0, 6);
        $puntos  = [];

        $n = count($serie);
        if ($n > 0) {
            $first = $serie[0];
            $last  = $serie[$n - 1];

            $puntos[] = ['emoji' => '🗓️', 'texto' =>
                "El análisis cubre **{$n} corrida(s)**. Último snapshot: **{$last['fecha']}**."];

            if ($n > 1) {
                $diff  = $last['total'] - $first['total'];
                $pct   = $first['total'] > 0 ? (int)round($diff / $first['total'] * 100) : 0;
                $verb  = $diff > 0 ? 'creció' : ($diff < 0 ? 'se redujo' : 'se mantuvo');
                $sign  = $diff >= 0 ? '+' : '';
                $puntos[] = ['emoji' => '📦', 'texto' =>
                    "Los perfumes en oferta {$verb} de **{$first['total']}** a **{$last['total']}** ({$sign}{$pct}%)."];

                $peak = collect($serie)->sortByDesc('total')->first();
                $puntos[] = ['emoji' => '📈', 'texto' =>
                    "Pico en **{$peak['fecha']}** con **{$peak['total']}** perfumes."];

                $d1    = $first['desc_prom'];
                $d2    = $last['desc_prom'];
                $verb2 = $d2 > $d1 ? 'subieron' : ($d2 < $d1 ? 'bajaron' : 'se mantuvieron');
                $puntos[] = ['emoji' => '🏷️', 'texto' =>
                    "Los descuentos promedio {$verb2} de **{$d1}%** a **{$d2}%**."];

                $topA = collect($serie)->sortByDesc('ahorro_total')->first();
                $fmt  = '$' . number_format($topA['ahorro_total'], 0, ',', '.');
                $puntos[] = ['emoji' => '💰', 'texto' =>
                    "El mayor ahorro total potencial fue **{$fmt}** (corrida {$topA['fecha']})."];
            } else {
                $puntos[] = ['emoji' => '📦', 'texto' =>
                    "Hay **{$last['total']}** perfumes en oferta · descuento promedio **{$last['desc_prom']}%**."];
            }
        }

        if (!empty($resumen)) {
            $topQty  = $resumen[0];
            $topDesc = collect($resumen)->sortByDesc('desc_prom')->first();
            $puntos[] = ['emoji' => '🏪', 'texto' =>
                "**{$topQty['tienda']}** lidera en cantidad ({$topQty['total']} productos). "
                . "**{$topDesc['tienda']}** tiene el mayor descuento promedio ({$topDesc['desc_prom']}%)."];
        }

        if (!empty($joyas)) {
            $topJoya = $joyas[0];
            $puntos[] = ['emoji' => '💎', 'texto' =>
                "Mejor joya hoy: **{$topJoya['linea']}** ({$topJoya['marca']}) con **{$topJoya['descuento_pct']}%** en {$topJoya['tienda']}."];
        }

        $narrativa = $this->generarNarrativa();
        $this->conclusionesJson = json_encode(compact('puntos', 'joyas', 'narrativa'));
    }

    private function generarNarrativa(): string
    {
        $serie = json_decode($this->serieEvolucionJson, true) ?? [];
        if (empty($serie)) {
            return 'Todavía no hay datos suficientes para una lectura general.';
        }

        $n    = count($serie);
        $prim = $serie[0];
        $ult  = $serie[$n - 1];

        if ($n === 1) {
            return "Por ahora hay un solo snapshot, del {$ult['fecha_display']}, con **{$ult['total']}** perfumes en oferta y un descuento promedio de **{$ult['desc_prom']}%**. En cuanto sumes más corridas, esta sección va a leer la evolución en el tiempo.";
        }

        $dif   = $ult['total'] - $prim['total'];
        $pct   = $prim['total'] > 0 ? (int)round($dif / $prim['total'] * 100) : 0;
        $sign  = $pct >= 0 ? '+' : '';
        $col   = collect($serie);
        $pico  = $col->sortByDesc('total')->first();
        $valle = $col->sortBy('total')->first();
        $verbo = $dif > 0 ? 'creció' : ($dif < 0 ? 'se redujo' : 'se mantuvo');

        $p1 = "Entre el {$prim['fecha_display']} y el {$ult['fecha_display']} la oferta de perfumes **{$verbo}** de {$prim['total']} a {$ult['total']} productos ({$sign}{$pct}%). El momento de mayor actividad fue el **{$pico['fecha_dia']}**, con {$pico['total']} perfumes en promoción, mientras que el piso se dio el {$valle['fecha_dia']} con {$valle['total']}. ";

        // Correlación con fechas comerciales
        foreach ($this->eventosComerciales() as [$evTs, $evNom, $evFmt]) {
            $diff = (int) round(abs(($pico['fecha_ts'] ?? 0) - $evTs) / 86400);
            if ($diff <= 5) {
                $cuando = $diff === 0 ? 'justo en' : "a {$diff} día(s) de";
                $p1 .= "Ese pico cae {$cuando} **{$evNom}** ({$evFmt}), una señal clara de que las perfumerías refuerzan las promos alrededor de las fechas comerciales y las aflojan después.";
                break;
            }
        }

        $dtend   = $ult['desc_prom'] - $prim['desc_prom'];
        $sentido = $dtend > 0.1 ? 'se profundizaron' : ($dtend < -0.1 ? 'se moderaron' : 'se mantuvieron estables');

        $resumen = json_decode($this->resumenTiendasJson, true) ?? [];
        $marcas  = json_decode($this->marcasJson, true) ?? [];

        $maxRow = DB::table('observaciones as o')
            ->join('productos as p', 'p.id', '=', 'o.producto_id')
            ->join('tiendas as t',   't.id', '=', 'p.tienda_id')
            ->where('o.corrida_id', $this->corridaActualId)
            ->where('o.tipo_descuento', 'rebaja')
            ->tap(fn($q) => Observacion::filtrarFuentesValidas($q, 'o.fuente'))
            ->when(!empty($this->tiendaIds()), fn($q) => $q->whereIn('p.tienda_id', $this->tiendaIds()))
            ->when(count($this->generosSeleccionados) < count($this->generosDisponibles), fn($q) => $q->whereIn('p.genero', $this->generosSeleccionados))
            ->tap(fn($q) => $this->filtrosRopa($q))
            ->tap(fn($q) => $this->filtrosBusqueda($q))
            ->when(!empty($this->mlDisponibles) && count($this->mlSeleccionados) < count($this->mlDisponibles),
                fn($q) => empty($this->mlSeleccionados) ? $q->whereRaw('1 = 0') : $q->whereIn('p.tamano_ml', $this->mlSeleccionados))
            ->selectRaw('p.nombre AS producto, t.nombre AS tienda, o.descuento_pct')
            ->orderByDesc('o.descuento_pct')
            ->first();

        $p2 = "En cuanto a la intensidad, los descuentos {$sentido}: el promedio pasó de **{$prim['desc_prom']}%** a **{$ult['desc_prom']}%**. ";

        if ($maxRow) {
            $p2 .= "El descuento más agresivo de todo el período fue **{$maxRow->descuento_pct}%** en {$maxRow->producto} ({$maxRow->tienda}). ";
        }

        if (!empty($resumen)) {
            $colRes  = collect($resumen);
            $topVol  = $colRes->sortByDesc('total')->first();
            $topDesc = $colRes->sortByDesc('desc_prom')->first();
            $p2 .= "A nivel tiendas, **{$topVol['tienda']}** es la que más volumen de ofertas mueve, pero **{$topDesc['tienda']}** es la que descuenta más fuerte en promedio ({$topDesc['desc_prom']}%). ";
        }

        $colM = collect($marcas)->filter(fn($m) => ($m['total'] ?? 0) >= 3);
        if ($colM->isNotEmpty()) {
            $topM = $colM->sortByDesc('desc_prom')->first();
            $p2 .= "Entre las marcas, conviene tener en el radar a **{$topM['marca']}**, que lidera en descuento promedio ({$topM['desc_prom']}%).";
        }

        return $p1 . "\n\n" . $p2;
    }

    private function eventosComerciales(): array
    {
        $eventos = [];
        foreach ([(int)date('Y') - 1, (int)date('Y'), (int)date('Y') + 1] as $y) {
            $padres = $this->primerDiaSemana(Carbon::create($y, 6,  1), 0, 3);
            $madres = $this->primerDiaSemana(Carbon::create($y, 10, 1), 0, 3);
            $bf     = $this->primerDiaSemana(Carbon::create($y, 11, 1), 5, 4);
            array_push($eventos,
                [Carbon::create($y, 2, 14)->timestamp, 'San Valentín',   '14/02'],
                [$padres->timestamp,                   'Día del Padre',  $padres->format('d/m')],
                [Carbon::create($y, 7, 20)->timestamp, 'Día del Amigo',  '20/07'],
                [$madres->timestamp,                   'Día de la Madre', $madres->format('d/m')],
                [$bf->timestamp,                       'Black Friday',   $bf->format('d/m')],
                [Carbon::create($y, 12, 25)->timestamp,'Navidad',        '25/12'],
            );
        }
        return $eventos;
    }

    private function primerDiaSemana(Carbon $start, int $dow, int $n): Carbon
    {
        $d = $start->copy();
        while ($d->dayOfWeek !== $dow) $d->addDay();
        return $d->addWeeks($n - 1);
    }

    // Subquery: para cada producto, el corrida_id más reciente en toda la base de datos.
    private function latestEnVentana(string $tipo = 'rebaja'): \Illuminate\Database\Query\Builder
    {
        $q = DB::table('observaciones as o')
            ->join('productos as p', 'p.id', '=', 'o.producto_id')
            ->select('o.producto_id', DB::raw('MAX(o.corrida_id) as max_cid'))
            ->where('o.tipo_descuento', $tipo)
            ->groupBy('o.producto_id');
        if ($tipo === 'rebaja') {
            $q->tap(fn($q) => Observacion::filtrarFuentesValidas($q, 'o.fuente'));
        }
        if (!empty(self::MARCAS_EXCLUIDAS)) {
            $q->where(fn($q2) => $q2->whereNull('p.marca')
                                    ->orWhereNotIn(DB::raw('LOWER(p.marca)'), self::MARCAS_EXCLUIDAS));
        }
        return $q;
    }

    private function matchJoyas(array $rows, float $umbral): array
    {
        $resultado = [];
        $seen = [];

        foreach (self::CATALOGO as $marca => $cfg) {
            $tokens = array_map('mb_strtolower', $cfg['tokens']);

            $brandRows = array_filter($rows, function ($r) use ($tokens, $marca) {
                $marcaNorm = mb_strtolower($r->marca ?? '');
                $prodNorm  = mb_strtolower($r->producto ?? '');
                if (mb_strtolower($marca) === $marcaNorm) return true;
                foreach ($tokens as $t) {
                    if (str_contains($prodNorm, $t) || str_contains($marcaNorm, $t)) return true;
                }
                return false;
            });

            foreach ($cfg['lineas'] as $linea => $kws) {
                $kwNorm = array_map('mb_strtolower', $kws);
                foreach ($brandRows as $r) {
                    if ($r->descuento_pct < $umbral) continue;
                    $key = mb_strtolower($r->producto . '|' . $r->tienda);
                    if (isset($seen[$key])) continue;
                    $prodNorm = mb_strtolower($r->producto ?? '');
                    foreach ($kwNorm as $kw) {
                        if (str_contains($prodNorm, $kw)) {
                            $seen[$key] = true;
                            $resultado[] = [
                                'marca'        => $marca,
                                'linea'        => $linea,
                                'producto'     => $r->producto,
                                'tienda'       => $r->tienda,
                                'precio_actual'=> round($r->precio_actual),
                                'descuento_pct'=> round($r->descuento_pct, 1),
                                'url'          => $r->url ?? '',
                            ];
                            break;
                        }
                    }
                }
            }
        }

        $brandPriority = [
            'Versace'            => 1,
            'Yves Saint Laurent' => 2,
            'Armani'             => 3,
            'Dior'               => 4,
            'Chanel'             => 5,
            'Dolce & Gabbana'    => 6,
            'Hugo Boss'          => 7,
            'Rabanne'            => 8,
            'Jean Paul Gaultier' => 9,
            'Givenchy'           => 10,
            'Montblanc'          => 11,
            'Carolina Herrera'   => 12,
        ];

        usort($resultado, function ($a, $b) use ($brandPriority) {
            $pa = $brandPriority[$a['marca']] ?? 99;
            $pb = $brandPriority[$b['marca']] ?? 99;
            if ($pa !== $pb) return $pa <=> $pb;
            return $b['descuento_pct'] <=> $a['descuento_pct'];
        });

        return $resultado;
    }

    // ── Marcas ────────────────────────────────────────────────────
    private function cargarMarcas(): void
    {
        if (!$this->corridaActualId) return;
        $tiendaIds = $this->tiendaIds();

        $rows = DB::table('observaciones as o')
            ->join('productos as p', 'p.id', '=', 'o.producto_id')
            ->where('o.corrida_id', $this->corridaActualId)
            ->where('o.tipo_descuento', 'rebaja')
            ->tap(fn($q) => Observacion::filtrarFuentesValidas($q, 'o.fuente'))
            ->whereBetween('o.descuento_pct', [$this->descMin, $this->descMax])
            ->where('p.marca', '!=', '')
            ->whereNotNull('p.marca')
            ->when(!empty($tiendaIds), fn($q) => $q->whereIn('p.tienda_id', $tiendaIds))
            ->when(count($this->generosSeleccionados) < count($this->generosDisponibles), fn($q) => $q->whereIn('p.genero', $this->generosSeleccionados))
            ->tap(fn($q) => $this->filtrosRopa($q))
            ->tap(fn($q) => $this->filtrosBusqueda($q))
            ->when(!empty($this->mlDisponibles) && count($this->mlSeleccionados) < count($this->mlDisponibles),
                fn($q) => empty($this->mlSeleccionados) ? $q->whereRaw('1 = 0') : $q->whereIn('p.tamano_ml', $this->mlSeleccionados))
            ->groupBy('p.marca')
            ->orderByDesc('total')
            ->selectRaw('p.marca, COUNT(*) AS total, AVG(o.descuento_pct) AS desc_prom, MAX(o.descuento_pct) AS desc_max')
            ->limit(40)
            ->get();

        $this->marcasJson = $rows->map(fn($r) => [
            'marca'    => $r->marca,
            'total'    => (int) $r->total,
            'desc_prom'=> round($r->desc_prom, 1),
            'desc_max' => round($r->desc_max, 1),
        ])->toJson();
    }

    // ── Cambios ───────────────────────────────────────────────────
    private function cargarCambios(): void
    {
        if (!$this->cambiosDesdeCorrida || !$this->cambiosHastaCorrida) return;
        $tiendaIds = $this->tiendaIds();

        $pk = $this->claveProducto();
        $snap = function (int $cid) use ($tiendaIds, $pk) {
            return DB::table('observaciones as o')
                ->join('productos as p', 'p.id', '=', 'o.producto_id')
                ->join('tiendas as t',   't.id', '=', 'p.tienda_id')
                ->where('o.corrida_id', $cid)
                ->where('o.tipo_descuento', 'rebaja')
                ->tap(fn($q) => Observacion::filtrarFuentesValidas($q, 'o.fuente'))
                ->when(!empty($tiendaIds), fn($q) => $q->whereIn('p.tienda_id', $tiendaIds))
                ->when(count($this->generosSeleccionados) < count($this->generosDisponibles), fn($q) => $q->whereIn('p.genero', $this->generosSeleccionados))
            ->tap(fn($q) => $this->filtrosRopa($q))
            ->tap(fn($q) => $this->filtrosBusqueda($q))
                ->when(!empty(self::MARCAS_EXCLUIDAS), fn($q) => $q->where(fn($q2) => $q2->whereNull('p.marca')->orWhereNotIn(DB::raw('LOWER(p.marca)'), self::MARCAS_EXCLUIDAS)))
                ->selectRaw($pk . ' AS pk, t.nombre AS tienda, p.marca, p.nombre AS producto, p.color, o.descuento_pct, o.precio_actual, p.url')
                ->orderByDesc('o.descuento_pct')
                ->get()
                ->keyBy('pk');
        };

        $desde = $snap($this->cambiosDesdeCorrida);
        $hasta  = $snap($this->cambiosHastaCorrida);

        $mapRow = fn($r) => [
            'producto'    => $this->nombreConColor($r),
            'tienda'      => $r->tienda,
            'marca'       => $r->marca ?? '',
            'descuento_pct' => round($r->descuento_pct, 1),
            'precio_actual' => round($r->precio_actual),
        ];

        $nuevosAll  = $hasta->filter(fn($r, $k) => !$desde->has($k));
        $salidosAll = $desde->filter(fn($r, $k) => !$hasta->has($k));
        $totalNuevos  = $nuevosAll->count();
        $totalSalidos = $salidosAll->count();

        $nuevos  = $nuevosAll->values()->take(60)->map($mapRow)->values();
        $salidos = $salidosAll->values()->take(60)->map($mapRow)->values();

        $cambios = $hasta->filter(fn($r, $k) => $desde->has($k))->map(fn($r, $k) => [
            'producto'   => $this->nombreConColor($r),
            'tienda'     => $r->tienda,
            'marca'      => $r->marca ?? '',
            'desc_antes' => round($desde[$k]->descuento_pct, 1),
            'desc_ahora' => round($r->descuento_pct, 1),
            'delta'      => round($r->descuento_pct - $desde[$k]->descuento_pct, 1),
        ])->values();

        $subas = $cambios->filter(fn($r) => $r['delta'] > 0)->sortByDesc('delta')->take(15)->values();
        $bajas = $cambios->filter(fn($r) => $r['delta'] < 0)->sortBy('delta')->take(15)->values();

        $this->cambiosJson = json_encode(compact('nuevos', 'salidos', 'subas', 'bajas', 'totalNuevos', 'totalSalidos'));
    }

    // ── Seguir un perfume ─────────────────────────────────────────
    /**
     * Identidad de un producto entre corridas (SQL). Perfumes: tienda||nombre (el scraper los identifica por nombre).
     * Otros rubros (ropa): productos.id, porque un mismo modelo existe en varios colores con el mismo nombre.
     */
    private function claveProducto(): string
    {
        return $this->rubro === config('rubros.default')
            ? 'CONCAT(LOWER(t.nombre),"||",LOWER(p.nombre))'
            : 'CAST(p.id AS CHAR)';
    }

    private function etiquetaProducto(): string
    {
        return $this->rubro === config('rubros.default')
            ? 'CONCAT(p.nombre," (",t.nombre,")")'
            : 'CONCAT(p.nombre, IF(p.color IS NULL OR p.color = "", "", CONCAT(" · ", p.color)), " (", t.nombre, ")")';
    }

    private function nombreConColor(object $r): string
    {
        $c = trim((string) ($r->color ?? ''));
        return $this->rubro !== config('rubros.default') && $c !== '' ? $r->producto . ' · ' . $c : $r->producto;
    }

    private function cargarSeguirOpciones(): void
    {
        $tiendaIds = $this->tiendaIds();

        $rows = DB::table('productos as p')
            ->join('tiendas as t', 't.id', '=', 'p.tienda_id')
            ->join('observaciones as o', 'o.producto_id', '=', 'p.id')
            ->where('o.tipo_descuento', 'rebaja')
            ->tap(fn($q) => Observacion::filtrarFuentesValidas($q, 'o.fuente'))
            ->when(!empty($tiendaIds), fn($q) => $q->whereIn('p.tienda_id', $tiendaIds))
            ->when(count($this->generosSeleccionados) < count($this->generosDisponibles), fn($q) => $q->whereIn('p.genero', $this->generosSeleccionados))
            ->tap(fn($q) => $this->filtrosRopa($q))
            ->tap(fn($q) => $this->filtrosBusqueda($q))
            ->when(!empty(self::MARCAS_EXCLUIDAS), fn($q) => $q->where(fn($q2) => $q2->whereNull('p.marca')->orWhereNotIn(DB::raw('LOWER(p.marca)'), self::MARCAS_EXCLUIDAS)))
            ->selectRaw($this->claveProducto() . ' AS pk, ' . $this->etiquetaProducto() . ' AS etiqueta')
            ->groupBy('pk', 'etiqueta')
            ->orderBy('p.nombre')
            ->get();

        $this->seguirOpciones = $rows->map(fn($r) => ['key' => $r->pk, 'label' => $r->etiqueta])->toArray();

        if (!$this->seguirProdKey && !empty($this->seguirOpciones)) {
            $this->seguirProdKey = $this->seguirOpciones[0]['key'];
        }
        $this->cargarSeguir();
    }

    private function cargarSeguir(): void
    {
        if (!$this->seguirProdKey) return;
        [$tiendaN, $prodN] = array_pad(explode('||', $this->seguirProdKey, 2), 2, '');
        $porId = $this->rubro !== config('rubros.default');   // ropa: la clave es productos.id

        $rows = DB::table('observaciones as o')
            ->join('productos as p', 'p.id', '=', 'o.producto_id')
            ->join('tiendas as t',   't.id', '=', 'p.tienda_id')
            ->join('corridas as c',  'c.id', '=', 'o.corrida_id')
            ->where('o.tipo_descuento', 'rebaja')
            ->tap(fn($q) => Observacion::filtrarFuentesValidas($q, 'o.fuente'))
            ->when(!empty(self::MARCAS_EXCLUIDAS), fn($q) => $q->where(fn($q2) => $q2->whereNull('p.marca')->orWhereNotIn(DB::raw('LOWER(p.marca)'), self::MARCAS_EXCLUIDAS)))
            ->when($porId,
                fn($q) => $q->where('p.id', (int) $this->seguirProdKey),
                fn($q) => $q->whereRaw('LOWER(t.nombre) = ?', [$tiendaN])->whereRaw('LOWER(p.nombre) = ?', [$prodN]))
            ->orderBy('c.fecha_hora')
            ->selectRaw('c.fecha_hora, o.precio_lista, o.precio_actual, o.descuento_pct, p.url')
            ->get();

        $this->seguirJson = $rows->map(fn($r) => [
            'fecha'        => Carbon::parse($r->fecha_hora)->format('d/m H:i'),
            'precio_lista' => round($r->precio_lista),
            'precio_actual'=> round($r->precio_actual),
            'descuento_pct'=> round($r->descuento_pct, 1),
            'url'          => $r->url ?? '',
        ])->toJson();
        $this->dispatch('seguir-refresh');
    }

    // ── Comparar precios ──────────────────────────────────────────
    private function cargarComparar(): void
    {
        if (!$this->corridaActualId) return;
        $tiendaIds = $this->tiendaIds();

        $rows = DB::table('observaciones as o')
            ->join('productos as p', 'p.id', '=', 'o.producto_id')
            ->join('tiendas as t',   't.id', '=', 'p.tienda_id')
            ->where('o.corrida_id', $this->corridaActualId)
            ->where('o.tipo_descuento', 'rebaja')
            ->tap(fn($q) => Observacion::filtrarFuentesValidas($q, 'o.fuente'))
            ->when(!empty($tiendaIds), fn($q) => $q->whereIn('p.tienda_id', $tiendaIds))
            ->when(count($this->generosSeleccionados) < count($this->generosDisponibles), fn($q) => $q->whereIn('p.genero', $this->generosSeleccionados))
            ->tap(fn($q) => $this->filtrosRopa($q))
            ->tap(fn($q) => $this->filtrosBusqueda($q))
            ->when(!empty($this->mlDisponibles) && count($this->mlSeleccionados) < count($this->mlDisponibles),
                fn($q) => empty($this->mlSeleccionados) ? $q->whereRaw('1 = 0') : $q->whereIn('p.tamano_ml', $this->mlSeleccionados))
            ->selectRaw('t.nombre AS tienda, p.marca, LOWER(p.nombre) AS prod_norm, p.nombre AS producto, o.precio_actual, o.descuento_pct, p.url')
            ->orderBy('o.precio_actual')
            ->get();

        $resultado = [];
        foreach ($rows->groupBy('prod_norm') as $norm => $group) {
            $tiendas = $group->pluck('tienda')->unique();
            if ($tiendas->count() < 2) continue;
            $min = $group->sortBy('precio_actual')->first();
            $max = $group->max('precio_actual');
            $ahorro = $max - $min->precio_actual;
            if ($ahorro <= 0) continue;
            $resultado[] = [
                'producto'   => $min->producto,
                'marca'      => $min->marca ?? '',
                'tiendas'    => $tiendas->count(),
                'precio_min' => round($min->precio_actual),
                'tienda_min' => $min->tienda,
                'precio_max' => round($max),
                'ahorro'     => round($ahorro),
                'spread_pct' => round($ahorro / $max * 100, 1),
                'url'        => $min->url ?? '',
            ];
        }

        usort($resultado, fn($a, $b) => $b['ahorro'] <=> $a['ahorro']);
        $this->compararJson = json_encode(array_slice($resultado, 0, 60));
    }

    // ── Transferencia ─────────────────────────────────────────────
    private function cargarTransfer(): void
    {
        if (!$this->corridaActualId) return;
        $tiendaIds = $this->tiendaIds();

        try {
            $rows = DB::table('observaciones as o')
                ->join('productos as p', 'p.id', '=', 'o.producto_id')
                ->join('tiendas as t',   't.id', '=', 'p.tienda_id')
                ->where('o.corrida_id', $this->corridaActualId)
                ->where('o.tipo_descuento', 'transferencia')
                ->whereNotNull('o.precio_transferencia')
                ->whereRaw('o.precio_transferencia < o.precio_actual')
                ->when(!empty($tiendaIds), fn($q) => $q->whereIn('p.tienda_id', $tiendaIds))
                ->when(count($this->generosSeleccionados) < count($this->generosDisponibles), fn($q) => $q->whereIn('p.genero', $this->generosSeleccionados))
            ->tap(fn($q) => $this->filtrosRopa($q))
            ->tap(fn($q) => $this->filtrosBusqueda($q))
                ->when(!empty($this->mlDisponibles) && count($this->mlSeleccionados) < count($this->mlDisponibles),
                    fn($q) => empty($this->mlSeleccionados) ? $q->whereRaw('1 = 0') : $q->whereIn('p.tamano_ml', $this->mlSeleccionados))
                ->selectRaw('t.nombre AS tienda, p.marca, p.nombre AS producto, o.precio_actual, o.precio_transferencia, p.url')
                ->orderByRaw('(o.precio_actual - o.precio_transferencia) / o.precio_actual DESC')
                ->limit(200)
                ->get();

            $this->transferJson = $rows->map(fn($r) => [
                'tienda'       => $r->tienda,
                'marca'        => $r->marca ?? '',
                'producto'     => $r->producto,
                'precio_normal'=> '$' . number_format($r->precio_actual, 0, ',', '.'),
                'precio_transf'=> '$' . number_format($r->precio_transferencia, 0, ',', '.'),
                'ahorro_pct'   => round(($r->precio_actual - $r->precio_transferencia) / $r->precio_actual * 100, 1),
                'url'          => $r->url ?? '',
            ])->toJson();
        } catch (\Throwable) {
            $this->transferJson = '[]';
        }
    }

    // ── Acciones de tabla ─────────────────────────────────────────
    public function toggleTablaFiltroMarca(string $marca): void
    {
        $idx = array_search($marca, $this->tablaFiltroMarcas);
        if ($idx !== false) {
            array_splice($this->tablaFiltroMarcas, $idx, 1);
        } else {
            $this->tablaFiltroMarcas[] = $marca;
        }
        $this->tablaFiltroMarcas = array_values($this->tablaFiltroMarcas);
        $this->guardarFiltrosEnSession();
        $this->recargarTodo();
    }

    /** Limpia solo la búsqueda (texto y marcas); conserva orden y demás filtros. */
    public function limpiarBusqueda(): void
    {
        $this->tablaFiltroBuscar = '';
        $this->tablaFiltroMarcas = [];
        $this->guardarFiltrosEnSession();
        $this->recargarTodo();
    }

    public function toggleTablaFiltroTiendaTabla(string $nombre): void
    {
        $idx = array_search($nombre, $this->tablaFiltroTiendas);
        if ($idx !== false) {
            array_splice($this->tablaFiltroTiendas, $idx, 1);
        } else {
            $this->tablaFiltroTiendas[] = $nombre;
        }
        $this->tablaFiltroTiendas = array_values($this->tablaFiltroTiendas);
        $this->cargarTabla();
    }

    // ── Filtros de ropa: categoría, tipo de producto y talles ──────
    public function setCategoria(string $categoria): void
    {
        $this->categoriaFiltro = in_array($categoria, ['ropa', 'calzado', 'accesorios'], true) ? $categoria : '';
        $this->recargarTodo();
    }

    public function toggleTipo(string $tipo): void
    {
        $i = array_search($tipo, $this->tiposExcluidos, true);
        $i === false ? $this->tiposExcluidos[] = $tipo : array_splice($this->tiposExcluidos, $i, 1);
        $this->tiposExcluidos = array_values($this->tiposExcluidos);
        $this->recargarTodo();
    }

    public function toggleDisciplina(string $disciplina): void
    {
        $i = array_search($disciplina, $this->disciplinasExcluidas, true);
        $i === false ? $this->disciplinasExcluidas[] = $disciplina : array_splice($this->disciplinasExcluidas, $i, 1);
        $this->disciplinasExcluidas = array_values($this->disciplinasExcluidas);
        $this->recargarTodo();
    }

    public function toggleLinea(string $linea): void
    {
        $i = array_search($linea, $this->lineasExcluidas, true);
        $i === false ? $this->lineasExcluidas[] = $linea : array_splice($this->lineasExcluidas, $i, 1);
        $this->lineasExcluidas = array_values($this->lineasExcluidas);
        $this->recargarTodo();
    }

    /** "✓ Todos / × Ninguno" de Prenda, Deporte o Línea (como en Género). */
    public function toggleTodosAtributo(string $atributo): void
    {
        [$excl, $disp] = match ($atributo) {
            'tipo'       => ['tiposExcluidos', 'tiposDisponibles'],
            'disciplina' => ['disciplinasExcluidas', 'disciplinasDisponibles'],
            'linea'      => ['lineasExcluidas', 'lineasDisponibles'],
            default      => [null, null],
        };
        if ($excl === null) {
            return;
        }
        $this->$excl = empty($this->$excl) ? array_column($this->$disp, 'v') : [];
        $this->recargarTodo();
    }

    public function toggleTalle(string $talle): void
    {
        $i = array_search($talle, $this->tallesSeleccionados, true);
        $i === false ? $this->tallesSeleccionados[] = $talle : array_splice($this->tallesSeleccionados, $i, 1);
        $this->tallesSeleccionados = array_values($this->tallesSeleccionados);
        $this->recargarTodo();
    }

    public function limpiarTallesTipos(): void
    {
        $this->tallesSeleccionados = [];
        $this->tiposExcluidos  = [];
        $this->disciplinasExcluidas = [];
        $this->lineasExcluidas = [];
        $this->recargarTodo();
    }

    /** Búsqueda global: texto en el nombre del producto y/o marcas elegidas (alias de productos: p). */
    private function filtrosBusqueda($q)
    {
        if ($this->tablaFiltroBuscar !== '') {
            $q->where('p.nombre', 'like', '%' . $this->tablaFiltroBuscar . '%');
        }
        if (!empty($this->tablaFiltroMarcas)) {
            $q->whereIn('p.marca', $this->tablaFiltroMarcas);
        }
        return $q;
    }

    /** Aplica categoría, tipo y talles (solo rubros con filtro_categoria, p. ej. ropa). Alias de productos: p. */
    private function filtrosRopa($q)
    {
        if (!config("rubros.rubros.{$this->rubro}.filtro_categoria")) {
            return $q;
        }
        if ($this->categoriaFiltro !== '') {
            $q->where('p.categoria', $this->categoriaFiltro);
        }
        if ($this->precioMin) {
            $q->where('o.precio_actual', '>=', $this->precioMin);
        }
        if ($this->precioMax) {
            $q->where('o.precio_actual', '<=', $this->precioMax);
        }
        // Atributos tipo "categoría": todo seleccionado por defecto; se excluye lo desmarcado.
        // Los productos sin ese dato (NULL) siempre pasan.
        foreach ([['tipo', $this->tiposExcluidos, $this->tiposDisponibles], ['disciplina', $this->disciplinasExcluidas, $this->disciplinasDisponibles], ['linea', $this->lineasExcluidas, $this->lineasDisponibles]] as [$col, $excluidos, $disponibles]) {
            if (empty($excluidos)) {
                continue;
            }
            if (!empty($disponibles) && count($excluidos) >= count($disponibles)) {
                $q->whereRaw('1 = 0');   // "× Ninguno": todo desmarcado = sin resultados (igual que Género)
                continue;
            }
            $q->where(fn($w) => $w->whereNull("p.$col")->orWhereNotIn("p.$col", $excluidos));
        }
        if (!empty($this->tallesSeleccionados)) {
            // productos.talles = "XS, S, M" (solo talles con stock): se busca el talle entre separadores
            $q->where(function ($w) {
                foreach ($this->tallesSeleccionados as $t) {
                    // talles_norm: calzado normalizado (H10 = talle hombre, M7 = talle mujer); cae a talles si no hay
                    $w->orWhereRaw("CONCAT(', ', COALESCE(p.talles_norm, p.talles), ', ') LIKE ?", ['%, ' . addcslashes($t, '%_\\') . ', %']);
                }
            });
        }
        return $q;
    }

    /**
     * Opciones de tipo y de talle según los productos en oferta de la corrida actual (respetando género y categoría).
     * Los talles se agrupan por sistema porque no son comparables entre sí.
     */
    private function cargarOpcionesRopa(): void
    {
        $this->tiposDisponibles  = [];
        $this->disciplinasDisponibles = [];
        $this->lineasDisponibles = [];
        $this->tallesDisponibles = [];
        if (!config("rubros.rubros.{$this->rubro}.filtro_categoria") || !$this->corridaActualId) {
            return;
        }

        $rows = DB::table('productos as p')
            ->join('observaciones as o', 'o.producto_id', '=', 'p.id')
            ->where('o.corrida_id', $this->corridaActualId)
            ->where('o.tipo_descuento', 'rebaja')
            ->whereIn('p.tienda_id', array_keys($this->tiendas))
            ->when(count($this->generosSeleccionados) < count($this->generosDisponibles), fn($q) => $q->whereIn('p.genero', $this->generosSeleccionados))
            ->when($this->categoriaFiltro !== '', fn($q) => $q->where('p.categoria', $this->categoriaFiltro))
            ->get(['p.categoria', 'p.tipo', 'p.talles', 'p.talles_norm', 'p.disciplina', 'p.linea']);

        $tipos = [];
        $disc = [];
        $lineas = [];
        $grupos = [];
        foreach ($rows as $r) {
            if ($r->tipo) {
                $tipos[$r->tipo] = ($tipos[$r->tipo] ?? 0) + 1;
            }
            if ($r->disciplina) {
                $disc[$r->disciplina] = ($disc[$r->disciplina] ?? 0) + 1;
            }
            if ($r->linea) {
                $lineas[$r->linea] = ($lineas[$r->linea] ?? 0) + 1;
            }
            foreach (array_filter(array_map('trim', explode(',', (string) ($r->talles_norm ?? $r->talles)))) as $t) {
                $g = self::grupoTalle($r->categoria, $t);
                $grupos[$g][$t] = ($grupos[$g][$t] ?? 0) + 1;
            }
        }

        arsort($tipos);
        arsort($disc);
        arsort($lineas);
        $this->lineasDisponibles = array_map(fn($v, $n) => ['v' => $v, 'n' => $n], array_keys($lineas), $lineas);
        $this->disciplinasDisponibles = array_map(fn($v, $n) => ['v' => $v, 'n' => $n], array_keys($disc), $disc);
        $this->tiposDisponibles = array_map(fn($v, $n) => ['v' => $v, 'n' => $n], array_keys($tipos), $tipos);

        $orden = ['Letras', 'Niños (T / X / meses)', 'Numérico (ropa niños)', 'Calzado hombre (talle H)', 'Calzado mujer (talle M)', 'Calzado niños (número)', 'Calzado infantil (C / Y)', 'Numérico (accesorios)', 'Otros'];
        uksort($grupos, fn($a, $b) => array_search($a, $orden) <=> array_search($b, $orden));
        foreach ($grupos as $g => $talles) {
            uksort($talles, fn($a, $b) => self::ordenTalle($a) <=> self::ordenTalle($b));
            $this->tallesDisponibles[] = [
                'grupo' => $g,
                // 'e' = etiqueta a mostrar (sin el prefijo H/M del calzado normalizado)
                'items' => array_map(fn($v, $n) => ['v' => (string) $v, 'n' => $n, 'e' => preg_replace('/^[HM](?=\d)/', '', (string) $v)], array_keys($talles), $talles),
            ];
        }

        // descartar selecciones que ya no existen con los filtros actuales
        $validos = [];
        foreach ($this->tallesDisponibles as $g) {
            $validos = array_merge($validos, array_column($g['items'], 'v'));
        }
        $this->tallesSeleccionados = array_values(array_intersect($this->tallesSeleccionados, $validos));
        $this->tiposExcluidos  = array_values(array_intersect($this->tiposExcluidos, array_column($this->tiposDisponibles, 'v')));
        $this->disciplinasExcluidas = array_values(array_intersect($this->disciplinasExcluidas, array_column($this->disciplinasDisponibles, 'v')));
        $this->lineasExcluidas = array_values(array_intersect($this->lineasExcluidas, array_column($this->lineasDisponibles, 'v')));
    }

    /** "S, M, S, L" (varios colores) -> "S, M, L" con orden natural. */
    private function unirTalles(string $talles): string
    {
        $t = array_values(array_unique(array_filter(array_map('trim', explode(',', $talles)))));
        usort($t, fn($a, $b) => self::ordenTalle($a) <=> self::ordenTalle($b));
        return implode(', ', $t);
    }

    private static function grupoTalle(?string $categoria, string $t): string
    {
        $u = mb_strtoupper(trim($t));
        if (preg_match('/^(XXS|XS|S|M|L|XL|XXL|[2-6]XL|[2-4]XS)$/', $u))        return 'Letras';
        if (preg_match('/^\d+(\.5)?[CY]$/', $u))                         return 'Calzado infantil (C / Y)';
        if (preg_match('/^(\d+T|\dX|\d+-\d+M)$/', $u))                  return 'Niños (T / X / meses)';
        if (preg_match('/^H\d/', $u))                                    return 'Calzado hombre (talle H)';   // talles_norm: H10
        if (preg_match('/^M\d/', $u))                                    return 'Calzado mujer (talle M)';    // talles_norm: M7
        if (preg_match('/^\d+(\.5)?$/', $u)) {
            return match ($categoria) {
                'calzado'    => (float) $u >= 30 ? 'Calzado (talle AR / EU)' : 'Calzado niños (número)',
                'accesorios' => 'Numérico (accesorios)',
                default      => 'Numérico (ropa niños)',
            };
        }
        return 'Otros';
    }

    /** Orden natural de talles: XS < S < M < L < XL < 2XL; números por valor; resto alfabético. */
    private static function ordenTalle(string $t): array
    {
        $letras = ['3XS', '2XS', 'XXS', 'XS', 'S', 'M', 'L', 'XL', 'XXL', '2XL', '3XL', '4XL', '5XL'];
        $u = mb_strtoupper(trim($t));
        if (($i = array_search($u, $letras, true)) !== false) return [0, $i, ''];
        if (preg_match('/^[HM]?\s*(\d+(?:\.\d)?)/', $u, $m))               return [1, (float) $m[1], $u];   // 8, 9.5, H10, M7
        return [2, 0, $u];
    }

    public function limpiarFiltroTiendasTabla(): void
    {
        $this->tablaFiltroTiendas = [];
        $this->cargarTabla();
    }

    public function limpiarFiltrosTabla(): void
    {
        $this->tablaFiltroMarcas  = [];
        $this->tablaMarcaBuscar   = '';
        $this->tablaFiltroBuscar  = '';
        $this->tablaFiltroTiendas = [];
        $this->tablaSortCol1 = 'descuento_pct';
        $this->tablaSortDir1 = 'desc';
        $this->tablaSortCol2 = '';
        $this->tablaSortDir2 = 'desc';
        $this->tablaSortCol3 = '';
        $this->tablaSortDir3 = 'desc';
        $this->guardarFiltrosEnSession();
        $this->recargarTodo();
    }

    public function resetearFiltros(): void
    {
        $this->descMin              = 0;
        $this->descMax              = 90;
        $this->tiendasSeleccionadas = array_keys($this->tiendas);
        $this->generosSeleccionados = $this->generosDisponibles;
        $this->categoriaFiltro      = '';
        $this->tallesSeleccionados  = [];
        $this->tiposExcluidos   = [];
        $this->disciplinasExcluidas = [];
        $this->lineasExcluidas = [];
        $this->precioMin            = null;
        $this->precioMax            = null;
        $this->mlSeleccionados      = $this->mlDisponibles;
        $this->tablaFiltroBuscar  = '';
        $this->tablaFiltroMarcas  = [];
        $this->tablaFiltroTiendas = [];
        $this->tablaMarcaBuscar   = '';
        $this->joyasOcultarFreq   = false;
        $this->guardarFiltrosEnSession();
        $this->recargarTodo();
    }

    // ── Favoritos ─────────────────────────────────────────────────
    public function guardarFavorito(): void
    {
        if (!auth()->check() || !trim($this->favoritoNombre)) return;

        \App\Models\Favorito::guardar(auth()->id(), trim($this->favoritoNombre), [
            'categoriaFiltro'    => $this->categoriaFiltro,
            'tiposExcluidos' => $this->tiposExcluidos,
            'disciplinasExcluidas' => $this->disciplinasExcluidas,
            'lineasExcluidas' => $this->lineasExcluidas,
            'tallesSeleccionados' => $this->tallesSeleccionados,
            'tablaSnapshotTipo'  => $this->tablaSnapshotTipo,
            'tablaFiltroMarcas'  => $this->tablaFiltroMarcas,
            'tablaFiltroTiendas' => $this->tablaFiltroTiendas,
            'tablaFiltroBuscar'  => $this->tablaFiltroBuscar,
            'tablaSortCol1'      => $this->tablaSortCol1,
            'tablaSortDir1'      => $this->tablaSortDir1,
            'tablaSortCol2'      => $this->tablaSortCol2,
            'tablaSortDir2'      => $this->tablaSortDir2,
            'tablaSortCol3'      => $this->tablaSortCol3,
            'tablaSortDir3'      => $this->tablaSortDir3,
        ], $this->favTipo());
        $this->favoritoNombre = '';
        $this->cargarFavoritosLista();
    }

    public function aplicarFavorito(string $nombre): void
    {
        if (!auth()->check()) return;
        $favs = \App\Models\Favorito::listar(auth()->id(), $this->favTipo());
        $cfg  = $favs[$nombre] ?? null;
        if (!$cfg) return;

        $this->tablaSnapshotTipo  = $cfg['tablaSnapshotTipo'] ?? 'ultimo';
        $this->tablaFiltroMarcas  = $cfg['tablaFiltroMarcas'] ?? [];
        $this->tablaFiltroTiendas = $cfg['tablaFiltroTiendas'] ?? [];
        $this->tablaFiltroBuscar  = $cfg['tablaFiltroBuscar'] ?? '';
        $this->tablaSortCol1      = $cfg['tablaSortCol1'] ?? 'descuento_pct';
        $this->tablaSortDir1      = $cfg['tablaSortDir1'] ?? 'desc';
        $this->tablaSortCol2      = $cfg['tablaSortCol2'] ?? '';
        $this->tablaSortDir2      = $cfg['tablaSortDir2'] ?? 'desc';
        $this->tablaSortCol3      = $cfg['tablaSortCol3'] ?? '';
        $this->tablaSortDir3      = $cfg['tablaSortDir3'] ?? 'desc';
        $this->categoriaFiltro    = $cfg['categoriaFiltro'] ?? '';
        $this->tiposExcluidos = $cfg['tiposExcluidos'] ?? [];
        $this->disciplinasExcluidas = $cfg['disciplinasExcluidas'] ?? [];
        $this->lineasExcluidas = $cfg['lineasExcluidas'] ?? [];
        $this->tallesSeleccionados = $cfg['tallesSeleccionados'] ?? [];
        $this->cargarOpcionesRopa();
        $this->cargarTabla();
    }

    public function borrarFavorito(string $nombre): void
    {
        if (!auth()->check()) return;
        \App\Models\Favorito::borrar(auth()->id(), $nombre, $this->favTipo());
        $this->cargarFavoritosLista();
    }

    /** Los favoritos son por rubro: perfumes conserva el tipo 'tabla' (datos existentes); el resto usa 'tabla_<rubro>'. */
    private function favTipo(): string
    {
        return $this->rubro === config('rubros.default') ? 'tabla' : 'tabla_' . $this->rubro;
    }

    private function cargarFavoritosLista(): void
    {
        if (!auth()->check()) {
            $this->favoritosLista = [];
            return;
        }
        $this->favoritosLista = array_keys(\App\Models\Favorito::listar(auth()->id(), $this->favTipo()));
    }

    private function cargarMarcasDisponibles(): void
    {
        $this->marcasDisponibles = DB::table('productos')
            ->whereIn('tienda_id', array_keys($this->tiendas))
            ->whereNotNull('marca')
            ->where('marca', '!=', '')
            ->when(!empty(self::MARCAS_EXCLUIDAS), fn($q) => $q->where(fn($q2) => $q2->whereNull('marca')->orWhereNotIn(DB::raw('LOWER(marca)'), self::MARCAS_EXCLUIDAS)))
            ->distinct()
            ->orderBy('marca')
            ->pluck('marca')
            ->toArray();
    }

    private function cargarMlDisponibles(): void
    {
        $this->mlDisponibles = DB::table('productos')
            ->whereIn('tienda_id', array_keys($this->tiendas))
            ->whereNotNull('tamano_ml')
            ->where('tamano_ml', '>', 0)
            ->distinct()
            ->orderBy('tamano_ml')
            ->pluck('tamano_ml')
            ->map(fn($v) => (int) $v)
            ->toArray();
    }

    // ─────────────────────────────────────────────────────────────
    private function tiendaIds(): array
    {
        if (empty($this->tiendasSeleccionadas)) return [-1];
        return array_map('intval', $this->tiendasSeleccionadas);
    }

    private function aplicarFiltroMl(\Illuminate\Database\Query\Builder $q): \Illuminate\Database\Query\Builder
    {
        if (!empty($this->mlDisponibles) && count($this->mlSeleccionados) < count($this->mlDisponibles)) {
            if (empty($this->mlSeleccionados)) {
                $q->whereRaw('1 = 0'); // nada seleccionado → sin resultados
            } else {
                $q->whereIn('p.tamano_ml', $this->mlSeleccionados);
            }
        }
        return $q;
    }

    public function render()
    {
        return view('livewire.dashboard');
    }
}
