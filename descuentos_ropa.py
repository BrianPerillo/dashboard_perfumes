#!/usr/bin/env python3
"""
Scraper de DESCUENTOS DE ROPA / CALZADO (rubro "ropa").  Hermano de descuentos_master.py (perfumes).

Primer sitio: Nike Argentina (www.nike.com.ar), tienda VTEX IO detras de Cloudflare:
  * Las consultas HTTP directas (requests) reciben 403, asi que se usa UN navegador real (Playwright).
  * El HTML de cada pagina de listado trae embebido el estado de VTEX (<template data-varname="__STATE__">)
    con los productos, su precio de lista vs. precio de venta y los SKUs (talle / color / stock).
    NO se llama a ninguna API: se lee una pagina a la vez, con pausas largas (el sitio responde 429 si se
    insiste) y reintentos con espera creciente.

Uso:
    python descuentos_ropa.py --solo nike --no-db                  # prueba: Excel en excel_ropa/, sin base
    python descuentos_ropa.py --solo nike --max-paginas 2 --no-db  # prueba corta
    python descuentos_ropa.py --modo catalogo --no-db              # recorre el catalogo completo y calcula descuentos
    python descuentos_ropa.py                                      # corrida normal (guarda en MySQL)

Documentacion: ai-context/ropa-01-scraper-nike.md
"""
from __future__ import annotations

import argparse
import json
import logging
import os
import random
import re
import sys
import time
from dataclasses import dataclass, field, asdict
from datetime import datetime
from typing import Optional

logging.basicConfig(level=logging.INFO, format="%(asctime)s  %(levelname)-8s %(message)s", datefmt="%H:%M:%S")
log = logging.getLogger("ropa")

USER_AGENT = ("Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) "
              "Chrome/124.0.0.0 Safari/537.36")
RUBRO = "ropa"
PAGINA_TAMANO = 18          # productos por pagina del listado de Nike (from=0,to=17)

# --------------------------------------------------------------------------- #
# Sitios y secciones
# --------------------------------------------------------------------------- #
# modo "descuento":     (defecto) categorias ordenadas por mayor descuento; corta al aparecer una pagina sin ofertas.
# modo "oportunidades": las secciones /oportunidades del sitio (cobertura parcial: no incluye todo el calzado).
SITES = [
    {
        "name": "nike", "platform": "vtex_io", "host": "www.nike.com.ar",
        "secciones": {
            "oportunidades": [
                {"path": "/oportunidades/hombre/ropa",     "genero": "hombre", "categoria": "ropa"},
                {"path": "/oportunidades/hombre/calzado",  "genero": "hombre", "categoria": "calzado"},
                {"path": "/oportunidades/mujer/ropa",      "genero": "mujer",  "categoria": "ropa"},
                {"path": "/oportunidades/mujer/calzado",   "genero": "mujer",  "categoria": "calzado"},
                {"path": "/oportunidades/nino-a/ropa",     "genero": "ninos",  "categoria": "ropa"},
                {"path": "/oportunidades/nino-a/calzado",  "genero": "ninos",  "categoria": "calzado"},
            ],
            # DESCUENTO (por defecto): listados de cada categoria ORDENADOS POR MAYOR DESCUENTO
            # (?order=OrderByBestDiscountDESC, el mismo orden que ofrece el sitio). Se pagina hasta que una pagina
            # ya no trae ningun producto con descuento: cobertura completa con pocas paginas.
            "descuento": [
                {"path": "/hombre/calzado", "genero": "hombre", "categoria": "calzado",    "orden": "OrderByBestDiscountDESC", "cortar_sin_descuento": True},
                {"path": "/hombre/ropa",    "genero": "hombre", "categoria": "ropa",       "orden": "OrderByBestDiscountDESC", "cortar_sin_descuento": True},
                {"path": "/mujer/calzado",  "genero": "mujer",  "categoria": "calzado",    "orden": "OrderByBestDiscountDESC", "cortar_sin_descuento": True},
                {"path": "/mujer/ropa",     "genero": "mujer",  "categoria": "ropa",       "orden": "OrderByBestDiscountDESC", "cortar_sin_descuento": True},
                {"path": "/nino-a/calzado", "genero": "ninos",  "categoria": "calzado",    "orden": "OrderByBestDiscountDESC", "cortar_sin_descuento": True},
                {"path": "/nino-a/ropa",    "genero": "ninos",  "categoria": "ropa",       "orden": "OrderByBestDiscountDESC", "cortar_sin_descuento": True},
                {"path": "/accesorios",     "genero": "unisex", "categoria": "accesorios", "orden": "OrderByBestDiscountDESC", "cortar_sin_descuento": True},
            ],
        },
        "pag_max": 60,
    },
]


# --------------------------------------------------------------------------- #
# Modelo
# --------------------------------------------------------------------------- #
@dataclass
class ItemRopa:
    tienda: str
    marca: str
    nombre: str
    referencia: str                 # productReference (ej. IH0745-010): identifica modelo+color
    genero: str                     # hombre | mujer | ninos | unisex
    categoria: str                  # ropa | calzado | accesorios
    tipo: str                       # remera, buzo, zapatillas... (spec "Tipo de producto")
    color: str
    talles: list = field(default_factory=list)   # talles con stock
    precio_lista: float = 0.0
    precio_actual: float = 0.0
    descuento_pct: float = 0.0
    url: str = ""
    imagen: str = ""
    seccion: str = ""
    disciplina: str = ""            # Futbol, Running, Moda, Basquet... (spec "Disciplina Extendida")
    linea: str = ""                 # Air Max, Air Force 1, Mercurial, Dunk... (spec "Icono")

    @property
    def ahorro(self) -> float:
        return max(self.precio_lista - self.precio_actual, 0.0)

    @property
    def clave(self) -> tuple:
        return (self.tienda, self.referencia or self.url)


def _dto(lista: float, actual: float) -> float:
    return round((lista - actual) / lista * 100, 2) if lista and lista > actual else 0.0


# --------------------------------------------------------------------------- #
# Lectura del estado VTEX embebido
# --------------------------------------------------------------------------- #
_RE_STATE = re.compile(r'data-varname="__STATE__"[^>]*>\s*<script>(.*?)</script>', re.S)

_GENERO_MAP = {"hombre": "hombre", "mujer": "mujer", "niño": "ninos", "nino": "ninos", "niños": "ninos",
               "ninos": "ninos", "niña": "ninos", "nina": "ninos", "niñas": "ninos", "ninas": "ninos",
               "unisex": "unisex"}


def extraer_state(html: str) -> Optional[dict]:
    m = _RE_STATE.search(html)
    if not m:
        return None
    try:
        return json.loads(m.group(1))
    except ValueError:
        return None


def _ref(st: dict, x):
    """Resuelve una referencia {'type':'id','id':...} del estado de Apollo."""
    if isinstance(x, dict) and x.get("type") == "id" and "id" in x:
        return st.get(x["id"], {})
    return x


def _specs(st: dict, p: dict) -> dict:
    """specificationGroups[0] -> {nombre: [valores]}"""
    out: dict = {}
    for g in p.get("specificationGroups") or []:
        g = _ref(st, g)
        if g.get("name") != "allSpecifications":
            continue
        for s in g.get("specifications") or []:
            s = _ref(st, s)
            vals = (s.get("values") or {}).get("json") or []
            out[s.get("name")] = vals
    return out


def _skus(st: dict, p: dict) -> list[dict]:
    """SKUs disponibles: [{talle, color, precio, lista, stock}]"""
    out = []
    key = next((k for k in p if k.startswith("items(")), None)
    for it in (p.get(key) or []) if key else []:
        it = _ref(st, it)
        var = {}
        for v in it.get("variations") or []:
            v = _ref(st, v)
            vals = (v.get("values") or {}).get("json") or []
            var[(v.get("name") or "").lower()] = vals[0] if vals else ""
        imgs = it.get("images") or []
        img = (_ref(st, imgs[0]) or {}).get("imageUrl", "") if imgs else ""
        for s in it.get("sellers") or []:
            s = _ref(st, s)
            co = _ref(st, s.get("commertialOffer"))
            if not co:
                continue
            out.append({"talle": var.get("talle", ""), "color": var.get("color", ""), "img": img,
                        "precio": float(co.get("Price") or 0), "lista": float(co.get("ListPrice") or 0),
                        "stock": int(co.get("AvailableQuantity") or 0) if "AvailableQuantity" in co else None})
            break
    return out


def normalizar_talles(genero_db: str, categoria: str, talles: list) -> str:
    """Talles de CALZADO normalizados (copia de app/Support/Talles.php (PHP): mantener iguales).
    masculino: 8 -> H8 | femenino: 5 -> M5 | unisex: "H 10 / M 11.5" -> H10, M11.5 (numero simple unisex -> H) |
    ninos (5.5Y, 13C, numeros) sin cambios. Ropa y accesorios no se modifican."""
    if categoria != "calzado" or not talles:
        return ", ".join(talles)
    out = []
    for t in (x.strip() for x in talles):
        m = re.match(r"^H\s*(\d+(?:\.\d)?)\s*/\s*M\s*(\d+(?:\.\d)?)$", t, re.I)
        if m:
            out += ["H" + m.group(1), "M" + m.group(2)]
        elif re.match(r"^\d+(?:\.\d)?[CY]$", t, re.I):
            out.append(t.upper())
        elif re.match(r"^\d+(?:\.\d)?$", t):
            out.append(("M" + t) if genero_db == "femenino" else (t if genero_db == "ninos" else "H" + t))
        else:
            out.append(t)
    return ", ".join(dict.fromkeys(out))


_ORDEN_LETRAS = ["XXS", "XS", "S", "M", "L", "XL", "XXL", "2XL", "3XL", "4XL", "5XL"]


def _orden_talle(t: str) -> tuple:
    """XS < S < M < L < XL < 2XL...; talles numericos (calzado: 8, 8.5, 9) por valor; resto al final."""
    u = (t or "").strip().upper()
    if u in _ORDEN_LETRAS:
        return (0, _ORDEN_LETRAS.index(u), u)
    try:
        return (1, float(u.replace(",", ".").split()[0]), u)
    except ValueError:
        return (2, 0, u)


def productos_de_state(st: dict, host: str, tienda: str, seccion: dict) -> tuple[list[ItemRopa], int, int]:
    """Devuelve (items, recordsFiltered, n_crudos): n_crudos = productos que devolvio el sitio en esta pagina, ANTES de
    descartar los que no se pueden leer (sin precio, etc.). El fin del listado se decide con n_crudos, no con len(items)."""
    # El estado puede traer VARIAS consultas productSearch (carruseles/banners con 0 resultados + el listado real):
    # se toma la que tiene mas productos.
    candidatos = [v for k, v in st.items() if k.startswith("$ROOT_QUERY.productSearch(") and "products" in v]
    ps = max(candidatos, key=lambda v: (len(v.get("products") or []), int(v.get("recordsFiltered") or 0)), default=None)
    if not ps:
        return [], 0, 0
    total = int(ps.get("recordsFiltered") or 0)
    items: list[ItemRopa] = []
    for r in ps.get("products") or []:
        p = _ref(st, r)
        if not p:
            continue
        pr = _ref(st, p.get("priceRange"))
        venta = _ref(st, pr.get("sellingPrice")) if pr else {}
        lista = _ref(st, pr.get("listPrice")) if pr else {}
        actual = float(venta.get("lowPrice") or 0)
        precio_lista = float(lista.get("lowPrice") or 0)
        skus = _skus(st, p)
        if (not actual or not precio_lista) and skus:       # respaldo: SKUs
            actual = min(s["precio"] for s in skus if s["precio"]) if any(s["precio"] for s in skus) else 0
            precio_lista = max(s["lista"] for s in skus)
        if actual <= 0:
            continue
        precio_lista = max(precio_lista, actual)
        sp = _specs(st, p)
        gen_txt = (sp.get("Género") or [""])[0].strip().lower()
        genero = _GENERO_MAP.get(gen_txt, seccion["genero"])
        cat_txt = (sp.get("Categorías") or [""])[0].strip().lower()
        categoria = cat_txt if cat_txt in ("ropa", "calzado", "accesorios") else seccion["categoria"]
        talles = sorted({s["talle"] for s in skus if s["talle"] and (s["stock"] is None or s["stock"] > 0)},
                        key=_orden_talle)
        color = (sp.get("Color PDP") or [""])[0] or (skus[0]["color"] if skus else "")
        link = p.get("link") or ""
        items.append(ItemRopa(
            tienda=tienda, marca=p.get("brand") or "Nike", nombre=(p.get("productName") or "").strip(),
            referencia=p.get("productReference") or str(p.get("productId") or ""),
            genero=genero, categoria=categoria,
            tipo=(sp.get("Tipo de producto") or [""])[0], color=color, talles=talles,
            disciplina=(sp.get("Disciplina Extendida") or [""])[0],
            linea=(sp.get("Icono") or [""])[0],
            precio_lista=precio_lista, precio_actual=actual, descuento_pct=_dto(precio_lista, actual),
            url=f"https://{host}{link}" if link.startswith("/") else link, seccion=seccion["path"],
            imagen=next((s["img"] for s in skus if s.get("img")), "")))
    return items, total, len(ps.get("products") or [])


# --------------------------------------------------------------------------- #
# Navegador (Playwright) con pausas y reintentos
# --------------------------------------------------------------------------- #
class Bloqueado(Exception):
    pass


def _es_bloqueo(titulo: str, status: int) -> bool:
    t = (titulo or "").lower()
    return status in (403, 429) or "un momento" in t or "just a moment" in t


# Ritmo adaptativo: factor que multiplica la pausa entre paginas. Sube tras cada bloqueo (el sitio nos pide ir
# mas lento) y baja de a poco con cada pagina exitosa, sin bajar del ritmo base.
_RITMO = {"factor": 1.0}
RITMO_MAX = 4.0


def _ritmo_bloqueo() -> None:
    _RITMO["factor"] = min(_RITMO["factor"] * 1.6, RITMO_MAX)


def _ritmo_exito() -> None:
    _RITMO["factor"] = max(_RITMO["factor"] * 0.92, 1.0)


def cargar_pagina(page, url: str, espera=(6.0, 10.0), reintentos=3) -> Optional[str]:
    """Carga una URL y devuelve su HTML. Pausa adaptativa entre paginas; espera creciente ante 429/desafio;
    Bloqueado si no cede."""
    for intento in range(1, reintentos + 1):
        time.sleep(random.uniform(*espera) * _RITMO["factor"])
        try:
            resp = page.goto(url, wait_until="domcontentloaded", timeout=60000)
            status = resp.status if resp else 0
            if not _es_bloqueo(page.title(), status):
                page.wait_for_selector('template[data-varname="__STATE__"]', state="attached", timeout=25000)
                _ritmo_exito()
                return page.content()
            _ritmo_bloqueo()
            espera_larga = 45 * intento
            log.warning("  bloqueo (status %s) en %s; espero %ss (intento %d/%d); ritmo x%.1f", status, url, espera_larga, intento, reintentos, _RITMO["factor"])
            time.sleep(espera_larga)
        except Exception as e:  # noqa: BLE001
            log.warning("  error cargando %s: %s (intento %d/%d)", url, str(e)[:120], intento, reintentos)
            time.sleep(10 * intento)
    raise Bloqueado(url)


def scrap_seccion(page, site: dict, seccion: dict, max_paginas: int) -> list[ItemRopa]:
    host, tienda = site["host"], site["name"]
    vistos: dict = {}
    pagina, total = 1, None
    tope = min(max_paginas, site.get("pag_max", 60))
    while pagina <= tope:
        params = []
        if seccion.get("orden"):
            params.append(f"order={seccion['orden']}")
        if pagina > 1:
            params.append(f"page={pagina}")
        url = f"https://{host}{seccion['path']}" + ("?" + "&".join(params) if params else "")
        html = cargar_pagina(page, url)
        st = extraer_state(html or "")
        if not st:
            log.warning("  sin __STATE__ en %s (¿cambió el sitio?)", url)
            break
        items, rec, crudos = productos_de_state(st, host, tienda, seccion)
        if total is None:
            total = rec
            log.info("  %s: %d productos en total", seccion["path"], total)
        nuevos = 0
        for it in items:
            if it.clave not in vistos:
                vistos[it.clave] = it
                nuevos += 1
        log.info("  pagina %d: %d items (%d nuevos)", pagina, len(items), nuevos)
        # Fin del listado: pagina incompleta segun lo que devolvio el SITIO (no los items legibles: una pagina de 18
        # con 2 productos sin precio trae 16 items y NO es la ultima), sin productos nuevos o ya cubierto el total.
        if nuevos == 0 or crudos < PAGINA_TAMANO or (total and pagina * PAGINA_TAMANO >= total):
            break
        if seccion.get("cortar_sin_descuento") and not any(it.descuento_pct > 0 for it in items):
            log.info("  pagina sin descuentos: fin de las ofertas de esta seccion")
            break
        pagina += 1
    return list(vistos.values())


# --------------------------------------------------------------------------- #
# Salida: Excel / JSON
# --------------------------------------------------------------------------- #
def escribir_excel(items: list[ItemRopa], ruta: str) -> str:
    from openpyxl import Workbook
    from openpyxl.styles import Font
    os.makedirs(os.path.dirname(os.path.abspath(ruta)), exist_ok=True)
    wb = Workbook()
    ws = wb.active
    ws.title = "Descuentos"
    cols = ["Tienda", "Genero", "Categoria", "Tipo", "Marca", "Producto", "Referencia", "Color", "Talles",
            "Precio lista", "Precio actual", "Descuento %", "Ahorro", "URL"]
    ws.append(cols)
    for c in ws[1]:
        c.font = Font(bold=True)
    for it in sorted(items, key=lambda i: -i.descuento_pct):
        ws.append([it.tienda, it.genero, it.categoria, it.tipo, it.marca, it.nombre, it.referencia, it.color,
                   ", ".join(it.talles), it.precio_lista, it.precio_actual, it.descuento_pct, it.ahorro, it.url])
    ws.freeze_panes = "A2"
    wb.save(ruta)
    return ruta


def _ruta_con_timestamp(base: str, ahora: datetime) -> str:
    nombre, ext = os.path.splitext(base)
    return f"{nombre}_{ahora.day}-{ahora.month}-{ahora.year}_{ahora:%H-%M-%S}{ext}"


# --------------------------------------------------------------------------- #
# Base de datos (MySQL): mismas tablas que perfumes, con rubro = 'ropa'
# --------------------------------------------------------------------------- #
# Requiere la migracion 2026_10_30_000000_add_rubro_multirubro (columnas rubro, categoria, tipo, color,
# talles, referencia, imagen y genero 'ninos'). Misma config de conexion que descuentos_master.py.
DB_CONFIG = {
    "host": os.getenv("PERF_DB_HOST", "127.0.0.1"),
    "port": int(os.getenv("PERF_DB_PORT", "3306")),
    "user": os.getenv("PERF_DB_USER", "root"),
    "password": os.getenv("PERF_DB_PASS", ""),
    "database": os.getenv("PERF_DB_NAME", "perfumes_descuentos"),
}
# productos.genero de perfumes usa masculino/femenino/unisex: ropa se mapea a esos valores (+ 'ninos')
_GENERO_DB = {"hombre": "masculino", "mujer": "femenino", "ninos": "ninos", "unisex": "unisex"}


def guardar_en_mysql(items: list, fecha_hora: datetime, reporte: list) -> bool:
    """Inserta la corrida (rubro ropa), hace UPSERT de tiendas/productos y agrega las observaciones.
    Guarda TODOS los productos vistos: con descuento -> tipo 'rebaja'; sin descuento -> 'ninguno' (el
    dashboard solo muestra 'rebaja', pero asi se conserva el catalogo y el historial de precios)."""
    try:
        import pymysql
    except ImportError:
        log.error("Falta PyMySQL: pip install pymysql (omito el guardado en MySQL).")
        return False
    try:
        conn = pymysql.connect(charset="utf8mb4", autocommit=False, **DB_CONFIG)
    except Exception as e:  # noqa: BLE001
        log.error("No pude conectar a MySQL (%s). ¿Esta levantado en XAMPP?", e)
        return False
    try:
        with conn.cursor() as cur:
            cur.execute("SHOW COLUMNS FROM corridas LIKE 'rubro'")
            if not cur.fetchone():
                log.error("Falta la migracion multi-rubro (columna corridas.rubro). Ejecuta: php artisan migrate")
                return False
            pcts = [i.descuento_pct for i in items if i.descuento_pct > 0]
            cur.execute(
                "INSERT INTO corridas (rubro, fecha_hora, total_productos, min_descuento, max_descuento) "
                "VALUES (%s,%s,%s,%s,%s) ON DUPLICATE KEY UPDATE total_productos=VALUES(total_productos), "
                "min_descuento=VALUES(min_descuento), max_descuento=VALUES(max_descuento), id=LAST_INSERT_ID(id)",
                (RUBRO, fecha_hora, len(items), round(min(pcts), 2) if pcts else None, round(max(pcts), 2) if pcts else None))
            corrida_id = cur.lastrowid

            tienda_id = {}
            for nombre in {i.tienda for i in items}:
                cur.execute("INSERT INTO tiendas (nombre, rubro) VALUES (%s,%s) ON DUPLICATE KEY UPDATE id=LAST_INSERT_ID(id)",
                            (nombre, RUBRO))
                tienda_id[nombre] = cur.lastrowid

            prod_id = {}
            for it in items:
                nn = (it.referencia or it.url).strip().lower()[:255]       # modelo+color: identidad estable
                if (it.tienda, nn) in prod_id:
                    continue
                cur.execute(
                    "INSERT INTO productos (tienda_id, nombre, nombre_norm, marca, genero, url, categoria, tipo, color, "
                    "talles, referencia, imagen, primera_vez, ultima_vez, disciplina, linea, talles_norm) "
                    "VALUES (%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s) "
                    "ON DUPLICATE KEY UPDATE nombre=VALUES(nombre), marca=VALUES(marca), genero=VALUES(genero), "
                    "url=VALUES(url), categoria=VALUES(categoria), tipo=VALUES(tipo), color=VALUES(color), "
                    "talles=VALUES(talles), referencia=VALUES(referencia), imagen=VALUES(imagen), "
                    "ultima_vez=VALUES(ultima_vez), disciplina=VALUES(disciplina), linea=VALUES(linea), talles_norm=VALUES(talles_norm), id=LAST_INSERT_ID(id)",
                    (tienda_id[it.tienda], it.nombre[:255], nn, it.marca or None, _GENERO_DB.get(it.genero, "unisex"),
                     it.url[:600] or None, it.categoria, (it.tipo or None), (it.color or None)
                     , (", ".join(it.talles)[:255] or None), (it.referencia or None), (it.imagen[:600] or None),
                     fecha_hora, fecha_hora, (it.disciplina[:60] or None), (it.linea[:80] or None),
                     (normalizar_talles(_GENERO_DB.get(it.genero, "unisex"), it.categoria, it.talles)[:255] or None)))
                prod_id[(it.tienda, nn)] = cur.lastrowid

            filas, vistos = [], set()
            for it in items:
                pid = prod_id[(it.tienda, (it.referencia or it.url).strip().lower()[:255])]
                if pid in vistos:
                    continue
                vistos.add(pid)
                filas.append((corrida_id, pid, round(it.precio_lista, 2), round(it.precio_actual, 2), it.descuento_pct,
                              "vtex_state", "rebaja" if it.descuento_pct > 0 else "ninguno"))
            cur.executemany(
                "INSERT INTO observaciones (corrida_id, producto_id, precio_lista, precio_actual, descuento_pct, fuente, tipo_descuento) "
                "VALUES (%s,%s,%s,%s,%s,%s,%s) ON DUPLICATE KEY UPDATE precio_lista=VALUES(precio_lista), "
                "precio_actual=VALUES(precio_actual), descuento_pct=VALUES(descuento_pct), fuente=VALUES(fuente), "
                "tipo_descuento=VALUES(tipo_descuento)", filas)

            cur.execute(
                "UPDATE corridas SET total_productos=(SELECT COUNT(*) FROM observaciones WHERE corrida_id=%s), "
                "min_descuento=(SELECT MIN(descuento_pct) FROM observaciones WHERE corrida_id=%s AND tipo_descuento='rebaja'), "
                "max_descuento=(SELECT MAX(descuento_pct) FROM observaciones WHERE corrida_id=%s AND tipo_descuento='rebaja') "
                "WHERE id=%s", (corrida_id, corrida_id, corrida_id, corrida_id))

            if reporte:
                cur.executemany(
                    "INSERT INTO corrida_sitios (corrida_id, tienda, plataforma, total_encontrados, con_descuento, estado, mensaje, segundos) "
                    "VALUES (%s,%s,%s,%s,%s,%s,%s,%s) ON DUPLICATE KEY UPDATE total_encontrados=VALUES(total_encontrados), "
                    "con_descuento=VALUES(con_descuento), estado=VALUES(estado), mensaje=VALUES(mensaje), segundos=VALUES(segundos)",
                    [(corrida_id, r["tienda"], "vtex_io", r["total"], r["con"], r["estado"], None, r["segundos"]) for r in reporte])
        conn.commit()
        log.info("MySQL: corrida #%d (ropa) guardada: %d observaciones, %d productos.", corrida_id, len(filas), len(prod_id))
        return True
    except Exception as e:  # noqa: BLE001
        conn.rollback()
        log.error("Error guardando en MySQL (rollback): %s", e)
        return False
    finally:
        conn.close()


# --------------------------------------------------------------------------- #
# Main
# --------------------------------------------------------------------------- #
def main() -> int:
    ap = argparse.ArgumentParser(description="Scraper de descuentos de ropa/calzado")
    ap.add_argument("-o", "--output", default=os.path.join("excel_ropa", "descuentos_ropa.xlsx"))
    ap.add_argument("--solo", nargs="*", metavar="TIENDA", help="solo estas tiendas (ej. nike)")
    ap.add_argument("--modo", choices=["descuento", "oportunidades"], default="descuento")
    ap.add_argument("--seccion", nargs="*", metavar="TEXTO", help="solo secciones cuyo path contenga alguno de estos textos")
    ap.add_argument("--max-paginas", type=int, default=60, help="tope de paginas por seccion")
    ap.add_argument("--min-descuento", type=float, default=0.0, help="en el Excel, solo items con al menos este %% (0 = todos con descuento)")
    ap.add_argument("--no-db", action="store_true", help="no guardar en MySQL (solo Excel/JSON)")
    ap.add_argument("--fecha", metavar="AAAA-MM-DDTHH:MM:SS",
                    help="guardar dentro de la corrida existente con esa fecha_hora (completar secciones que fallaron); no genera Excel")
    ap.add_argument("--force-db", action="store_true", help="guardar aunque la corrida este incompleta (secciones bloqueadas/vacias)")
    ap.add_argument("--umbral-secciones", type=float, default=0.7, help="fraccion minima de secciones con datos para guardar en MySQL")
    ap.add_argument("--headful", action="store_true")
    ap.add_argument("--json", metavar="RUTA", help="ademas volcar todos los items a JSON")
    args = ap.parse_args()

    ahora = datetime.fromisoformat(args.fecha) if args.fecha else datetime.now()
    sitios = [s for s in SITES if not args.solo or s["name"] in args.solo]
    if not sitios:
        log.error("Ninguna tienda coincide con --solo %s", args.solo)
        return 2

    try:
        from playwright.sync_api import sync_playwright
    except ImportError:
        log.error("Falta Playwright: pip install playwright && playwright install chromium")
        return 2

    todos: dict = {}
    reporte = []
    with sync_playwright() as p:
        browser = p.chromium.launch(headless=not args.headful)
        ctx = browser.new_context(user_agent=USER_AGENT, locale="es-AR")
        # no descargar imagenes/fuentes/media: menos carga para el sitio y para nosotros
        ctx.route("**/*", lambda r: r.abort() if r.request.resource_type in ("image", "media", "font") else r.continue_())
        for site in sitios:
            secciones = site["secciones"][args.modo]
            if args.seccion:
                secciones = [s for s in secciones if any(t in s["path"] for t in args.seccion)]
            for sec in secciones:
                log.info("== %s %s ==", site["name"], sec["path"])
                t0 = time.time()
                page = ctx.new_page()
                try:
                    items = scrap_seccion(page, site, sec, args.max_paginas)
                    con = sum(1 for i in items if i.descuento_pct > 0)
                    for it in items:
                        todos.setdefault(it.clave, it)
                    log.info("  -> %d productos (%d con descuento)", len(items), con)
                    reporte.append({"tienda": f"{site['name']}_{sec['genero']}_{sec['categoria']}", "total": len(items),
                                    "con": con, "estado": "ok" if items else "vacio", "segundos": round(time.time() - t0, 1)})
                except Bloqueado as e:
                    log.error("  BLOQUEADO: %s (dejo la seccion y sigo)", e)
                    reporte.append({"tienda": f"{site['name']}_{sec['genero']}_{sec['categoria']}", "total": 0, "con": 0,
                                    "estado": "bloqueado", "segundos": round(time.time() - t0, 1)})
                except Exception as e:  # noqa: BLE001
                    log.error("  fallo: %s", e)
                    reporte.append({"tienda": f"{site['name']}_{sec['genero']}_{sec['categoria']}", "total": 0, "con": 0,
                                    "estado": "error", "segundos": round(time.time() - t0, 1)})
                finally:
                    page.close()
                time.sleep(random.uniform(8, 14))
        browser.close()

    items = list(todos.values())
    con_desc = [i for i in items if i.descuento_pct > max(args.min_descuento, 0.0)]
    log.info("TOTAL: %d productos unicos, %d con descuento", len(items), len(con_desc))
    for r in reporte:
        log.info("  %-28s %-10s %4d productos, %4d con descuento, %.0fs", r["tienda"], r["estado"], r["total"], r["con"], r["segundos"])

    if (con_desc or items) and not args.fecha:
        ruta = escribir_excel(con_desc, _ruta_con_timestamp(args.output, ahora))
        log.info("Excel: %s", ruta)
    if args.json:
        with open(args.json, "w", encoding="utf-8") as f:
            json.dump([asdict(i) for i in items], f, ensure_ascii=False, indent=1)
        log.info("JSON: %s", args.json)
    for it in sorted(con_desc, key=lambda i: -i.descuento_pct)[:10]:
        log.info("  %5.1f%%  %-8s %-9s %-34s ($%d -> $%d) talles: %s", it.descuento_pct, it.genero, it.categoria,
                 it.nombre[:34], it.precio_lista, it.precio_actual, ",".join(it.talles))
    if not args.no_db:
        # Una seccion "vacia" (la pagina cargo bien pero no hay ofertas, p. ej. calzado hombre) es legitima.
        # Invalidan la corrida las secciones bloqueadas o con error.
        fallidas = [r for r in reporte if r["estado"] in ("bloqueado", "error")]
        vacias = [r["tienda"] for r in reporte if r["estado"] == "vacio"]
        ok_ratio = 1 - len(fallidas) / len(reporte) if reporte else 0
        if vacias:
            log.warning("Secciones vacias (sin ofertas hoy o cambio del sitio): %s", ", ".join(vacias))
        if not items or ok_ratio < args.umbral_secciones and not args.force_db:
            log.error("CORRIDA INCOMPLETA: %d de %d secciones bloqueadas/con error (minimo %.0f%% sin fallar). NO guardo en MySQL "
                      "para no tapar el ultimo snapshot bueno. Usa --force-db para guardar igual.",
                      len(fallidas), len(reporte), args.umbral_secciones * 100)
            return 3
        if not guardar_en_mysql(items, ahora, reporte):
            return 4
    return 0 if items else 1


if __name__ == "__main__":
    sys.exit(main())
