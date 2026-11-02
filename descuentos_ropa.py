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
    # --- plataforma "vtex_api": catalogo publico de VTEX por HTTP, sin navegador (ver scrap_seccion_api) ---
    {
        "name": "levis", "platform": "vtex_api", "host": "www.levi.com.ar", "marca": "Levi's", "pag_max": 40,
        "specs": {"genero": ["Género"], "tipo": ["Tipo producto"], "color": ["Color", "Color Levi's"], "linea": [], "disciplina": []},   # "Linea" de Levi's son niveles de precio (Good/Better/Best), no lineas de producto
        "secciones": {"descuento": [{"path": "/catalogo", "genero": "unisex", "categoria": "ropa", "etiqueta": "catalogo",
                                      "orden": "OrderByBestDiscountDESC", "cortar_sin_descuento": True}]},
    },
    {
        "name": "topper", "platform": "vtex_api", "host": "www.topper.com.ar", "marca": "Topper", "pag_max": 40,
        "specs": {"genero": ["Género"], "tipo": ["Tipo de Producto"], "color": ["Color"], "linea": [], "disciplina": ["Deporte"]},
        "secciones": {"descuento": [{"path": "/catalogo", "genero": "unisex", "categoria": "ropa", "etiqueta": "catalogo",
                                      "orden": "OrderByBestDiscountDESC", "cortar_sin_descuento": True}]},
    },
    {
        "name": "kevingston", "platform": "kevingston", "host": "www.kevingston.com", "marca": "Kevingston", "pag_max": 60,
        "secciones": {"descuento": [{"path": "/catalogo", "genero": "unisex", "categoria": "ropa", "etiqueta": "catalogo",
                                      "cortar_sin_descuento": True, "talles": True}]},
    },
]
SITES.append({
    "name": "wrangler", "platform": "wrangler", "host": "wranglerjeans.com.ar", "marca": "Wrangler", "pag_max": 20,
    "secciones": {"descuento": [{"path": "/last-chance", "category_id": 171, "genero": "unisex", "categoria": "ropa", "etiqueta": "lastchance"}]},
})
SITES.append({
    "name": "dexter", "platform": "sfcc_dexter", "host": "www.dexter.com.ar", "marca": "Dexter", "pag_max": 60,
    "secciones": {"descuento": [{"path": "/sale", "cgid": "liquidacion", "genero": "unisex", "categoria": "ropa", "etiqueta": "sale"}]},
})
SITES.append({
    "name": "prototype", "platform": "tiendanube", "host": "www.prototype.com.ar", "marca": "Prototype", "pag_max": 100, "talle_opcion": 1,
    "secciones": {"descuento": [{"path": "/productos/", "genero": "unisex", "categoria": "ropa", "etiqueta": "catalogo"}]},
})
SITES.append({
    "name": "puma", "platform": "puma", "host": "ar.puma.com", "marca": "Puma", "pag_max": 60,
    "secciones": {"descuento": [{"path": "/puma-sale", "genero": "unisex", "categoria": "ropa", "etiqueta": "sale"}]},
})
# Montagne esta desactivado por defecto ("activo": False): su precio de oferta es "Comunidad Montagne" (usuarios registrados).
# Se prueba con --solo montagne; ver ai-context/ropa-02 antes de activarlo.
SITES.append({
    "name": "montagne", "platform": "montagne", "host": "www.montagne.com.ar", "marca": "Montagne", "pag_max": 30, "activo": False,
    "secciones": {"descuento": [{"path": "/categoria/164-ofertas", "genero": "unisex", "categoria": "ropa", "etiqueta": "ofertas"}]},
})
# --- Tiendas agregadas el 2026-11-01 (ver ai-context/ropa-02) ---
# Tascani: VTEX con catalogo publico (adaptador vtex_api). Su "Genero" es siempre hombre.
SITES.append({
    "name": "tascani", "platform": "vtex_api", "host": "www.tascani.com.ar", "marca": "Tascani", "pag_max": 40,
    "specs": {"genero": ["Genero"], "tipo": [], "color": ["Color"], "linea": [], "disciplina": []},
    "secciones": {"descuento": [{"path": "/catalogo", "genero": "hombre", "categoria": "ropa", "etiqueta": "catalogo",
                                  "orden": "OrderByBestDiscountDESC", "cortar_sin_descuento": True}]},
})
# Tiendanube (adaptador tiendanube): el genero sale de la seccion (/hombre/, /mujer/...) y el catalogo completo va al final
# (main() descarta duplicados: gana la primera seccion que trae el producto). Talle y color se detectan solos por producto.
SITES.append({
    "name": "catriel", "platform": "tiendanube", "host": "catrielbuenosaires.com", "marca": "Catriel", "pag_max": 40,
    "secciones": {"descuento": [
        {"path": "/hombre/", "genero": "hombre", "categoria": "ropa", "etiqueta": "hombre"},
        {"path": "/mujer/", "genero": "mujer", "categoria": "ropa", "etiqueta": "mujer"},
        {"path": "/productos/", "genero": "unisex", "categoria": "ropa", "etiqueta": "catalogo"}]},
})
SITES.append({
    "name": "mistral", "platform": "tiendanube", "host": "www.mistral.com.ar", "marca": "Mistral", "pag_max": 60,
    "secciones": {"descuento": [
        {"path": "/hombre/", "genero": "hombre", "categoria": "ropa", "etiqueta": "hombre"},
        {"path": "/mujer/", "genero": "mujer", "categoria": "ropa", "etiqueta": "mujer"},
        {"path": "/nino/", "genero": "ninos", "categoria": "ropa", "etiqueta": "ninos"},
        {"path": "/productos/", "genero": "unisex", "categoria": "ropa", "etiqueta": "catalogo"}]},
})
SITES.append({
    "name": "stone", "platform": "tiendanube", "host": "www.stoneshop.com.ar", "marca": "Stone", "pag_max": 40,
    "secciones": {"descuento": [
        {"path": "/hombre/", "genero": "hombre", "categoria": "ropa", "etiqueta": "hombre"},
        {"path": "/mujer/", "genero": "mujer", "categoria": "ropa", "etiqueta": "mujer"},
        {"path": "/kids/", "genero": "ninos", "categoria": "ropa", "etiqueta": "ninos"},
        {"path": "/productos/", "genero": "unisex", "categoria": "ropa", "etiqueta": "catalogo"}]},
})
SITES.append({
    "name": "cuerosmicampo", "platform": "tiendanube", "host": "www.cuerosmicampo.com.ar", "marca": "Cueros Mi Campo", "pag_max": 30,
    "secciones": {"descuento": [{"path": "/productos/", "genero": "unisex", "categoria": "ropa", "etiqueta": "catalogo"}]},
})
# Devre: Magento 2 (HTML del servidor; cada tarjeta trae el JSON de sus talles y precios). Ropa de hombre.
SITES.append({
    "name": "devre", "platform": "magento", "host": "www.devre.la", "marca": "Devré", "pag_max": 60,
    "secciones": {"descuento": [{"path": "/coleccion.html", "genero": "hombre", "categoria": "ropa", "etiqueta": "coleccion"}]},
})
# Brooksfield: PrestaShop. Hoy NINGUN producto de su catalogo (~1.500) tiene precio tachado, asi que queda desactivado
# (se prueba con --solo brooksfield). Si publican ofertas, poner "activo": True (el adaptador lee precio y precio anterior).
SITES.append({
    "name": "brooksfield", "platform": "prestashop", "host": "www.brooksfieldstore.com", "marca": "Brooksfield", "pag_max": 40, "activo": False,
    "secciones": {"descuento": [
        {"path": "/3-hombre-brooksfield", "genero": "hombre", "categoria": "ropa", "etiqueta": "hombre"},
        {"path": "/7-mujer-brooksfield", "genero": "mujer", "categoria": "ropa", "etiqueta": "mujer"}]},
})
# Las plataformas sin secciones propias para "oportunidades" usan las de "descuento".
for _s in SITES:
    _s["secciones"].setdefault("oportunidades", _s["secciones"]["descuento"])


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
    numeros >= 30 (talle argentino/europeo: 44) y ninos (5.5Y, 13C, numeros) sin cambios. Ropa y accesorios no se modifican."""
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
            if genero_db == "ninos" or float(t) >= 30:      # >= 30: talle argentino/europeo (Topper...), no es US H/M
                out.append(t)
            else:
                out.append(("M" + t) if genero_db == "femenino" else "H" + t)
        else:
            out.append(t)
    return ", ".join(dict.fromkeys(out))


_ORDEN_LETRAS = ["3XS", "2XS", "XXS", "XS", "S", "M", "L", "XL", "XXL", "2XL", "3XL", "4XL", "5XL"]


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
# Adaptador "vtex_api": tiendas VTEX con el catalogo publico /api/catalog_system/pub/products/search
# --------------------------------------------------------------------------- #
# Sin navegador: JSON por HTTP (50 productos por pedido, ordenados por mayor descuento) con pausas entre pedidos.
# Se usa donde el sitio lo permite (Levi's, Topper). Cada sitio declara en "specs" como se llaman sus campos.
API_PAGINA = 50
_RE_CALZADO = re.compile(r"calzado|zapatill|botin|sandalia|ojota|chinela|zapato|\bbotas?\b|mocasin|pantufla|championes", re.I)
_RE_ACCESORIOS = re.compile(r"accesorio|mochila|gorra|gorro|bolso|pelota|cintur|billetera|medias|rinonera|riñonera|canillera|"
                            r"lentes|bufanda|guante|valija|neceser|paraguas|bidon|bidón|inflador|\bmat\b|botinero|sombrero|"
                            r"cartera|morral|llavero|pañuelo|panuelo|vincha|munequera|muñequera|reloj", re.I)


def _genero_de_texto(valores) -> Optional[str]:
    """'Hombre' -> hombre; ['Hombre','Mujer'] -> unisex; 'Niños'/'Bebe'/'Junior' -> ninos; sin dato -> None."""
    if isinstance(valores, str):
        valores = [valores]
    gens = set()
    for v in valores or []:
        t = (v or "").strip().lower()
        if re.search(r"mujer|dama|femenin", t):
            gens.add("mujer")
        elif re.search(r"hombre|masculin|varon|varón", t):
            gens.add("hombre")
        elif re.search(r"niñ|nin|kid|junior|bebe|bebé|infant|juvenil", t):
            gens.add("ninos")
        elif "unisex" in t:
            gens.add("unisex")
    if not gens:
        return None
    return gens.pop() if len(gens) == 1 else "unisex"


def _cap(t: str) -> str:
    """Primera letra en mayuscula (las tiendas publican 'remera' / 'Remera' / 'REMERA': un solo valor en los filtros)."""
    t = (t or "").strip()
    return t[:1].upper() + (t[1:].lower() if t.isupper() else t[1:])


def _spec1(p: dict, claves: list) -> str:
    """Primer valor no vacio entre varios nombres posibles de un campo del producto (specs de VTEX)."""
    for k in claves or []:
        v = p.get(k)
        if isinstance(v, list):
            v = next((x for x in v if x), "")
        if v:
            return str(v).strip()
    return ""


def _oferta(it: dict) -> dict:
    return next(((s.get("commertialOffer") or {}) for s in it.get("sellers") or [] if s.get("commertialOffer")), {})


def producto_de_api(p: dict, site: dict, seccion: dict) -> Optional[ItemRopa]:
    """Convierte un producto de la API de catalogo VTEX en ItemRopa (None si no se puede leer o no tiene stock)."""
    sp = site.get("specs", {})
    skus = []
    for it in p.get("items") or []:
        offer = _oferta(it)
        if not offer:
            continue
        talle = it.get("Talle") or ""
        talle = talle[0] if isinstance(talle, list) and talle else talle
        imgs = it.get("images") or []
        skus.append({"talle": str(talle).strip(), "precio": float(offer.get("Price") or 0), "lista": float(offer.get("ListPrice") or 0),
                     "stock": int(offer.get("AvailableQuantity") or 0), "img": (imgs[0].get("imageUrl") if imgs else "") or ""})
    con_stock = [s for s in skus if s["stock"] > 0 and s["precio"] > 0]
    if not con_stock:
        return None
    ref_sku = min(con_stock, key=lambda s: s["precio"])
    actual = ref_sku["precio"]
    precio_lista = max(ref_sku["lista"], actual)
    cats = [c.strip("/") for c in (p.get("categories") or [])]
    arbol = " ".join(cats)
    tipo = _spec1(p, sp.get("tipo")) or (cats[0].split("/")[-1] if cats else "")
    gen_spec = _spec1(p, sp.get("genero"))
    genero = _genero_de_texto(gen_spec) or _genero_de_texto(cats[0].split("/") if cats else None) or seccion["genero"]
    if _RE_CALZADO.search(arbol + " " + tipo):
        categoria = "calzado"
    elif _RE_ACCESORIOS.search(arbol + " " + tipo):
        categoria = "accesorios"
    else:
        categoria = "ropa"
    link = p.get("link") or ""
    talles = sorted({s["talle"] for s in con_stock if s["talle"]}, key=_orden_talle)
    return ItemRopa(
        tienda=site["name"], marca=p.get("brand") or site.get("marca", ""), nombre=(p.get("productName") or "").strip(),
        referencia=str(p.get("productReference") or p.get("productId") or ""), genero=genero, categoria=categoria,
        tipo=_cap(tipo), color=_cap(_spec1(p, sp.get("color"))), talles=talles,
        disciplina=_cap(_spec1(p, sp.get("disciplina"))), linea=_spec1(p, sp.get("linea")),
        precio_lista=precio_lista, precio_actual=actual, descuento_pct=_dto(precio_lista, actual),
        url=(f"https://{site['host']}{link}" if link.startswith("/") else link), seccion=seccion["path"],
        imagen=(ref_sku["img"] or next((s["img"] for s in skus if s["img"]), "")).replace(" ", "%20"))


def cargar_json_api(sesion, url: str, reintentos: int = 3):
    """GET de la API con pausa y espera creciente ante 429/5xx; Bloqueado si no cede."""
    for intento in range(1, reintentos + 1):
        time.sleep(random.uniform(2.5, 4.5) * _RITMO["factor"])
        try:
            r = sesion.get(url, timeout=40)
            if r.status_code in (200, 206):
                _ritmo_exito()
                return r.json(), r.headers.get("resources", "")
            if r.status_code in (403, 429, 500, 502, 503, 504):
                _ritmo_bloqueo()
                espera = 45 * intento
                log.warning("  API respondio %s en %s; espero %ss (intento %d/%d)", r.status_code, url, espera, intento, reintentos)
                time.sleep(espera)
                continue
            log.warning("  API respondio %s en %s", r.status_code, url)
            return [], ""
        except Exception as e:  # noqa: BLE001
            log.warning("  error en API %s: %s (intento %d/%d)", url, str(e)[:100], intento, reintentos)
            time.sleep(10 * intento)
    raise Bloqueado(url)


def _hay_descuento_crudo(p: dict) -> bool:
    """True si algun SKU del producto de la API tiene precio de lista mayor al de venta (aunque no tenga stock)."""
    return any((_oferta(it).get("ListPrice") or 0) > (_oferta(it).get("Price") or 0) for it in p.get("items") or [])


def scrap_seccion_api(sesion, site: dict, seccion: dict, max_paginas: int) -> list[ItemRopa]:
    vistos: dict = {}
    for pagina in range(min(max_paginas, site.get("pag_max", 60))):
        desde = pagina * API_PAGINA
        if desde + API_PAGINA > 2500:           # limite de la API de VTEX
            break
        params = f"_from={desde}&_to={desde + API_PAGINA - 1}"
        if seccion.get("orden"):
            params += f"&O={seccion['orden']}"
        for f in seccion.get("fq") or []:
            params += f"&fq={f}"
        data, recursos = cargar_json_api(sesion, f"https://{site['host']}/api/catalog_system/pub/products/search?{params}")
        if not isinstance(data, list) or not data:
            break
        items = [i for i in (producto_de_api(p, site, seccion) for p in data) if i]
        nuevos = 0
        for it in items:
            if it.clave not in vistos:
                vistos[it.clave] = it
                nuevos += 1
        log.info("  pagina %d: %d productos, %d legibles con stock (%d nuevos) [%s]", pagina + 1, len(data), len(items), nuevos, recursos)
        if len(data) < API_PAGINA or nuevos == 0:
            break
        if seccion.get("cortar_sin_descuento") and not any(_hay_descuento_crudo(p) for p in data):
            log.info("  pagina sin descuentos: fin de las ofertas")
            break
    return list(vistos.values())


# --------------------------------------------------------------------------- #
# Adaptador "kevingston": sitio propio (no VTEX). El listado lo pide la propia pagina a su endpoint interno
# POST /api/v1/products (36 por pagina, order=4 -> mayor descuento) y los talles a GET /api/v1/product/size/{sku}.
# Se llaman desde el navegador, en la misma pagina y con el mismo origen (como hace el sitio), con pausas.
# --------------------------------------------------------------------------- #
_JS_POST = ("async (b) => { const r = await fetch('/api/v1/products', {method:'POST', headers:{'Content-Type':"
            "'application/x-www-form-urlencoded; charset=UTF-8','X-Requested-With':'XMLHttpRequest'}, body:b});"
            " if (!r.ok) return {_status: r.status}; return await r.json(); }")
_JS_GET = ("async (u) => { const r = await fetch(u, {headers:{'X-Requested-With':'XMLHttpRequest'}});"
           " if (!r.ok) return {_status: r.status}; return await r.json(); }")
_KEV_GENERO = {"1": "hombre", "2": "mujer", "7": "ninos"}          # idcategory del sitio
_RE_NO_ROPA = re.compile(r"^(perfume|fragancia|eau de|colonia|body splash|splash)\b", re.I)


def _llamar_kev(page, js: str, arg: str, espera=(2.0, 3.5), reintentos=3):
    for intento in range(1, reintentos + 1):
        time.sleep(random.uniform(*espera) * _RITMO["factor"])
        try:
            d = page.evaluate(js, arg)
        except Exception as e:  # noqa: BLE001
            log.warning("  error en %s: %s (intento %d/%d)", arg[:60], str(e)[:100], intento, reintentos)
            time.sleep(10 * intento)
            continue
        if isinstance(d, dict) and d.get("_status") in (403, 429, 503):
            _ritmo_bloqueo()
            log.warning("  el sitio respondio %s; espero %ss (intento %d/%d)", d["_status"], 45 * intento, intento, reintentos)
            time.sleep(45 * intento)
            continue
        if isinstance(d, dict) and d.get("_status"):
            return {}
        _ritmo_exito()
        return d
    raise Bloqueado(arg[:80])


def scrap_kevingston(page, site: dict, seccion: dict, max_paginas: int) -> list[ItemRopa]:
    host = site["host"]
    resp = page.goto(f"https://{host}/categoria/hombres", wait_until="domcontentloaded", timeout=60000)
    if _es_bloqueo(page.title(), resp.status if resp else 0):
        raise Bloqueado(host)
    page.wait_for_timeout(3000)
    crudos: list[dict] = []
    for pagina in range(1, min(max_paginas, site.get("pag_max", 60)) + 1):
        body = (f"page={pagina}&category=&type=&linea=&condition=&order=4&promotion=&price_from=&price_to=&group_dto="
                "&is_bomba=0&subcategory=&porcentaje=&idcolor=&search=")
        d = _llamar_kev(page, _JS_POST, body)
        datos = ((d or {}).get("data") or {}).get("data") or []
        if not datos:
            break
        crudos += datos
        con_desc = sum(1 for x in datos if float(x.get("porcentaje") or 0) > 0)
        log.info("  pagina %d: %d productos, %d con descuento", pagina, len(datos), con_desc)
        if len(datos) < 36 or (seccion.get("cortar_sin_descuento") and con_desc == 0):
            break
    # tipo = palabra mas comun con que empieza el nombre entre los productos del mismo idtype del sitio
    por_tipo: dict = {}
    for x in crudos:
        por_tipo.setdefault(x.get("idtype"), []).append(((x.get("nombre") or "").split() or [""])[0].lower())
    tipo_de = {k: max(set(v), key=v.count) for k, v in por_tipo.items()}

    items: dict = {}
    for x in crudos:
        nombre = (x.get("nombre") or "").strip()
        precio, lista = float(x.get("precio") or 0), float(x.get("precio_real") or 0)
        if not nombre or precio <= 0 or int(x.get("stock") or 0) <= 0 or _RE_NO_ROPA.search(nombre):
            continue
        lista = max(lista, precio)
        tipo = tipo_de.get(x.get("idtype")) or (nombre.split()[0].lower() if nombre else "")
        arbol = f"{nombre} {tipo}"
        categoria = "calzado" if _RE_CALZADO.search(arbol) else "accesorios" if _RE_ACCESORIOS.search(arbol) else "ropa"
        img = x.get("image") or ""
        if img and not re.search(r"\.(jpe?g|png|webp)$", img, re.I):
            img += "_1.jpg"
        it = ItemRopa(
            tienda=site["name"], marca=site.get("marca", ""), nombre=nombre, referencia=str(x.get("sku") or ""),
            genero=_KEV_GENERO.get(str(x.get("idcategory")), "unisex"), categoria=categoria, tipo=_cap(tipo), color="",
            precio_lista=lista, precio_actual=precio, descuento_pct=_dto(lista, precio),
            url=x.get("url_completa") or f"https://{host}{x.get('url') or ''}", imagen=img, seccion=seccion["path"])
        items.setdefault(it.clave, it)
    lista_items = list(items.values())

    # talles: una consulta por producto CON descuento (los sin descuento no se muestran: no vale la pena)
    con_desc = [i for i in lista_items if i.descuento_pct > 0]
    if seccion.get("talles", True):
        log.info("  pidiendo talles de %d productos con descuento…", len(con_desc))
        for n, it in enumerate(con_desc, 1):
            d = _llamar_kev(page, _JS_GET, f"/api/v1/product/size/{it.referencia}", espera=(0.8, 1.4))
            filas = (d or {}).get("data") or []
            it.talles = sorted({str(f.get("size_nombre")).strip() for f in filas
                                if f.get("size_nombre") and int(f.get("stock") or 0) > 0}, key=_orden_talle)
            if n % 100 == 0:
                log.info("    talles %d/%d", n, len(con_desc))
    return lista_items


# --------------------------------------------------------------------------- #
# Adaptador "montagne": sitio propio en PHP, listado renderizado en el servidor (HTTP simple + BeautifulSoup).
# La seccion /categoria/164-ofertas se pagina con ?p=N&n=20. Los talles solo estan en la ficha (se eligen con JS):
# por ahora no se leen. OJO: el precio de oferta de Montagne se rotula "Comunidad Montagne" (precio para usuarios
# registrados en su comunidad): ver ai-context/ropa-02 antes de activarlo en produccion.
# --------------------------------------------------------------------------- #
_RE_PRECIO = re.compile(r"[\d\.]+")
_ROPA_MONTAGNE = {"campera", "camperas", "chaleco", "remera", "remeras", "pantalon", "pantalón", "buzo", "camisa", "chomba", "bermuda",
                  "short", "calza", "calzas", "polar", "vestido", "pollera", "musculosa", "top", "campión", "campera", "cortaviento",
                  "rompevientos", "pulover", "pullover", "sweater", "jogging", "leggins", "calzas", "capri", "malla", "boxer", "bóxer",
                  "camiseta", "conjunto", "parka", "piluso", "medias", "guantes", "gorro", "gorra", "cuello", "bufanda"}


def _monto(txt: str) -> float:
    m = _RE_PRECIO.search((txt or "").replace("$", "").strip())
    return float(m.group(0).replace(".", "")) if m else 0.0


def scrap_montagne(sesion, site: dict, seccion: dict, max_paginas: int) -> list[ItemRopa]:
    from bs4 import BeautifulSoup
    items: dict = {}
    for pagina in range(1, min(max_paginas, site.get("pag_max", 30)) + 1):
        url = f"https://{site['host']}{seccion['path']}?p={pagina}&n=20&orderby=position&orderway=asc"
        for intento in range(1, 4):
            time.sleep(random.uniform(2.5, 4.5) * _RITMO["factor"])
            r = sesion.get(url, timeout=40, headers={"Accept": "text/html"})
            if r.status_code == 200:
                _ritmo_exito()
                break
            _ritmo_bloqueo()
            log.warning("  %s respondio %s (intento %d/3)", url, r.status_code, intento)
            time.sleep(30 * intento)
        else:
            raise Bloqueado(url)
        soup = BeautifulSoup(r.text, "html.parser")
        tarjetas = soup.select(".product-list")
        if not tarjetas:
            break
        nuevos = 0
        for t in tarjetas:
            a = t.select_one("a.product-list-image-link")
            if not a:
                continue
            href = a.get("href") or ""
            m = re.search(r"/producto/(\d+)-", href)
            lista = _monto((t.select_one(".price-old") or t).get_text()) if t.select_one(".price-old") else 0.0
            actual = _monto(t.select_one(".price-offer").get_text()) if t.select_one(".price-offer") else 0.0
            nombre = " ".join(((a.get("title") or "").split()))
            if not m or not nombre or actual <= 0:
                continue
            lista = max(lista, actual)
            primera = nombre.split()[0].lower()
            gen = _genero_de_texto(nombre) or "unisex"
            categoria = "calzado" if _RE_CALZADO.search(nombre) else "ropa" if primera in _ROPA_MONTAGNE else "accesorios"
            img = t.select_one("img.product-list-image")
            it = ItemRopa(
                tienda=site["name"], marca=site.get("marca", ""), nombre=nombre, referencia=m.group(1), genero=gen,
                categoria=categoria, tipo=_cap(primera), color=", ".join(c.get("title") for c in t.select(".product-list-colors a") if c.get("title")),
                precio_lista=lista, precio_actual=actual, descuento_pct=_dto(lista, actual), url=href,
                imagen=(img.get("src") if img else "") or "", seccion=seccion["path"])
            if it.clave not in items:
                items[it.clave] = it
                nuevos += 1
        log.info("  pagina %d: %d tarjetas (%d nuevas)", pagina, len(tarjetas), nuevos)
        if len(tarjetas) < 20 or nuevos == 0:
            break
    return list(items.values())


# --------------------------------------------------------------------------- #
# Adaptador "wrangler": sitio propio; el listado se pide como fragmento HTML (HTTP simple + BeautifulSoup):
# GET /{seccion}/search?page=N&breadcrumb_category_id=ID  (12 tarjetas por pagina; vacio = fin).
# Una tarjeta = un producto en un color. Por ahora solo la seccion LAST CHANCE (liquidacion, id 171): el resto del
# catalogo casi no tiene descuentos. No trae talles en el listado (van en la ficha).
# --------------------------------------------------------------------------- #
def scrap_wrangler(sesion, site: dict, seccion: dict, max_paginas: int) -> list[ItemRopa]:
    from bs4 import BeautifulSoup
    items: dict = {}
    for pagina in range(1, min(max_paginas, site.get("pag_max", 20)) + 1):
        url = f"https://{site['host']}{seccion['path']}/search?page={pagina}&breadcrumb_category_id={seccion['category_id']}&sc=&orderby="
        for intento in range(1, 4):
            time.sleep(random.uniform(2.5, 4.5) * _RITMO["factor"])
            r = sesion.get(url, timeout=40, headers={"Accept": "text/html,*/*", "X-Requested-With": "XMLHttpRequest"})
            if r.status_code == 200:
                _ritmo_exito()
                break
            _ritmo_bloqueo()
            log.warning("  %s respondio %s (intento %d/3)", url, r.status_code, intento)
            time.sleep(30 * intento)
        else:
            raise Bloqueado(url)
        tarjetas = BeautifulSoup(r.text, "html.parser").select(".shop-card")
        if not tarjetas:
            break
        for t in tarjetas:
            a = t.select_one("a.product-link")
            actual = _monto((t.select_one(".card-product-price") or t).get_text()) if t.select_one(".card-product-price") else 0.0
            lista = _monto(t.select_one(".card-product-price-before").get_text()) if t.select_one(".card-product-price-before") else 0.0
            nombre = " ".join((t.get("data-name") or "").split()).title()
            if not a or not nombre or actual <= 0:
                continue
            lista = max(lista, actual)
            href = a.get("href") or ""
            color_id = (re.search(r"color=(\d+)", href) or [None, ""])[1]
            img = t.select_one("img.image")
            imagen = (img.get("src") if img else "") or ""
            gen_txt = (re.search(r"/(MUJER|HOMBRE|NI[ÑN]OS?|KIDS)/", imagen, re.I) or [None, ""])[1]
            genero = _genero_de_texto(gen_txt) or _genero_de_texto("mujer" if re.search(r"\bwoman\b|\bwomen\b", nombre, re.I) else "hombre" if re.search(r"\bman\b|\bmen\b", nombre, re.I) else "") or "unisex"
            col = t.select_one("input.btn-check[data-id='%s']" % color_id) if color_id else None
            primera = nombre.split()[0].lower()
            it = ItemRopa(
                tienda=site["name"], marca=site.get("marca", ""), nombre=nombre, referencia=f"{t.get('data-id')}-{color_id}".strip("-"),
                genero=genero, categoria="accesorios" if _RE_ACCESORIOS.search(nombre) else "ropa", tipo=_cap(primera),
                color=_cap(col.get("data-name") if col else ""), precio_lista=lista, precio_actual=actual,
                descuento_pct=_dto(lista, actual), url=f"https://{site['host']}{href}", imagen=imagen, seccion=seccion["path"])
            items.setdefault(it.clave, it)
        log.info("  pagina %d: %d tarjetas", pagina, len(tarjetas))
        if len(tarjetas) < 12:
            break
    return list(items.values())


# --------------------------------------------------------------------------- #
# Adaptador "sfcc_dexter": Dexter (Salesforce Commerce Cloud tras Cloudflare), multimarca (Nike, Adidas, Puma, Topper...).
# Se abre la pagina de la seccion en el navegador y las paginas siguientes se piden (mismo origen, como hace el boton
# "ver mas" del sitio) a /on/demandware.store/Sites-Dexter-Site/default/Search-UpdateGrid?cgid=...&srule=product-discount
# &start=N&sz=36. La tarjeta no trae marca ni talles: la marca se deduce del nombre.
# --------------------------------------------------------------------------- #
_MARCAS_MULTI = ["Nike", "Jordan", "Adidas", "Puma", "Topper", "Lotto", "Reebok", "Under Armour", "Fila", "Converse", "Vans", "New Balance",
                 "Umbro", "Asics", "Hoka", "Skechers", "Kappa", "Columbia", "Wilson", "Speedo", "Mizuno", "Salomon", "Le Coq Sportif",
                 "Penalty", "Olympikus", "Crocs", "Champion", "Merrell", "The North Face", "Havaianas", "Ellesse", "Diadora", "Babolat",
                 "Head", "Yonex", "Everlast", "Gadnic", "Kappa", "Hummel", "Joma", "Saucony", "On", "Brooks", "Levi's"]
_JS_FETCH_TXT = "async (u) => { const r = await fetch(u, {headers:{'X-Requested-With':'XMLHttpRequest'}}); return r.ok ? await r.text() : ''; }"
_RE_TARJETA_PRECIO = re.compile(r"[\d\.]+")


def _marca_de_nombre(nombre: str, defecto: str) -> str:
    for m in _MARCAS_MULTI:
        if re.search(r"(?<![A-Za-z])" + re.escape(m) + r"(?![A-Za-z])", nombre, re.I):
            return m if m != "Jordan" else "Jordan"
    return defecto


def _tarjetas_sfcc(html: str, site: dict, seccion: dict) -> tuple[list[ItemRopa], int]:
    from bs4 import BeautifulSoup
    soup = BeautifulSoup(html, "html.parser")
    tiles = soup.select(".product[data-pid]")
    out = []
    for t in tiles:
        a = t.select_one("a.link")
        nombre = " ".join((a.get_text() if a else "").split())
        venta = t.select_one(".sales .value")
        lista = t.select_one(".strike-through .value")
        actual = float(venta.get("content") or 0) if venta else 0.0
        precio_lista = float(lista.get("content") or 0) if lista else 0.0
        if not a or not nombre or actual <= 0:
            continue
        precio_lista = max(precio_lista, actual)
        img = t.select_one("img.primary-image")
        href = a.get("href") or ""
        gen = _genero_de_texto(nombre) or "unisex"
        categoria = "calzado" if _RE_CALZADO.search(nombre) else "accesorios" if _RE_ACCESORIOS.search(nombre) else "ropa"
        out.append(ItemRopa(
            tienda=site["name"], marca=_marca_de_nombre(nombre, "Dexter"), nombre=nombre, referencia=t.get("data-pid") or "",
            genero=gen, categoria=categoria, tipo=_cap(nombre.split()[0].lower()), color="", precio_lista=precio_lista,
            precio_actual=actual, descuento_pct=_dto(precio_lista, actual),
            url=f"https://{site['host']}{href}" if href.startswith("/") else href,
            imagen=(img.get("src") if img else "") or "", seccion=seccion["path"]))
    return out, len(tiles)


def scrap_dexter(page, site: dict, seccion: dict, max_paginas: int) -> list[ItemRopa]:
    resp = page.goto(f"https://{site['host']}{seccion['path']}?srule=product-discount&start=0&sz=36", wait_until="domcontentloaded", timeout=60000)
    if _es_bloqueo(page.title(), resp.status if resp else 0):
        raise Bloqueado(site["host"])
    page.wait_for_timeout(4000)
    vistos: dict = {}
    html = page.content()
    for pagina in range(1, min(max_paginas, site.get("pag_max", 60)) + 1):
        items, n = _tarjetas_sfcc(html, site, seccion)
        nuevos = 0
        for it in items:
            if it.clave not in vistos:
                vistos[it.clave] = it
                nuevos += 1
        log.info("  pagina %d: %d tarjetas, %d legibles (%d nuevas)", pagina, n, len(items), nuevos)
        if n < 36 or nuevos == 0:
            break
        url = (f"/on/demandware.store/Sites-Dexter-Site/default/Search-UpdateGrid?cgid={seccion['cgid']}"
               f"&srule=product-discount&start={pagina * 36}&sz=36")
        html = ""
        for intento in range(1, 4):
            time.sleep(random.uniform(3.0, 5.0) * _RITMO["factor"])
            try:
                html = page.evaluate(_JS_FETCH_TXT, url)
            except Exception as e:  # noqa: BLE001
                log.warning("  error pidiendo %s: %s (intento %d/3)", url, str(e)[:100], intento)
            if html:
                _ritmo_exito()
                break
            _ritmo_bloqueo()
            time.sleep(30 * intento)
        if not html:
            raise Bloqueado(url)
    lista = list(vistos.values())
    if seccion.get("talles", True):
        _talles_dexter(page, lista)
    return lista


def sin_ceros(talles: list) -> list:
    """"05" -> "5" (algunas tiendas publican talles numericos con cero a la izquierda y salian duplicados en el filtro)."""
    return sorted({re.sub(r"^0+(?=\d)", "", (t or "").strip()) for t in talles if (t or "").strip()}, key=_orden_talle)


def expandir_rangos(talles: list) -> list:
    """Talles de calzado publicados como rango ("40-40.5") -> sus dos valores ("40", "40.5")."""
    out = []
    for t in talles:
        t = (t or "").strip()
        # "40-40.5", "39/40", "45/46" y "38.39" (dos talles pegados con punto) -> dos valores
        m = re.match(r"^(\d+(?:\.5)?)\s*[-/]\s*(\d+(?:\.5)?)$", t) or re.match(r"^(\d{2})\.(\d{2})$", t)
        out += [m.group(1), m.group(2)] if m else [t]
    return sorted(set(out), key=_orden_talle)


def _talles_dexter(page, items: list[ItemRopa]) -> None:
    """Talles con stock: se baja el HTML de la ficha de cada producto (mismo origen, sin renderizar) y se leen los
    <li class="variation-attribute-size"> que NO tienen la clase 'disabled'. ~1 s por producto."""
    from bs4 import BeautifulSoup
    log.info("  pidiendo talles de %d productos…", len(items))
    fallos = 0
    for n, it in enumerate(items, 1):
        path = it.url.split("dexter.com.ar", 1)[-1]
        time.sleep(random.uniform(0.6, 1.1) * _RITMO["factor"])
        try:
            html = page.evaluate(_JS_FETCH_TXT, path)
        except Exception as e:  # noqa: BLE001
            html = ""
            log.warning("  error pidiendo %s: %s", path, str(e)[:80])
        if not html:
            fallos += 1
            _ritmo_bloqueo()
            if fallos >= 15:
                log.warning("  demasiados fallos pidiendo talles de Dexter: dejo los que faltan sin talles")
                return
            continue
        _ritmo_exito()
        lis = BeautifulSoup(html, "html.parser").select("li.variation-attribute-size")
        it.talles = expandir_rangos([(li.get("data-attr-value") or "").strip() for li in lis
                                     if "disabled" not in (li.get("class") or []) and li.get("data-attr-value")])
        if n % 200 == 0:
            log.info("    talles %d/%d", n, len(items))


# --------------------------------------------------------------------------- #
# Adaptador "tiendanube": Tiendanube/Nuvemshop (HTML del servidor, HTTP simple). Cada tarjeta trae en data-variants
# el JSON de TODAS sus variantes (color/talle, precio, precio anterior, stock). Mismo formato que el lector de
# perfumes (descuentos_master.py::_items_de_tiendanube); aca se arma un ItemRopa con los talles con stock.
# --------------------------------------------------------------------------- #
import html as _html

_RE_TN_ITEM = re.compile(r'<div\s+class="(?:js-product-item-private product-item|js-item-product\b)')     # dos temas de Tiendanube
_RE_TN_VARIANTS = re.compile(r'data-variants="(.*?)"', re.S)
_RE_TN_NOMBRE = re.compile(r'js-item-name[^>]*>\s*([^<]+?)\s*<')
_RE_TN_URL = re.compile(r'href="(https?://[^"]*/productos/[^"]+?)"')
_RE_TN_IMG = re.compile(r'(?:data-srcset|srcset|src)="(?://|https?://)([^"\s,]*(?:tiendanube|nuvemshop|mitiendanube)[^"\s,]*)')
# Valor de una opcion que parece un talle (S, XL, 2XL, 42, 39-43, "44 AR", S/M, Unico...); lo demas se toma como color.
_RE_TALLE = re.compile(
    r"^(?:(?:[2-6]\s?)?x{0,4}[sl]|m|u|[uú]nic[oa]|talle\s+[uú]nico|\d{1,3}(?:[.,]5)?(?:\s?(?:ar|eu|us|cm))?|\d{2}\s?[-/]\s?\d{2}|"
    r"(?:xxs|xs|s|m|l|xl|xxl|[2-6]xl)\s?/\s?(?:xs|s|m|l|xl|xxl|[2-6]xl))$", re.I)


def _es_talle(valor) -> bool:
    return bool(_RE_TALLE.match(str(valor or "").strip()))


def _opciones_tn(con_stock: list, site: dict) -> tuple:
    """(indice de la opcion de talle, indice de la de color) de las variantes de un producto Tiendanube. Con "talle_opcion"
    en la tienda se usa ese indice (color = option0); si no, se detecta: el orden de las opciones cambia de tienda en tienda
    (y de producto en producto), asi que se elige la opcion cuyos valores parecen talles."""
    if site.get("talle_opcion") is not None:
        return site["talle_opcion"], 0
    mejor, mejor_pct = None, 0.0
    for i in range(3):
        vals = [str(v.get(f"option{i}") or "").strip() for v in con_stock if v.get(f"option{i}")]
        if not vals:
            continue
        pct = sum(1 for x in vals if _es_talle(x)) / len(vals)
        if pct >= 0.6 and pct > mejor_pct:
            mejor, mejor_pct = i, pct
    color = next((i for i in range(3) if i != mejor and any(v.get(f"option{i}") for v in con_stock)), None)
    return mejor, color


def _img_tn(bloque: str) -> str:
    """Primera imagen del producto en el CDN de Tiendanube, a 480 px de ancho (el HTML trae 50/100/240/320/480/...)."""
    mi = _RE_TN_IMG.search(bloque)
    return re.sub(r"-\d+-0\.(webp|jpe?g|png)", r"-480-0.\1", "https://" + mi.group(1)) if mi else ""


def items_de_tiendanube_ropa(html_text: str, site: dict, seccion: dict) -> list[ItemRopa]:
    out: list[ItemRopa] = []
    pos = [m.start() for m in _RE_TN_ITEM.finditer(html_text)] + [len(html_text)]
    for i in range(len(pos) - 1):
        bloque = html_text[pos[i]:pos[i + 1]]
        mv = _RE_TN_VARIANTS.search(bloque)
        mn = _RE_TN_NOMBRE.search(bloque)
        mu = _RE_TN_URL.search(bloque)
        if not (mv and mn and mu):
            continue
        try:
            vars_ = json.loads(_html.unescape(mv.group(1)))
        except ValueError:
            continue
        vars_ = vars_ if isinstance(vars_, list) else [vars_]
        con_stock = [v for v in vars_ if v.get("available") is not False and (v.get("stock") is None or (v.get("stock") or 0) > 0)]
        if not con_stock:
            continue
        v0 = min(con_stock, key=lambda v: float(v.get("price_number") or 1e12))
        actual = float(v0.get("price_number") or 0)
        promo = v0.get("promotional_price_number")
        cap = v0.get("compare_at_price_number")
        lista = float(cap) if cap else actual
        if promo and float(promo) < actual:
            lista, actual = actual, float(promo)
        if actual <= 0:
            continue
        lista = max(lista, actual)
        idx, idx_color = _opciones_tn(con_stock, site)
        talles = sorted({str(v.get(f"option{idx}") or "").strip() for v in con_stock if v.get(f"option{idx}")}, key=_orden_talle) if idx is not None else []
        colores = sorted({str(v.get(f"option{idx_color}") or "").strip() for v in con_stock if v.get(f"option{idx_color}")}) if idx_color is not None else []
        nombre = _html.unescape(mn.group(1)).strip()
        img = _img_tn(bloque)
        # seccion con genero (/hombre/, /mujer/...): manda; en la general ("unisex") se deduce del nombre
        gen = seccion["genero"] if seccion["genero"] != "unisex" else (_genero_de_texto(nombre) or "unisex")
        categoria = "calzado" if _RE_CALZADO.search(nombre) else "accesorios" if _RE_ACCESORIOS.search(nombre) else "ropa"
        out.append(ItemRopa(
            tienda=site["name"], marca=site.get("marca", ""), nombre=nombre, referencia=str(v0.get("product_id") or mu.group(1)),
            genero=gen, categoria=categoria, tipo=_cap(nombre.split()[0].lower()), color=", ".join(colores[:3]), talles=talles,
            precio_lista=lista, precio_actual=actual, descuento_pct=_dto(lista, actual), url=mu.group(1), imagen=img,
            seccion=seccion["path"]))
    return out


def scrap_tiendanube_ropa(sesion, site: dict, seccion: dict, max_paginas: int) -> list[ItemRopa]:
    vistos: dict = {}
    for pagina in range(1, min(max_paginas, site.get("pag_max", 30)) + 1):
        url = f"https://{site['host']}{seccion['path']}?page={pagina}"
        for intento in range(1, 4):
            time.sleep(random.uniform(2.5, 4.5) * _RITMO["factor"])
            r = sesion.get(url, timeout=40, headers={"Accept": "text/html,application/xhtml+xml,*/*"})
            if r.status_code == 200:
                _ritmo_exito()
                break
            _ritmo_bloqueo()
            log.warning("  %s respondio %s (intento %d/3)", url, r.status_code, intento)
            time.sleep(30 * intento)
        else:
            raise Bloqueado(url)
        items = items_de_tiendanube_ropa(r.text, site, seccion)
        total_tarjetas = len(_RE_TN_ITEM.findall(r.text))
        nuevos = 0
        for it in items:
            if it.clave not in vistos:
                vistos[it.clave] = it
                nuevos += 1
        log.info("  pagina %d: %d tarjetas, %d con stock (%d nuevas)", pagina, total_tarjetas, len(items), nuevos)
        if total_tarjetas == 0 or (nuevos == 0 and total_tarjetas > 0 and not items):
            break
        if nuevos == 0:
            break
    return list(vistos.values())


# --------------------------------------------------------------------------- #
# Adaptador "magento": Devre (Magento 2, HTML del servidor, HTTP simple). El listado /coleccion.html?p=N trae 16 tarjetas;
# cada una incluye el JSON del selector de talles (jsonConfig: talles disponibles y, por talle, precio anterior y precio
# final) y un bloque de datos de analitica (item_id). Sin orden por descuento: se recorre toda la seccion.
# --------------------------------------------------------------------------- #
_RE_MG_ITEM = re.compile(r'<li class="item product product-item')
_RE_MG_URL = re.compile(r'<a href="([^"]+)" class="product-item-photo')
_RE_MG_NOMBRE = re.compile(r'class="product-item-link"[^>]*href="[^"]*">\s*([^<]+?)\s*</a>')
_RE_MG_IMG = re.compile(r'class="product-image-photo"\s+src="([^"]+)"')
_RE_MG_ID = re.compile(r'"item_id":"([^"]+)"')
_RE_MG_PRECIO = re.compile(r'data-price-amount="([\d.]+)"\s+data-price-type="(finalPrice|oldPrice)"')


def _cfg_magento(bloque: str) -> dict:
    """JSON 'jsonConfig' del selector de opciones de la tarjeta ({} si no hay)."""
    m = re.search(r'"jsonConfig":\s*(?=\{"attributes")', bloque)      # la tarjeta tiene otro "jsonConfig" (colores) antes
    if not m:
        return {}
    try:
        return json.JSONDecoder().raw_decode(bloque, m.end())[0]
    except ValueError:
        return {}


def items_de_magento(html_text: str, site: dict, seccion: dict) -> list[ItemRopa]:
    out: list[ItemRopa] = []
    pos = [m.start() for m in _RE_MG_ITEM.finditer(html_text)] + [len(html_text)]
    for i in range(len(pos) - 1):
        b = html_text[pos[i]:pos[i + 1]]
        mu, mn = _RE_MG_URL.search(b), _RE_MG_NOMBRE.search(b)
        if not (mu and mn):
            continue
        cfg = _cfg_magento(b)
        talles: list = []
        actual = lista = 0.0
        attrs = list((cfg.get("attributes") or {}).values())
        if attrs:
            attr = next((a for a in attrs if "talle" in str(a.get("code", "")).lower()), attrs[0])
            hijos = {pid for o in attr.get("options", []) for pid in o.get("products", [])}      # solo los talles disponibles
            if not hijos:
                continue                                                                         # sin stock
            talles = sorted({str(o.get("label", "")).strip() for o in attr.get("options", []) if o.get("products")}, key=_orden_talle)
            precios = [cfg["optionPrices"][h] for h in hijos if h in (cfg.get("optionPrices") or {})]
            if precios:
                p0 = min(precios, key=lambda p: p["finalPrice"]["amount"])
                actual, lista = float(p0["finalPrice"]["amount"]), float((p0.get("oldPrice") or {}).get("amount") or 0)
        if actual <= 0:                                                                          # producto sin variantes: caja de precio
            pr = {tipo: float(v) for v, tipo in _RE_MG_PRECIO.findall(b)}
            actual, lista = pr.get("finalPrice", 0.0), pr.get("oldPrice", 0.0)
        if actual <= 0:
            continue
        lista = max(lista, actual)
        nombre = _html.unescape(mn.group(1)).strip()
        mi, mid = _RE_MG_IMG.search(b), _RE_MG_ID.search(b)
        categoria = "calzado" if _RE_CALZADO.search(nombre) else "accesorios" if _RE_ACCESORIOS.search(nombre) else "ropa"
        out.append(ItemRopa(
            tienda=site["name"], marca=site.get("marca", ""), nombre=nombre, referencia=(mid.group(1) if mid else mu.group(1)),
            genero=_genero_de_texto(nombre) or seccion["genero"], categoria=categoria, tipo=_cap(nombre.split()[0].lower()), color="",
            talles=talles, precio_lista=lista, precio_actual=actual, descuento_pct=_dto(lista, actual), url=mu.group(1),
            imagen=_html.unescape(mi.group(1)) if mi else "", seccion=seccion["path"]))
    return out


def scrap_magento(sesion, site: dict, seccion: dict, max_paginas: int) -> list[ItemRopa]:
    vistos: dict = {}
    for pagina in range(1, min(max_paginas, site.get("pag_max", 60)) + 1):
        url = f"https://{site['host']}{seccion['path']}?p={pagina}"
        for intento in range(1, 4):
            time.sleep(random.uniform(2.5, 4.5) * _RITMO["factor"])
            r = sesion.get(url, timeout=60, headers={"Accept": "text/html,application/xhtml+xml,*/*"})
            if r.status_code == 200:
                _ritmo_exito()
                break
            _ritmo_bloqueo()
            log.warning("  %s respondio %s (intento %d/3)", url, r.status_code, intento)
            time.sleep(30 * intento)
        else:
            raise Bloqueado(url)
        tarjetas = len(_RE_MG_ITEM.findall(r.text))
        items = items_de_magento(r.text, site, seccion)
        nuevos = 0
        for it in items:
            if it.clave not in vistos:
                vistos[it.clave] = it
                nuevos += 1
        log.info("  pagina %d: %d tarjetas, %d con stock (%d nuevas)", pagina, tarjetas, len(items), nuevos)
        if tarjetas == 0 or (nuevos == 0 and items):
            break        # Magento repite la ultima pagina si se pide una mas alla del final
    return list(vistos.values())


# --------------------------------------------------------------------------- #
# Adaptador "prestashop": Brooksfield (PrestaShop, tema iqit; HTML del servidor, HTTP simple). El listado
# /{id}-{categoria}?page=N trae 24 tarjetas (`article.js-product-miniature`) con precio (`.product-price`) y, si hay oferta,
# precio anterior (`.regular-price`). Pedir una pagina mas alla del final repite la ultima: se corta al no haber productos
# nuevos. El listado no trae talles: se leen de la ficha solo de los productos con descuento.
# AVISO: al 2026-11-01 ningun producto tenia precio tachado, asi que la lectura de ofertas no esta verificada con datos reales.
# --------------------------------------------------------------------------- #
def _talles_prestashop(sesion, url: str) -> list:
    try:
        time.sleep(random.uniform(1.5, 2.5) * _RITMO["factor"])
        r = sesion.get(url.split("#")[0], timeout=40, headers={"Accept": "text/html"})
    except Exception as e:  # noqa: BLE001
        log.warning("  ficha sin leer %s: %s", url, str(e)[:80])
        return []
    if r.status_code != 200:
        return []
    from bs4 import BeautifulSoup
    soup = BeautifulSoup(r.text, "html.parser")
    for grupo in soup.select(".product-variants-item"):
        if "talle" in (grupo.get_text(" ")[:30]).lower():
            return sorted({(i.get("title") or "").strip() for i in grupo.select("input.input-radio") if i.get("title")}, key=_orden_talle)
    return []


def scrap_prestashop(sesion, site: dict, seccion: dict, max_paginas: int) -> list[ItemRopa]:
    from bs4 import BeautifulSoup
    vistos: dict = {}
    for pagina in range(1, min(max_paginas, site.get("pag_max", 40)) + 1):
        url = f"https://{site['host']}{seccion['path']}?page={pagina}"
        for intento in range(1, 4):
            time.sleep(random.uniform(2.5, 4.5) * _RITMO["factor"])
            r = sesion.get(url, timeout=40, headers={"Accept": "text/html,*/*"})
            if r.status_code == 200:
                _ritmo_exito()
                break
            _ritmo_bloqueo()
            log.warning("  %s respondio %s (intento %d/3)", url, r.status_code, intento)
            time.sleep(30 * intento)
        else:
            raise Bloqueado(url)
        tarjetas = BeautifulSoup(r.text, "html.parser").select("article.js-product-miniature")
        nuevos = 0
        for t in tarjetas:
            a = t.select_one(".product-title a")
            pid = t.get("data-id-product")
            if not a or not pid or pid in vistos:
                continue
            nuevos += 1
            actual = _monto((t.select_one(".product-price") or t).get_text()) if t.select_one(".product-price") else 0.0
            lista = _monto(t.select_one(".regular-price").get_text()) if t.select_one(".regular-price") else 0.0
            nombre = " ".join(a.get_text().split())
            if actual <= 0:
                continue
            lista = max(lista, actual)
            img = t.select_one("img[data-full-size-image-url], img[data-src]")
            imagen = ((img.get("data-full-size-image-url") or img.get("data-src")) if img else "") or ""
            ref = t.select_one(".product-reference")
            cat = " ".join((t.select_one(".product-category-name") or t).get_text().split()) if t.select_one(".product-category-name") else ""
            categoria = "calzado" if _RE_CALZADO.search(nombre) else "accesorios" if _RE_ACCESORIOS.search(nombre + " " + cat) else "ropa"
            vistos[pid] = ItemRopa(
                tienda=site["name"], marca=site.get("marca", ""), nombre=nombre, referencia=(ref.get_text().strip() if ref else pid),
                genero=seccion["genero"], categoria=categoria, tipo=_cap(cat or nombre.split()[0].lower()), color="",
                precio_lista=lista, precio_actual=actual, descuento_pct=_dto(lista, actual), url=(a.get("href") or "").split("#")[0],
                imagen=imagen, seccion=seccion["path"])
        log.info("  pagina %d: %d tarjetas (%d nuevas)", pagina, len(tarjetas), nuevos)
        if not tarjetas or nuevos == 0:
            break
    con = [it for it in vistos.values() if it.descuento_pct > 0]
    for it in con:
        it.talles = _talles_prestashop(sesion, it.url)
    return list(vistos.values())


# --------------------------------------------------------------------------- #
# Tipos de producto unificados entre tiendas
# --------------------------------------------------------------------------- #
# Cada tienda publica el tipo a su manera ("Remera", "Remeras y polos", "Remeras"; "Calzado" / "Zapatillas";
# "Shorts-bermudas"...). Para que el filtro "Prenda / tipo" funcione igual con todas las tiendas se asigna un tipo
# canonico (y la categoria que le corresponde) mirando el nombre y el tipo crudo. El primer patron que coincide gana:
# el orden importa (p. ej. "traje de baño" antes que "traje").
_TIPOS = [
    (r"\b(edt|edp|perfume|eau|fragancia|colonia|parfum|desodorante|antitranspirante)\b", "Perfumería", "accesorios"),
    (r"bicicleta|shimano|knog|vairo|\bcasco\b|\bfreno\b|herramienta", "Ciclismo", "accesorios"),
    (r"\bbotin(es)?\b", "Botines", "calzado"),
    (r"ojota|sandalia|\bslides?\b|chancl", "Sandalias y ojotas", "calzado"),
    (r"\bzuecos?\b", "Zuecos", "calzado"),
    (r"zapatill|\bzapatos?\b|borcego|mocasin|\bcalzado\b|footwear", "Zapatillas", "calzado"),
    (r"traje de ba|short de ba|malla|bikini|\bbaño\b", "Traje de baño", "ropa"),
    (r"\bjeans?\b", "Jean", "ropa"),
    (r"jogg", "Jogging", "ropa"),
    (r"\bcalzas?\b|leggin", "Calza", "ropa"),
    (r"short|bermuda", "Short / Bermuda", "ropa"),
    (r"pantal|\bchino\b|trouser|bombacha|babucha|\bcargo\b", "Pantalón", "ropa"),
    (r"musculosa", "Musculosa", "ropa"),
    (r"remera|polera|t-?shirt|\btee\b", "Remera", "ropa"),
    (r"\btops?\b|corpi|bralette|tops-deportivos|\bbody\b", "Top", "ropa"),
    (r"chomba|\bpolo\b", "Chomba", "ropa"),
    (r"camiseta", "Camiseta", "ropa"),
    (r"camis|blusa", "Camisa", "ropa"),
    (r"buzo|hoodie|canguro|zipthru|sudadera", "Buzo", "ropa"),
    (r"campera|chaqueta|parka|anorak|puffer|gaban|montgomery|cortaviento|rompevientos|jacket|\bsaco\b|abrigo|blazer|tapado|sobretodo|\bpiloto\b|poncho|trench", "Campera", "ropa"),
    (r"chaleco|\bvest\b", "Chaleco", "ropa"),
    (r"sweater|cardigan|pullover|tejido", "Sweater", "ropa"),
    (r"vestido", "Vestido", "ropa"),
    (r"pollera|falda", "Pollera", "ropa"),
    (r"conjunto|\bambo\b|pijama|\btraje\b", "Conjunto", "ropa"),
    (r"\bmedias?\b|soquete", "Medias", "ropa"),
    (r"boxer|bóxer|\bslip\b|ropa interior|calzoncillo", "Ropa interior", "ropa"),
    (r"gorra|gorro|beanie|piluso|vincha|sombrero|\bcap\b", "Gorras y gorros", "accesorios"),
    (r"mochila|bolso|bolsa|riñonera|rinonera|bandolera|valija|matera|necesser|neceser|totebag|canasta|morral", "Mochilas y bolsos", "accesorios"),
    (r"billetera|tarjetero|cartera|monedero", "Billeteras", "accesorios"),
    (r"\bcintos?\b|cintur", "Cinturones", "accesorios"),
    (r"pelota|balón|\bbalon\b", "Pelotas", "accesorios"),
    (r"guante", "Guantes", "accesorios"),
    (r"lentes|gafas|anteojo", "Lentes", "accesorios"),
    (r"reloj", "Relojes", "accesorios"),
    (r"bufanda|corbata|pañuelo|panuelo|\bcuello\b|chalina|pashmina", "Bufandas y pañuelos", "accesorios"),
    (r"canillera|rodillera|muñequera|munequera|\bgrip\b|\bpalo\b|soporte|protector", "Protecciones y deporte", "accesorios"),
]
_TIPOS_RE = [(re.compile(rx, re.I), tipo, cat) for rx, tipo, cat in _TIPOS]


def normalizar_tipo(nombre: str, tipo_crudo: str, categoria: str) -> tuple[str, str]:
    """(tipo canonico, categoria) a partir del nombre, el tipo publicado por la tienda y la categoria detectada."""
    texto = f"{tipo_crudo or ''} {nombre or ''}"
    for rx, tipo, cat in _TIPOS_RE:
        if rx.search(texto):
            return tipo, cat
    if categoria == "calzado":
        return "Zapatillas", "calzado"
    if categoria == "accesorios":
        return "Otros accesorios", "accesorios"
    return "Otras prendas", categoria or "ropa"


# Deportes: se usan los nombres de Nike como canonicos. Se unifica lo que publica cada tienda y, si no trae deporte,
# se deduce del nombre (sin dato = NULL = el filtro "Deporte" no lo excluye).
_DISCIPLINA_MAP = {"training": "Entrenamiento", "basketball": "Basquet", "básquet": "Basquet", "tenis": "Tenis y Pádel",
                   "futbol": "Fútbol", "casual": "Moda", "inspiracion-deportiva": "Moda", "urbano": "Moda", "deportivo": ""}
_DISCIPLINA_NOMBRE = [(re.compile(rx, re.I), d) for rx, d in [
    (r"f[úu]tbol|\bbotines\b", "Fútbol"), (r"running|\brun\b|correr", "Running"), (r"tenis|p[áa]del", "Tenis y Pádel"),
    (r"b[áa]squet|basket|\bnba\b", "Basquet"), (r"nataci|piscina|traje de ba|malla", "Natación"),
    (r"training|entrenamiento|\bgym\b|crossfit", "Entrenamiento"), (r"trekking|outdoor|monta[ñn]a", "Outdoor")]]


def normalizar_disciplina(nombre: str, disciplina: str, tipo: str) -> str:
    d = (disciplina or "").strip()
    d = _DISCIPLINA_MAP.get(d.lower(), d)
    if d:
        return d
    if tipo == "Ciclismo":
        return "Ciclismo"
    for rx, nombre_disc in _DISCIPLINA_NOMBRE:
        if rx.search(nombre or ""):
            return nombre_disc
    return ""


_RE_EXCLUIR = re.compile(r"gift ?card|tarjeta de regalo|^null$", re.I)     # no son productos de ropa


def unificar_tipos(items: list) -> None:
    """Tipo/categoria y deporte unificados entre tiendas (ver normalizar_tipo / normalizar_disciplina)."""
    for it in items:
        it.tipo, it.categoria = normalizar_tipo(it.nombre, it.tipo, it.categoria)
        it.disciplina = normalizar_disciplina(it.nombre, it.disciplina, it.tipo)
        it.talles = sin_ceros(it.talles)
        if it.categoria == "calzado":
            it.talles = expandir_rangos(it.talles)


# --------------------------------------------------------------------------- #
# Adaptador "puma": ar.puma.com (Salesforce Composable Storefront / PWA Kit tras Cloudflare). Cada pagina del listado
# /puma-sale?page=N&limit=32 se abre en el navegador y los productos se leen del estado de la app embebido en
# <script id="mobify-data"> (hits con precio de lista, precio de venta, % y colores). No trae talles ni genero:
# el genero sale del nombre ("... para hombre") y los talles quedan vacios (la tienda los muestra en la ficha).
# --------------------------------------------------------------------------- #
PUMA_LIMITE = 32


def _hits_puma(estado) -> tuple[list[dict], int]:
    """(hits, total) del listado dentro del estado de la app: la consulta de busqueda con mas hits."""
    mejor: list[dict] = []
    total = 0

    def recorrer(o):
        nonlocal mejor, total
        if isinstance(o, dict):
            hits = o.get("hits")
            if isinstance(hits, list) and hits and isinstance(hits[0], dict) and "productId" in hits[0] and len(hits) > len(mejor):
                mejor, total = hits, int(o.get("total") or 0)
            for v in o.values():
                recorrer(v)
        elif isinstance(o, list):
            for v in o:
                recorrer(v)

    recorrer(estado)
    return mejor, total


def _item_puma(h: dict, site: dict, seccion: dict) -> Optional[ItemRopa]:
    try:
        actual = float(h.get("c_salePrice") or h.get("price") or 0)
        lista = float(h.get("c_listPrice") or actual)
    except (TypeError, ValueError):
        return None
    nombre = " ".join((h.get("productName") or "").split())
    if actual <= 0 or not nombre:
        return None
    lista = max(lista, actual)
    sw = h.get("c_colorSwatches") or []
    color = next((s.get("name") for s in sw if s.get("selected")), "") or (sw[0].get("name") if sw else "")
    imgs = (h.get("imageGroups") or [{}])[0].get("images") or []
    link = h.get("c_canonicalUrl") or ""
    gen = _genero_de_texto(re.findall(r"para (hombre|mujer|ni[ñn]os?|ni[ñn]as?|beb[ée]s?|unisex)", nombre, re.I)) or _genero_de_texto(nombre) or "unisex"
    categoria = "calzado" if _RE_CALZADO.search(nombre) else "accesorios" if _RE_ACCESORIOS.search(nombre) else "ropa"
    return ItemRopa(
        tienda=site["name"], marca="Puma", nombre=nombre, referencia=str(h.get("c_styleNumber") or h.get("productId") or ""),
        genero=gen, categoria=categoria, tipo=_cap(nombre.split()[0].lower()), color=_cap((color or "").split("-")[0].strip()),
        precio_lista=lista, precio_actual=actual, descuento_pct=_dto(lista, actual),
        url=(f"https://{site['host']}{link}" if link.startswith("/") else link),
        imagen=(imgs[0].get("link") if imgs else "") or "", seccion=seccion["path"])


def scrap_puma(page, site: dict, seccion: dict, max_paginas: int) -> list[ItemRopa]:
    vistos: dict = {}
    for pagina in range(1, min(max_paginas, site.get("pag_max", 60)) + 1):
        url = f"https://{site['host']}{seccion['path']}?page={pagina}&limit={PUMA_LIMITE}"
        hits, total = [], 0
        for intento in range(1, 4):
            time.sleep(random.uniform(4.0, 7.0) * _RITMO["factor"])
            try:
                resp = page.goto(url, wait_until="domcontentloaded", timeout=60000)
                if _es_bloqueo(page.title(), resp.status if resp else 0):
                    raise RuntimeError("bloqueo")
                page.wait_for_selector("#mobify-data", state="attached", timeout=25000)
                estado = json.loads(page.evaluate("() => document.getElementById('mobify-data').textContent"))
                hits, total = _hits_puma(estado)
                _ritmo_exito()
                break
            except Exception as e:  # noqa: BLE001
                _ritmo_bloqueo()
                log.warning("  error/bloqueo en %s: %s (intento %d/3)", url, str(e)[:100], intento)
                time.sleep(40 * intento)
        else:
            raise Bloqueado(url)
        nuevos = 0
        for h in hits:
            it = _item_puma(h, site, seccion)
            if it and it.clave not in vistos:
                vistos[it.clave] = it
                nuevos += 1
        log.info("  pagina %d: %d productos (%d nuevos) de %s", pagina, len(hits), nuevos, total or "?")
        if len(hits) < PUMA_LIMITE or nuevos == 0 or (total and pagina * PUMA_LIMITE >= total):
            break
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


def _guardar_checkpoint(ruta: str, fecha: datetime, todos: dict, reporte: list) -> None:
    """Vuelca lo leido hasta ahora para poder terminar la corrida con --reanudar si se corta (el guardado en MySQL es al final)."""
    try:
        os.makedirs(os.path.dirname(os.path.abspath(ruta)), exist_ok=True)
        with open(ruta, "w", encoding="utf-8") as f:
            json.dump({"fecha": fecha.isoformat(), "reporte": reporte, "items": [asdict(i) for i in todos.values()]}, f, ensure_ascii=False)
    except Exception as e:  # noqa: BLE001
        log.warning("No pude escribir el checkpoint: %s", e)


def renormalizar_tipos_en_bd() -> int:
    """Aplica normalizar_tipo a los productos de ropa ya guardados (datos derivados: se pueden recalcular)."""
    import pymysql
    conn = pymysql.connect(charset="utf8mb4", autocommit=False, **DB_CONFIG)
    try:
        with conn.cursor() as cur:
            cur.execute("SELECT p.id, p.nombre, p.tipo, p.categoria, p.disciplina, p.talles, p.genero FROM productos p JOIN tiendas t ON t.id = p.tienda_id WHERE t.rubro = %s", (RUBRO,))
            cambios = []
            for pid, nombre, tipo, cat, disc, talles, genero in cur.fetchall():
                nt, nc = normalizar_tipo(nombre, tipo, cat)
                nd = normalizar_disciplina(nombre, disc, nt) or None
                lista = sin_ceros([x.strip() for x in (talles or "").split(",") if x.strip()])
                if nc == "calzado":
                    lista = expandir_rangos(lista)
                ntalles = ", ".join(lista)[:255] or None
                nnorm = normalizar_talles(genero, nc, lista)[:255] or None
                cambios.append((nt, nc, nd, ntalles, nnorm, pid))
            cur.executemany("UPDATE productos SET tipo = %s, categoria = %s, disciplina = %s, talles = %s, talles_norm = %s WHERE id = %s", cambios)
        conn.commit()
        log.info("Tipos, deportes y talles renormalizados: %d productos revisados.", len(cambios))
        return 0
    except Exception as e:  # noqa: BLE001
        conn.rollback()
        log.error("Error renormalizando (rollback): %s", e)
        return 4
    finally:
        conn.close()


def guardar_en_mysql(items: list, fecha_hora: datetime, reporte: list, estado_inicial: str = "completa") -> bool:
    """Inserta la corrida (rubro ropa), hace UPSERT de tiendas/productos y agrega las observaciones.
    Guarda TODOS los productos vistos: con descuento -> tipo 'rebaja'; sin descuento -> 'ninguno' (el
    dashboard solo muestra 'rebaja', pero asi se conserva el catalogo y el historial de precios).
    Es idempotente por (rubro, fecha_hora): se la llama una vez por tienda con estado_inicial='en_curso' y la corrida
    se cierra al final con cerrar_corrida(). El estado solo se fija al CREAR la corrida; si ya existe no se toca."""
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
            cur.execute("SHOW COLUMNS FROM corridas LIKE 'estado'")
            tiene_estado = cur.fetchone() is not None          # migracion 2026_11_01: sin ella se guarda como antes (todo a la vez)
            cur.execute(
                "INSERT INTO corridas (rubro, fecha_hora, total_productos, min_descuento, max_descuento" + (", estado" if tiene_estado else "") + ") "
                "VALUES (%s,%s,%s,%s,%s" + (",%s" if tiene_estado else "") + ") ON DUPLICATE KEY UPDATE total_productos=VALUES(total_productos), "
                "min_descuento=VALUES(min_descuento), max_descuento=VALUES(max_descuento), id=LAST_INSERT_ID(id)",
                (RUBRO, fecha_hora, len(items), round(min(pcts), 2) if pcts else None, round(max(pcts), 2) if pcts else None)
                + ((estado_inicial,) if tiene_estado else ()))
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

            fuente_de = {s["name"]: {"vtex_api": "vtex_api", "kevingston": "kevingston_api", "montagne": "html", "wrangler": "html", "sfcc_dexter": "sfcc_html", "puma": "puma_state", "tiendanube": "tiendanube", "magento": "magento_html", "prestashop": "html"}.get(s["platform"], "vtex_state") for s in SITES}
            filas, vistos = [], set()
            for it in items:
                pid = prod_id[(it.tienda, (it.referencia or it.url).strip().lower()[:255])]
                if pid in vistos:
                    continue
                vistos.add(pid)
                filas.append((corrida_id, pid, round(it.precio_lista, 2), round(it.precio_actual, 2), it.descuento_pct,
                              fuente_de.get(it.tienda, "vtex_state"), "rebaja" if it.descuento_pct > 0 else "ninguno"))
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
                    [(corrida_id, r["tienda"], r.get("plataforma", "vtex_io"), r["total"], r["con"], r["estado"], None, r["segundos"]) for r in reporte])
        conn.commit()
        log.info("MySQL: corrida #%d (ropa) guardada: %d observaciones, %d productos.", corrida_id, len(filas), len(prod_id))
        return True
    except Exception as e:  # noqa: BLE001
        conn.rollback()
        log.error("Error guardando en MySQL (rollback): %s", e)
        return False
    finally:
        conn.close()


def cerrar_corrida(fecha_hora: datetime, estado: str) -> bool:
    """Fija el estado final de la corrida: 'completa' (la lee el sitio) o 'incompleta' (no paso el control de calidad)."""
    try:
        import pymysql
        conn = pymysql.connect(charset="utf8mb4", autocommit=True, **DB_CONFIG)
    except Exception as e:  # noqa: BLE001
        log.error("No pude conectar a MySQL para cerrar la corrida (%s).", e)
        return False
    try:
        with conn.cursor() as cur:
            cur.execute("SHOW COLUMNS FROM corridas LIKE 'estado'")
            if not cur.fetchone():
                return True                                  # sin la migracion no hay estado: la corrida ya es valida
            cur.execute("UPDATE corridas SET estado=%s WHERE rubro=%s AND fecha_hora=%s", (estado, RUBRO, fecha_hora))
        log.info("Corrida %s (ropa) marcada '%s'.", fecha_hora, estado)
        return True
    except Exception as e:  # noqa: BLE001
        log.error("Error cerrando la corrida: %s", e)
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
    ap.add_argument("--reanudar", action="store_true", help="guardar en MySQL lo ya leido por una corrida cortada (excel_ropa/checkpoint_ropa.json), sin volver a scrapear")
    ap.add_argument("--renormalizar-tipos", action="store_true", help="recalcula tipo/categoria de los productos de ropa YA guardados (sin scrapear)")
    ap.add_argument("--json", metavar="RUTA", help="ademas volcar todos los items a JSON")
    args = ap.parse_args()

    if args.renormalizar_tipos:
        return renormalizar_tipos_en_bd()
    ahora = datetime.fromisoformat(args.fecha) if args.fecha else datetime.now()
    sitios = [s for s in SITES if (s["name"] in args.solo if args.solo else s.get("activo", True))]
    if not sitios:
        log.error("Ninguna tienda coincide con --solo %s", args.solo)
        return 2

    todos: dict = {}
    reporte: list = []
    usar_ckpt = not (args.no_db or args.fecha or args.solo or args.seccion)      # solo en corridas completas
    # Guardado tienda por tienda: la corrida queda 'en_curso' (invisible para el sitio) hasta cerrarla al final.
    # Con --fecha (completar una corrida existente) o --reanudar se guarda todo al final, como antes.
    guardar_inc = not (args.no_db or args.fecha or args.reanudar)
    inc_ok = True
    ckpt = os.path.join(os.path.dirname(os.path.abspath(__file__)), "excel_ropa", "checkpoint_ropa.json")
    if args.reanudar:
        with open(ckpt, encoding="utf-8") as f:
            cp = json.load(f)
        ahora = datetime.fromisoformat(cp["fecha"])
        reporte = cp["reporte"]
        todos = {(d["tienda"], d["referencia"] or d["url"]): ItemRopa(**d) for d in cp["items"]}
        log.info("Reanudando desde %s: %d productos, %d secciones ya leidas (corrida %s).", ckpt, len(todos), len(reporte), ahora)
    else:
        PLAT_BROWSER = ("vtex_io", "kevingston", "sfcc_dexter", "puma")
        necesita_browser = any(s["platform"] in PLAT_BROWSER for s in sitios)
        if necesita_browser:
            try:
                from playwright.sync_api import sync_playwright
            except ImportError:
                log.error("Falta Playwright: pip install playwright && playwright install chromium")
                return 2
        import contextlib
        import requests

        with contextlib.ExitStack() as stack:
            ctx = None
            if necesita_browser:
                browser = stack.enter_context(sync_playwright()).chromium.launch(headless=not args.headful)
                stack.callback(browser.close)
                ctx = browser.new_context(user_agent=USER_AGENT, locale="es-AR")
                # no descargar imagenes/fuentes/media: menos carga para el sitio y para nosotros
                ctx.route("**/*", lambda r: r.abort() if r.request.resource_type in ("image", "media", "font") else r.continue_())
            sesion = requests.Session()
            sesion.headers.update({"User-Agent": USER_AGENT, "Accept": "application/json", "Accept-Language": "es-AR,es;q=0.9"})
            for site in sitios:
                secciones = site["secciones"][args.modo]
                if args.seccion:
                    secciones = [s for s in secciones if any(t in s["path"] for t in args.seccion)]
                items_tienda: list = []
                rep_ini = len(reporte)
                for sec in secciones:
                    log.info("== %s %s ==", site["name"], sec["path"])
                    t0 = time.time()
                    nombre_rep = f"{site['name']}_{sec.get('etiqueta') or (sec['genero'] + '_' + sec['categoria'])}"
                    page = ctx.new_page() if site["platform"] in PLAT_BROWSER else None
                    try:
                        if site["platform"] == "kevingston":
                            items = scrap_kevingston(page, site, sec, args.max_paginas)
                        elif site["platform"] == "puma":
                            items = scrap_puma(page, site, sec, args.max_paginas)
                        elif site["platform"] == "sfcc_dexter":
                            items = scrap_dexter(page, site, sec, args.max_paginas)
                        elif site["platform"] == "tiendanube":
                            items = scrap_tiendanube_ropa(sesion, site, sec, args.max_paginas)
                        elif site["platform"] == "wrangler":
                            items = scrap_wrangler(sesion, site, sec, args.max_paginas)
                        elif site["platform"] == "montagne":
                            items = scrap_montagne(sesion, site, sec, args.max_paginas)
                        elif site["platform"] == "magento":
                            items = scrap_magento(sesion, site, sec, args.max_paginas)
                        elif site["platform"] == "prestashop":
                            items = scrap_prestashop(sesion, site, sec, args.max_paginas)
                        elif page is not None:
                            items = scrap_seccion(page, site, sec, args.max_paginas)
                        else:
                            items = scrap_seccion_api(sesion, site, sec, args.max_paginas)
                        items = [i for i in items if not _RE_EXCLUIR.search(i.nombre)]
                        unificar_tipos(items)
                        con = sum(1 for i in items if i.descuento_pct > 0)
                        for it in items:
                            if it.clave not in todos:
                                todos[it.clave] = it
                                items_tienda.append(it)
                        log.info("  -> %d productos (%d con descuento)", len(items), con)
                        estado = "ok" if items else "vacio"
                    except Bloqueado as e:
                        log.error("  BLOQUEADO: %s (dejo la seccion y sigo)", e)
                        items, con, estado = [], 0, "bloqueado"
                    except Exception as e:  # noqa: BLE001
                        log.error("  fallo: %s", e)
                        items, con, estado = [], 0, "error"
                    finally:
                        if page is not None:
                            page.close()
                    reporte.append({"tienda": nombre_rep, "plataforma": site["platform"], "total": len(items), "con": con,
                                    "estado": estado, "segundos": round(time.time() - t0, 1)})
                    time.sleep(random.uniform(8, 14))
                    if usar_ckpt:
                        _guardar_checkpoint(ckpt, ahora, todos, reporte)
                if guardar_inc and len(reporte) > rep_ini:
                    log.info("== %s completa: guardo la tienda en MySQL (%d productos) ==", site["name"], len(items_tienda))
                    if not guardar_en_mysql(items_tienda, ahora, reporte[rep_ini:], "en_curso"):
                        inc_ok = False
                        log.warning("No pude guardar %s ahora; se reintenta con todo al final de la corrida.", site["name"])

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
            log.error("CORRIDA INCOMPLETA: %d de %d secciones bloqueadas/con error (minimo %.0f%% sin fallar). No la publico para no "
                      "tapar el ultimo snapshot bueno (queda 'incompleta' en la base). Usa --force-db para publicarla igual.",
                      len(fallidas), len(reporte), args.umbral_secciones * 100)
            if guardar_inc and inc_ok:
                cerrar_corrida(ahora, "incompleta")
            return 3
        if guardar_inc and inc_ok:
            if not cerrar_corrida(ahora, "completa"):
                return 4
        else:
            # Sin guardado por tienda (--fecha / --reanudar) o si alguna tienda no se pudo guardar: se vuelca todo (idempotente)
            if not guardar_en_mysql(items, ahora, reporte, "completa" if args.fecha else "en_curso"):
                return 4
            if not args.fecha and not cerrar_corrida(ahora, "completa"):
                return 4
        if os.path.exists(ckpt):
            os.remove(ckpt)
    return 0 if items else 1


if __name__ == "__main__":
    sys.exit(main())
