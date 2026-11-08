#!/usr/bin/env python3
"""
Scraper de DESCUENTOS DE TECNOLOGIA (PC y electro, rubro "tecnologia").  Hermano de descuentos_master.py (perfumes)
y descuentos_ropa.py (ropa).

Tiendas (un adaptador por tienda; ver ai-context/tecno-01-tiendas-y-precios.md):
  * compragamer  -> JSON estatico que la propia pagina descarga (https://static.compragamer.com/productos): el catalogo
                    completo en UN pedido (mas 3 JSON chicos de marcas/categorias). Trae precio y precio anterior.
  * maximus      -> el WebMethod de su propio listado (wfmWebSite2.aspx/wsNRW_Script, el mismo que usa la pagina), con
                    el identificador de sesion que el sitio publica en su HTML (hidWSId). Una categoria por vez.
  * fullh4rd     -> HTML del servidor, /productos?page=N (12 por pagina). No publica descuentos: la "rebaja" se calcula
                    contra el precio maximo de los ultimos 30 dias que ya guardamos de ese producto.
  * gezatek      -> HTML propio, /<categoria>/?page=N (48 por pagina); precio final y precio tachado en promocion.
  * wiztech      -> un JSON que pide la propia pagina (ProductosController.php?action=getProductosForCatalogo).
  * armytech     -> PrestaShop, /2-productos?page=N (48 por pagina); precio actual y precio anterior.
  * gamingcity   -> PHP propio con URLs amigables (<slug>--prod--<id>), paginado por offset de 25.

Precio de referencia: SIEMPRE el precio de contado/transferencia/debito (el mas bajo de cada tienda), para que las tiendas
sean comparables. precio_lista = ese mismo precio ANTES de la rebaja; si no hay rebaja, lista = actual (0 %).

Uso:
    python descuentos_tecno.py --no-db                      # prueba sin tocar la base
    python descuentos_tecno.py --solo compragamer --no-db   # una tienda
    python descuentos_tecno.py                              # corrida normal (guarda en MySQL, tienda por tienda)
    python descuentos_tecno.py --solo maximus --fecha 2026-11-08T13:00:00   # completar una corrida ya guardada

Sin paralelismo: una tienda a la vez y un pedido a la vez, con pausas aleatorias y espera creciente ante 403/429/5xx.
"""
from __future__ import annotations

import argparse
import html as _html
import json
import logging
import os
import random
import re
import sys
import time
import unicodedata
from dataclasses import dataclass, asdict
from datetime import datetime, timedelta

logging.basicConfig(level=logging.INFO, format="%(asctime)s  %(levelname)-8s %(message)s", datefmt="%H:%M:%S")
log = logging.getLogger("tecno")

USER_AGENT = ("Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) "
              "Chrome/124.0.0.0 Safari/537.36")
RUBRO = "tecnologia"

SITES = [
    {"name": "compragamer", "platform": "cg_json", "host": "compragamer.com"},
    {"name": "maximus", "platform": "maximus_ws", "host": "www.maximus.com.ar", "pag_max": 40},
    {"name": "fullh4rd", "platform": "fh_html", "host": "fullh4rd.com.ar", "pag_max": 220, "historial_dias": 30},
    # Agregadas el 2026-11-09 (ver ai-context/tecno-01):
    {"name": "gezatek", "platform": "gz_html", "host": "gezatek.com.ar", "pag_max": 40,
     "categorias": ["almacenamiento", "combos-actualizacion", "electro", "gabinetes-y-fuentes", "memorias-portatiles", "memorias-ram",
                    "motherboards", "notebooks-y-accesorios", "otros", "pc-armadas", "perifericos", "placas-de-video", "procesadores",
                    "redes", "refrigeracion"]},
    {"name": "wiztech", "platform": "wt_json", "host": "wiztech.com.ar", "historial_dias": 30},
    {"name": "armytech", "platform": "at_html", "host": "www.armytech.com.ar", "pag_max": 40},
    # categorias raiz de Gamingcity (incluyen a sus hijas); el listado se recorre siguiendo "Siguiente" (25 por pagina)
    {"name": "gamingcity", "platform": "gc_html", "host": "www.gamingcity.com.ar", "pag_max": 60,
     "categorias": ["computadoras--prod--162", "componentes--prod--166", "perifericos--prod--178", "notebooks--prod--50", "monitores--prod--32",
                    "conectividad--prod--6", "impresoras-e-insumos--prod--174", "consolas--prod--25", "tabletas--prod--22", "sillas--prod--57",
                    "estabilizadores-y-ups--prod--68", "camaras--prod--33", "accesorios--prod--19", "almacenamiento-extraible--prod--113",
                    "simuladores--prod--175", "inteligencia-artificial--prod--183", "hogar--prod--94"]},
    # Mexx (www.mexx.com.ar) estaba en mantenimiento el 2026-11-08 (HTTP 400, "MEXX | Mantenimiento"): agregar cuando vuelva.
]
_FUENTE = {"cg_json": "compragamer_json", "maximus_ws": "maximus_ws", "fh_html": "fullh4rd_html", "gz_html": "gezatek_html",
           "wt_json": "wiztech_json", "at_html": "armytech_html", "gc_html": "gamingcity_html"}


class Bloqueado(Exception):
    pass


@dataclass
class ItemTecno:
    tienda: str
    nombre: str
    referencia: str            # identidad estable por tienda: cg-<id> | mx-<item_id> | fh-<id>
    marca: str
    categoria: str             # categoria comun a las 3 tiendas (ver clasificar)
    tipo: str
    precio_lista: float
    precio_actual: float
    descuento_pct: float
    url: str
    imagen: str = ""
    pn: str = ""               # numero de parte del fabricante cuando la tienda lo publica (sirve para comparar entre tiendas)

    @property
    def clave(self) -> tuple:
        return (self.tienda, self.referencia)


def _dto(lista: float, actual: float) -> float:
    return round((lista - actual) / lista * 100, 2) if lista and lista > actual > 0 else 0.0


# --------------------------------------------------------------------------- #
# Ritmo adaptativo (misma idea que descuentos_ropa.py)
# --------------------------------------------------------------------------- #
_RITMO = {"factor": 1.0}
RITMO_MAX = 4.0


def _ritmo_bloqueo() -> None:
    _RITMO["factor"] = min(_RITMO["factor"] * 1.6, RITMO_MAX)


def _ritmo_exito() -> None:
    _RITMO["factor"] = max(_RITMO["factor"] * 0.92, 1.0)


def _pausa(a: float = 2.5, b: float = 4.5) -> None:
    time.sleep(random.uniform(a, b) * _RITMO["factor"])


def _pedir(sesion, metodo: str, url: str, reintentos: int = 4, **kw):
    """GET/POST con espera creciente ante 403/429/5xx (30 s x intento). Devuelve la respuesta 200 o levanta Bloqueado."""
    for intento in range(1, reintentos + 1):
        try:
            r = sesion.request(metodo, url, timeout=60, **kw)
        except Exception as e:  # noqa: BLE001  (corte de conexion, timeout...)
            log.warning("  %s fallo (%s) (intento %d/%d)", url[:90], e.__class__.__name__, intento, reintentos)
            _ritmo_bloqueo()
            time.sleep(20 * intento)
            continue
        if r.status_code == 200:
            _ritmo_exito()
            if (r.encoding or "").lower() in ("", "iso-8859-1"):       # requests asume latin-1 si el sitio no declara charset
                r.encoding = "utf-8"
            return r
        if r.status_code not in (403, 429, 500, 502, 503, 504):
            raise Bloqueado(f"{url} -> HTTP {r.status_code}")
        _ritmo_bloqueo()
        log.warning("  %s respondio %s (intento %d/%d); ritmo x%.1f", url[:90], r.status_code, intento, reintentos, _RITMO["factor"])
        time.sleep(30 * intento)
    raise Bloqueado(url)


# --------------------------------------------------------------------------- #
# Clasificacion comun: categoria, tipo y marca
# --------------------------------------------------------------------------- #
# Los nombres de producto arrancan con el tipo ("Notebook ...", "Mother ...", "MONITOR 24 ..."): se mira el comienzo del
# nombre y, si no alcanza, la categoria que publica la tienda. El primer patron que coincide gana (el orden importa).
_REGLAS_NOMBRE = [
    (r"notebook|laptop|macbook|chromebook", "Notebooks"),
    (r"tablet|ipad", "Tablets"),
    (r"televisor|smart tv|tv\b", "Televisores"),
    (r"pc armada|pc gamer|pc de escritorio|pc oficina|pc kit|pc all|computadora|supercomputadora|all in one|mini ?pc|pc\b", "PC armadas"),
    (r"combo|kit\b|pack\b", "Combos y kits"),
    (r"monitor|pantalla", "Monitores"),
    (r"proyector", "Proyectores"),
    (r"placa de red|placa wifi|placa inal|adaptador wifi|adaptador usb wifi|capturador de red|receptor bluetooth|router|access point|repetidor|extensor|mesh|deco\b|switch|antena|powerline", "Conectividad"),
    (r"placa de video|placa video|tarjeta de video|tarjeta grafica|geforce|radeon|gpu|vga\b", "Placas de video"),
    (r"procesador|microprocesador|micro\b(?! ?sd)|cpu\b", "Procesadores"),
    (r"mother|placa madre", "Motherboards"),
    (r"memoria (micro ?sd|sd\b|flash|usb)|micro ?sd|pendrive|pen drive|disco|ssd|hdd|hd\b|unidad (solida|de estado)|nvme", "Almacenamiento"),
    (r"memoria|ram\b|sodimm|dimm", "Memorias RAM"),
    (r"fuente", "Fuentes"),
    (r"gabinete|case\b", "Gabinetes"),
    (r"cooler|ventilador|fan\b|disipador|water ?cool(?:ing|er)|refrigera|pasta t|liquid|kit de ventiladores|controladora", "Refrigeración"),
    (r"silla|escritorio|extension escritorio|mesa\b|soporte|rack\b", "Sillas y muebles"),
    (r"estabilizador|ups\b", "UPS y estabilizadores"),
    (r"impresora|toner|cartucho|tinta|multifunci|cinta|scanner|escaner|plotter", "Impresoras"),
    (r"teclado|mouse|auricular|headset|parlante|speaker|barra de sonido|webcam|camara web|microfono|joystick|gamepad|control |palanca|shifter|simulador|controlador de juego|numpad|keycaps|stream deck|presentador|clutch|motor base|volante|pedalera|alfombra|pad\b|capturadora|stream|mando|lector|kit de teclado", "Periféricos"),
    (r"cable|adaptador|conversor|hdmi|displayport|hub\b|placa pci", "Cables y adaptadores"),
    (r"consola|playstation|xbox|nintendo|ps5\b|ps4\b", "Consolas y juegos"),
    (r"celular|smartphone|iphone|smartwatch|watch\b|reloj inteligente", "Celulares y wearables"),
    (r"aspiradora|licuadora|cafetera|freidora|microondas|heladera|pava\b|tostadora|batidora|plancha|purificador|aire acondicionado|lavarropas|"
     r"termotanque|hervidora|multiprocesadora|sandwichera|waflera|bazar|vaso\b|botella|valija|herramienta|taladro|calefactor|estufa", "Hogar y electro"),
    (r"camara|sensor|alarma|cerradura|timbre|domotic|enchufe inteligente|smart home|tapo", "Cámaras y smart home"),
    (r"accesorio|windows|office\b|licencia|software|alcohol|dvd|thermal pad|conectores|luz profesional|dominator|filtro|bracket|spl[i]?ter|tester|corrector|rig\b|riser|funda|mochila|robot|powerbank|power bank|cargador|bateria|pila\b", "Accesorios y otros"),
]
def _sin_tildes(txt: str) -> str:
    return unicodedata.normalize("NFKD", txt or "").encode("ascii", "ignore").decode()


_RE_OUTLET = re.compile(r"^\W*outlet\W*", re.I)
# servicios que algunas tiendas publican como "producto" (no son articulos para comparar)
_RE_EXCLUIR = re.compile(r"^\W*(armado|instalaci[oó]n|servicio t[eé]cnico)\b|no se vende suelto|^\W*art[ií]culo de prueba", re.I)
_REGLAS_NOMBRE_RE = [(re.compile(r"^\W*(?:" + rx + ")", re.I), cat) for rx, cat in _REGLAS_NOMBRE]
_CATS_SOLO_AL_INICIO = {"PC armadas", "Combos y kits", "Cables y adaptadores", "Accesorios y otros", "Periféricos", "Hogar y electro",
                        "Cámaras y smart home", "Consolas y juegos", "Celulares y wearables", "Sillas y muebles", "Tablets", "Televisores"}
_REGLAS_NOMBRE_ANY_RE = [(re.compile(r"\b(?:" + rx + ")", re.I), cat) for rx, cat in _REGLAS_NOMBRE if cat not in _CATS_SOLO_AL_INICIO]
# categoria publicada por la tienda -> categoria comun (se usa si el nombre no empieza con un tipo conocido)
_REGLAS_ETIQUETA = [
    (r"notebook|laptop", "Notebooks"), (r"tablet", "Tablets"), (r"televisor", "Televisores"), (r"monitor", "Monitores"),
    (r"placa.{0,3}de.{0,3}video|video", "Placas de video"), (r"microprocesador|procesador", "Procesadores"),
    (r"mother", "Motherboards"), (r"almacenamiento|disco|ssd", "Almacenamiento"), (r"memoria", "Memorias RAM"),
    (r"fuente", "Fuentes"), (r"gabinete", "Gabinetes"), (r"refrigera|cooler", "Refrigeración"),
    (r"conectividad|redes|router", "Conectividad"), (r"silla|escritorio|mesa", "Sillas y muebles"),
    (r"impresora", "Impresoras"), (r"ups|estabilizador", "UPS y estabilizadores"), (r"combo|kit", "Combos y kits"),
    (r"computadora|armad|pc de escritorio", "PC armadas"), (r"proyector", "Proyectores"), (r"perif|teclado|mouse|auricular", "Periféricos"),
    (r"cable|adaptador", "Cables y adaptadores"),
    (r"hogar|electro|bazar|purificador|valija|herramienta|mueble", "Hogar y electro"),
    (r"consola|simulador|juego", "Consolas y juegos"), (r"celular|smartwatch|wearable", "Celulares y wearables"),
    (r"accesorio", "Accesorios y otros"),
]
_REGLAS_ETIQUETA_RE = [(re.compile(rx, re.I), cat) for rx, cat in _REGLAS_ETIQUETA]

# tipo (subcategoria) de los periféricos y de las placas/procesadores/notebooks: igual para las 3 tiendas
_TIPOS_PERIF = [
    (r"mouse ?pad|mousepad|alfombrilla|pad gamer|\bpad\b", "Mouse pads"),
    (r"teclado|keyboard|kit de teclado", "Teclados"), (r"mouse|raton", "Mouses"),
    (r"auricular|headset|headphone|vincha|in ?ear", "Auriculares"), (r"webcam|camara web", "Webcams"),
    (r"parlante|speaker|barra de sonido|soundbar", "Parlantes"), (r"microfono|micrófono", "Micrófonos"),
    (r"joystick|gamepad|mando|control ", "Joysticks"), (r"volante|pedalera", "Volantes"),
    (r"capturadora|stream", "Streaming"), (r"lector", "Lectores"),
]
_TIPOS_PERIF_RE = [(re.compile(rx, re.I), t) for rx, t in _TIPOS_PERIF]
_ACRONIMOS = {"HDMI", "NVME", "WIFI", "RGB", "LED", "DDR4", "DDR5", "USB", "SSD", "HDD", "VGA", "FHD", "QHD", "IPS", "RAM", "GPU", "CPU", "OLED", "UPS"}


def clasificar(nombre: str, etiqueta: str = "") -> tuple[str, str]:
    """(categoria comun, tipo) de un producto a partir de su nombre y de la categoria que publica la tienda."""
    nombre = _RE_OUTLET.sub("", _sin_tildes(nombre))
    etiqueta = _sin_tildes(etiqueta or "")
    cat = ""
    for rx, c in _REGLAS_NOMBRE_RE:
        if rx.search(nombre):
            cat = c
            break
    if not cat:
        for rx, c in _REGLAS_ETIQUETA_RE:
            if rx.search(etiqueta or ""):
                cat = c
                break
    if not cat:
        # el nombre no arranca con el tipo (algunas tiendas empiezan con la marca: "Gamemax GX-1050 - Fuente 1050W"):
        # se busca la palabra de tipo en cualquier parte, salvo las reglas genericas que darian falsos positivos
        for rx, c in _REGLAS_NOMBRE_ANY_RE:
            if rx.search(nombre):
                cat = c
                break
    if not cat:
        cat = "Otros"
    tipo = ""
    if cat == "Periféricos":
        for rx, t in _TIPOS_PERIF_RE:
            if rx.search(nombre):
                tipo = t
                break
        tipo = tipo or "Otros periféricos"
    elif cat == "Placas de video":
        tipo = "GeForce (NVIDIA)" if re.search(r"geforce|nvidia|rtx|gtx", nombre, re.I) else "Radeon (AMD)" if re.search(r"radeon|\brx\b|amd", nombre, re.I) else "Intel Arc" if re.search(r"\barc\b|intel", nombre, re.I) else ""
    elif cat == "Procesadores":
        tipo = "AMD Ryzen" if re.search(r"amd|ryzen|athlon", nombre, re.I) else "Intel" if re.search(r"intel|core|pentium|celeron", nombre, re.I) else ""
    return cat, tipo


_MARCAS = [
    "Asus", "Intel", "Asrock", "Logitech", "AMD", "Redragon", "Corsair", "TP-Link", "Samsung", "Adata", "Lenovo", "SteelSeries",
    "Gigabyte", "HP", "MSI", "ID-Cooling", "Acer", "Vention", "Antec", "Team", "Glorious", "Be Quiet!", "Aula", "Razer", "KTC",
    "LG", "HyperX", "Cooler Master", "Thermaltake", "KLEVV", "Western Digital", "Viewsonic", "JBL", "Cougar", "Lyonn", "Brother",
    "TCL", "Xiaomi", "PNY", "Deepcool", "HYTE", "Zotac", "XFX", "Patriot", "Kingston", "G.Skill", "Elgato", "Seasonic", "Crucial",
    "Hyundai", "Dell", "Magnum Tech", "KingDian", "Mercusys", "Robosen", "INNO3D", "Biostar", "Lian Li", "Sandisk", "Game PRO",
    "Wesdar", "Microtec", "Segotep", "Spartan", "Pantum", "Zalman", "Timetec", "Lamborghini", "Philips", "Hikvision", "Dahua",
    "NVIDIA", "Gadnic", "Noganet", "Netmak", "Nisuta", "Trust", "Genius", "Epson", "Canon", "Sentey", "Aorus", "PowerColor",
    "Sapphire", "Palit", "EVGA", "NZXT", "Phanteks", "Noctua", "Arctic", "Hisense", "BenQ", "AOC", "Gamdias", "Sharkoon",
    "Seagate", "Toshiba", "Netac", "Lexar", "Verbatim", "D-Link", "Ubiquiti", "Mikrotik", "Apple", "Motorola", "Sony", "Genesis",
    "Fury", "Xigmatek", "Lovingcool", "Qbox", "Performance", "Coolermaster", "Zowie", "Evga", "Nox", "Teros", "Noga", "Sentey",
    "Fantech", "Hiksemi", "Kolke", "Philco", "Atma", "Kanji", "Thermalright", "Montech", "Mtek", "Gigabyte", "Gamer Pro",
    "Oneplus", "Realme", "Huawei", "Garmin", "Jbl", "Kodak", "Tcl", "Bgh", "Liliana", "Peabody", "Oster", "Moulinex", "Ariston",
]
_MARCAS_RE = []
for _m in sorted(set(_MARCAS), key=len, reverse=True):
    _pat = re.escape(_m).replace(r"\ ", r"[\s-]?").replace(r"\-", r"[\s-]?")
    _MARCAS_RE.append((re.compile(r"(?<![A-Za-z0-9])" + _pat + r"(?![A-Za-z0-9])", re.I), "Cooler Master" if _m == "Coolermaster" else _m))


def detectar_marca(nombre: str) -> str:
    """Marca que aparece primero en el nombre (los nombres arrancan con el tipo y despues la marca); en empate, la mas larga."""
    mejor = None
    for rx, m in _MARCAS_RE:
        mo = rx.search(nombre)
        if mo and (mejor is None or mo.start() < mejor[0] or (mo.start() == mejor[0] and len(mo.group(0)) > mejor[1])):
            mejor = (mo.start(), len(mo.group(0)), m)
    return mejor[2] if mejor else ""


def _bonito(nombre: str) -> str:
    """Los nombres TODO EN MAYUSCULAS (Fullh4rd) se pasan a mayuscula inicial, conservando siglas y modelos."""
    nombre = _html.unescape(re.sub(r"\s+", " ", nombre or "")).strip()
    if not nombre.isupper():
        return nombre
    out = []
    for t in nombre.split(" "):
        out.append(t if (re.search(r"\d", t) or len(t) <= 3 or t in _ACRONIMOS or not t.isalpha()) else t.capitalize())
    return " ".join(out)


def _num_ar(txt: str) -> float:
    """'$63.915,85' -> 63915.85"""
    s = re.sub(r"[^\d,\.]", "", txt or "")
    if not s:
        return 0.0
    return float(s.replace(".", "").replace(",", "."))


# --------------------------------------------------------------------------- #
# Compragamer (JSON estatico del propio sitio)
# --------------------------------------------------------------------------- #
def scrap_compragamer(sesion, site: dict, max_paginas: int) -> list[ItemTecno]:
    base = "https://static.compragamer.com"
    cab = {"Accept": "application/json"}
    productos = _pedir(sesion, "GET", f"{base}/productos", headers=cab).json()
    _pausa(1.0, 2.0)
    marcas = {m["id"]: (m.get("nombre") or "").strip() for m in _pedir(sesion, "GET", f"{base}/marcas", headers=cab).json()}
    _pausa(1.0, 2.0)
    subs = {s["id"]: s for s in _pedir(sesion, "GET", f"{base}/categorias_sub", headers=cab).json()}
    _pausa(1.0, 2.0)
    agrup = {a["id"]: (a.get("nombre") or "").strip() for a in _pedir(sesion, "GET", f"{base}/subcategoriasAgrupadores", headers=cab).json()}
    sin_marca = {"", "SIN DEFINIR", "Generica", "Genérica", "Compra Gamer", "Venta Gamer", "Newton Station"}
    out = []
    for p in productos:
        if not p.get("vendible") or (p.get("stock") or 0) <= 0:
            continue
        if p.get("visible_solo_en_ATPC") or p.get("visible_solo_en_combo"):
            continue                                            # solo se ven dentro del armador de PC / de un combo
        actual = float(p.get("precioEspecial") or 0)            # precio de efectivo/transferencia
        if actual <= 0:
            continue
        ant = float(p.get("precioEspecialAnterior") or 0)
        lista = ant if ant > actual else actual
        nombre = _bonito(p["nombre"]) + (" (Outlet)" if p.get("es_outlet") and "outlet" not in p["nombre"].lower() else "")
        sub = subs.get(p.get("id_subcategoria")) or {}
        etiqueta = agrup.get(int(sub["id_agrupador"]), "") if str(sub.get("id_agrupador", "")).isdigit() else (sub.get("nombre") or "")
        cat, tipo = clasificar(p["nombre"], etiqueta)
        marca = marcas.get(p.get("id_marca"), "")
        marca = "" if marca in sin_marca else marca
        img = (p.get("imagenes") or [{}])[0].get("nombre")
        sku = ((p.get("codigo_principal") or [""])[0] or "").replace("SKU:", "").strip()
        out.append(ItemTecno(
            tienda=site["name"], nombre=nombre, referencia=f"cg-{p['id_producto']}", marca=marca or detectar_marca(p["nombre"]),
            categoria=cat, tipo=tipo, precio_lista=lista, precio_actual=actual, descuento_pct=_dto(lista, actual),
            url=f"https://compragamer.com/producto/{re.sub(r'[^0-9A-Za-z]+', '_', p['nombre']).strip('_')}_{p['id_producto']}",
            imagen=f"https://imagenes.compragamer.com/productos/compragamer_Imganen_general_{img}-grn.jpg" if img else "", pn=sku))
    return out


# --------------------------------------------------------------------------- #
# Maximus (WebMethod del propio listado)
# --------------------------------------------------------------------------- #
_MX = "https://www.maximus.com.ar"


def scrap_maximus(sesion, site: dict, max_paginas: int) -> list[ItemTecno]:
    pagina = _pedir(sesion, "GET", f"{_MX}/Productos/Placas-De-Video/maximus.aspx?/CAT=48/SCAT=-1/M=-1/OR=1/PAGE=1/",
                    headers={"Accept": "text/html,application/xhtml+xml"})
    mo = re.search(r'id="hidWSId" value="([0-9a-f-]{36})"', pagina.text)
    if not mo:
        raise Bloqueado("Maximus: no encontre hidWSId en el HTML (cambio el sitio)")
    ws = mo.group(1)

    def listar(cat: int, pag: int) -> dict:
        params = {"ws_id": ws, "comp_id": 1, "prli_id": 17, "cust_id": -1, "page": pag, "cat_id": cat, "subcat_id": -1,
                  "brand_id": -1, "local": 0, "search": "", "order": 1, "price_min": "", "price_max": "", "wco_tV": []}
        body = {"guidWS_Id": ws, "strScriptLabel": "web.MAX.GetItemList4Search_v3_V6", "JSonParameters": json.dumps(params)}
        r = _pedir(sesion, "POST", f"{_MX}/wfmWebSite2.aspx/wsNRW_Script", json=body,
                   headers={"X-Requested-With": "XMLHttpRequest", "Accept": "application/json", "Referer": pagina.url})
        return json.loads(json.loads(r.text)["d"])["data"]

    _pausa()
    cats = [(c["cat_id"], c["cat_desc"].strip()) for c in listar(-1, 1)["categories"]]
    cats.sort(key=lambda c: c[1].upper() == "OUTLET")           # OUTLET al final: si un item esta en dos categorias gana la real
    log.info("  Maximus: %d categorias", len(cats))
    vistos: dict = {}
    for cat_id, cat_desc in cats:
        pag, total_pags = 1, 1
        while pag <= min(total_pags, max_paginas):
            _pausa()
            d = listar(cat_id, pag)
            total_pags = int(d.get("pagesTotal") or 1)
            for it in d.get("items") or []:
                if it["item_id"] in vistos:
                    continue
                vistos[it["item_id"]] = (it, cat_desc)
            pag += 1
        log.info("    %-22s %2d pag, %4d acumulados", cat_desc, total_pags, len(vistos))
    out = []
    for item_id, (it, cat_desc) in vistos.items():
        lista_tarjeta = float(it.get("prli_price_original") or 0)
        mejor = it.get("bestPriceData") or {}
        dto_pago = float(mejor.get("disc_percentage1") or 0)
        actual = float(mejor.get("disc_price") or 0) or round(lista_tarjeta * (1 - dto_pago / 100), 2)
        if actual <= 0:
            continue
        # "strikeThroughPrice" = precio de lista ANTES de la campania (Cyber Week, etc.); se lleva a la base de efectivo
        antes = float(it.get("strikeThroughPrice_original") or 0) or lista_tarjeta
        lista = round(antes * (1 - dto_pago / 100), 2)
        lista = lista if lista > actual else actual
        nombre = _bonito(it["item_desc"]) + (" (Outlet)" if it.get("item_outlet") and "outlet" not in it["item_desc"].lower() else "")
        cat, tipo = clasificar(it["item_desc"], cat_desc)
        pn = (it.get("item_code4web") or "").strip()
        out.append(ItemTecno(
            tienda=site["name"], nombre=nombre, referencia=f"mx-{item_id}", marca=detectar_marca(it["item_desc"]),
            categoria=cat, tipo=tipo, precio_lista=lista, precio_actual=actual, descuento_pct=_dto(lista, actual),
            url=f"{_MX}/Producto/{it['item_desc4link']}/ITEM={item_id}/maximus.aspx" + (f"?PN={pn}" if pn else ""),
            imagen=f"{_MX}/Temp/App_WebSite/App_PictureFiles/Items/{pn}_600.jpg" if pn else "", pn=pn))
    return out


# --------------------------------------------------------------------------- #
# Fullh4rd (HTML del servidor)
# --------------------------------------------------------------------------- #
_RE_FH_TARJETA = re.compile(r'<article class="results-card"')
_RE_FH_HREF = re.compile(r'href="(/prod/(\d+)/[^"]+)"')
_RE_FH_TITULO = re.compile(r'results-card__title-link">\s*(.*?)\s*</a>', re.S)
_RE_FH_META = re.compile(r'results-card__meta">\s*(.*?)\s*</p>', re.S)
_RE_FH_PRECIO = re.compile(r'results-card__price-current">\s*(.*?)\s*</p>', re.S)
_RE_FH_IMG = re.compile(r'<img src="(/img/productos/[^"]+)"')


def items_de_fullh4rd(html_text: str, site: dict) -> list[ItemTecno]:
    out = []
    partes = _RE_FH_TARJETA.split(html_text)[1:]
    for bloque in partes:
        mh, mt, mp = _RE_FH_HREF.search(bloque), _RE_FH_TITULO.search(bloque), _RE_FH_PRECIO.search(bloque)
        if not (mh and mt and mp):
            continue
        actual = _num_ar(re.sub(r"<[^>]+>", "", mp.group(1)))
        if actual <= 0:
            continue
        nombre_crudo = _html.unescape(re.sub(r"<[^>]+>", "", mt.group(1)))
        mm = _RE_FH_META.search(bloque)
        etiqueta = _html.unescape(re.sub(r"<[^>]+>", "", mm.group(1))).strip() if mm else ""
        cat, tipo = clasificar(nombre_crudo, etiqueta)
        mi = _RE_FH_IMG.search(bloque)
        out.append(ItemTecno(
            tienda=site["name"], nombre=_bonito(nombre_crudo), referencia=f"fh-{mh.group(2)}", marca=detectar_marca(nombre_crudo),
            categoria=cat, tipo=tipo, precio_lista=actual, precio_actual=actual, descuento_pct=0.0,
            url=f"https://{site['host']}{mh.group(1)}", imagen=f"https://{site['host']}{mi.group(1)}" if mi else ""))
    return out


def scrap_fullh4rd(sesion, site: dict, max_paginas: int) -> list[ItemTecno]:
    vistos: dict = {}
    for pagina in range(1, min(max_paginas, site.get("pag_max", 200)) + 1):
        _pausa()
        r = _pedir(sesion, "GET", f"https://{site['host']}/productos?sort=name_asc&page={pagina}",
                   headers={"Accept": "text/html,application/xhtml+xml,*/*"})
        items = items_de_fullh4rd(r.text, site)
        tarjetas = len(_RE_FH_TARJETA.findall(r.text))
        nuevos = 0
        for it in items:
            if it.clave not in vistos:
                vistos[it.clave] = it
                nuevos += 1
        if pagina % 10 == 1 or tarjetas == 0:
            log.info("  pagina %d: %d tarjetas, %d nuevas (acumulados %d)", pagina, tarjetas, nuevos, len(vistos))
        if tarjetas == 0 or nuevos == 0:
            break
    return list(vistos.values())


# --------------------------------------------------------------------------- #
# Gezatek (HTML del servidor; ?page=N, 48 por pagina)
# --------------------------------------------------------------------------- #
_RE_GZ_URL = re.compile(r'<h4 class="card-title[^>]*>\s*<a href="([^"]+)">(.*?)</a>', re.S)
_RE_GZ_STOCK = re.compile(r'<font[^>]*>\s*([^<]*stock[^<]*|[^<]*agotado[^<]*)\s*</font>', re.I)
_RE_GZ_IMG = re.compile(r'<img src="(https://[^"]+)"')
_RE_GZ_FINAL = re.compile(r'data-precio="([\d\.]+)"')
_RE_GZ_TACHADO = re.compile(r'tachado_\d">\s*\$\s*([\d\.]+)\s*<')


def items_de_gezatek(html_text: str, site: dict, etiqueta: str = "") -> list[ItemTecno]:
    """Cada tarjeta trae: nombre/URL/SKU/stock ANTES del bloque <div class="price"> y los precios dentro de el: precio final
    ("Precio Geza", de contado/transferencia, data-precio) y el tachado (precio anterior) cuando hay promocion."""
    partes = html_text.split('<div class="price">')
    out = []
    for i in range(1, len(partes)):
        ant, cur = partes[i - 1][-7000:], partes[i][:1000]     # la foto queda lejos del precio (botones, favoritos...)
        urls = list(_RE_GZ_URL.finditer(ant))
        mf = _RE_GZ_FINAL.search(cur)
        if not (urls and mf):
            continue
        mu = urls[-1]
        stock = [m.group(1).lower() for m in _RE_GZ_STOCK.finditer(ant)]
        if stock and "agotado" in stock[-1]:
            continue
        actual = float(mf.group(1))
        mt = _RE_GZ_TACHADO.search(cur)
        tachado = float(mt.group(1)) if mt else 0.0
        lista = tachado if tachado > actual else actual
        url = mu.group(1)
        idp = re.search(r"-(\d+)\.html", url)
        if actual <= 0 or not idp:
            continue
        nombre_crudo = _html.unescape(re.sub(r"<[^>]+>", "", mu.group(2))).strip()
        mi =list(_RE_GZ_IMG.finditer(ant[:mu.start()]))
        cat, tipo = clasificar(nombre_crudo, etiqueta or url.split("/")[3].replace("-", " "))
        out.append(ItemTecno(
            tienda=site["name"], nombre=_bonito(nombre_crudo), referencia=f"gz-{idp.group(1)}", marca=detectar_marca(nombre_crudo),
            categoria=cat, tipo=tipo, precio_lista=lista, precio_actual=actual, descuento_pct=_dto(lista, actual), url=url,
            imagen=mi[-1].group(1) if mi else ""))               # el SKU de Gezatek (SSD029...) es interno: no sirve para comparar
    return out


def scrap_gezatek(sesion, site: dict, max_paginas: int) -> list[ItemTecno]:
    vistos: dict = {}
    for cat in site["categorias"]:
        n_cat = 0
        for pagina in range(1, min(max_paginas, site.get("pag_max", 40)) + 1):
            _pausa()
            r = _pedir(sesion, "GET", f"https://{site['host']}/{cat}/" + (f"?page={pagina}" if pagina > 1 else ""),
                       headers={"Accept": "text/html,application/xhtml+xml,*/*"})
            items = items_de_gezatek(r.text, site, cat.replace("-", " "))
            nuevos = 0
            for it in items:
                if it.clave not in vistos:
                    vistos[it.clave] = it
                    nuevos += 1
            n_cat += nuevos
            if not items or nuevos == 0 or "rel='next'" not in r.text:
                break
        log.info("    %-26s %4d nuevos (acumulados %d)", cat, n_cat, len(vistos))
    return list(vistos.values())


# --------------------------------------------------------------------------- #
# Wiztech (SPA Angular: un JSON con todo el catalogo, el mismo que pide la pagina)
# --------------------------------------------------------------------------- #
def scrap_wiztech(sesion, site: dict, max_paginas: int) -> list[ItemTecno]:
    base = f"https://{site['host']}"
    r = _pedir(sesion, "GET", f"{base}/backend/Controller/ProductosController.php?action=getProductosForCatalogo&param=0",
               headers={"Accept": "application/json", "Referer": base + "/"})
    datos = r.json()
    if not datos.get("Ok") or not isinstance(datos.get("Result"), list):
        raise Bloqueado(f"Wiztech: respuesta inesperada ({str(datos)[:120]})")
    out = []
    for p in datos["Result"]:
        if (p.get("Cantidad") or 0) <= 0 or not p.get("HabilitadoWeb") or p.get("Consultar"):
            continue
        precio = float(p.get("Precio") or 0)
        if precio <= 1:                                         # "1" = precio a consultar
            continue
        oferta = float(p.get("PrecioOferta") or 0)
        actual = oferta if 0 < oferta < precio else precio
        comp, marca, modelo = (p.get("Componente") or "").strip(), (p.get("Marca") or "").strip(), (p.get("Modelo") or "").strip()
        nombre_crudo = f"{comp} {marca} {modelo}".strip()
        cat, tipo = clasificar(nombre_crudo, comp)
        img = (p.get("ImgSrcCollectionWeb") or [p.get("ImgSrc")] or [""])[0] or ""
        out.append(ItemTecno(
            tienda=site["name"], nombre=_bonito(nombre_crudo), referencia=f"wt-{p['IdProducto']}", marca=marca or detectar_marca(nombre_crudo),
            categoria=cat, tipo=tipo, precio_lista=precio, precio_actual=actual, descuento_pct=_dto(precio, actual),
            url=f"{base}/{p['ProductUrl']}" if p.get("ProductUrl") else base + "/", imagen=f"{base}/{img.lstrip('/')}" if img else ""))
    return out


# --------------------------------------------------------------------------- #
# Armytech (PrestaShop, tema iqit; /2-productos?page=N, 48 por pagina)
# --------------------------------------------------------------------------- #
_RE_AT_TARJETA = re.compile(r"(?=<article[^>]*product-miniature)")
_RE_AT_ID = re.compile(r'data-id-product="(\d+)"')
_RE_AT_TITULO = re.compile(r'product-title">\s*<a href="([^"]+)">\s*(.*?)\s*</a>', re.S)
_RE_AT_PRECIO = re.compile(r'class="product-price"[^>]*content="([\d\.]+)"')
_RE_AT_ANTES = re.compile(r'class="regular-price[^"]*">\s*\$\s*([\d\.,]+)')
_RE_AT_IMG = re.compile(r'<img[^>]*src="(https://[^"]+)"[^>]*product-thumbnail-first|product-thumbnail-first[^>]*src="(https://[^"]+)"')
_RE_AT_CAT = re.compile(r'product-category-name text-muted">\s*([^<]*?)\s*<')


def items_de_armytech(html_text: str, site: dict) -> list[ItemTecno]:
    out = []
    for bloque in _RE_AT_TARJETA.split(html_text)[1:]:
        bloque = bloque.split("</article>")[0]
        if "s-marca" in bloque[:400]:                           # pseudo-productos del armador de PC ("Selecciona tu marca")
            continue
        mi, mt, mp = _RE_AT_ID.search(bloque), _RE_AT_TITULO.search(bloque), _RE_AT_PRECIO.search(bloque)
        if not (mi and mt and mp):
            continue
        actual = float(mp.group(1))
        ma = _RE_AT_ANTES.search(bloque)
        antes = _num_ar(ma.group(1)) if ma else 0.0
        lista = antes if antes > actual else actual
        nombre_crudo = _html.unescape(re.sub(r"<[^>]+>", "", mt.group(2))).strip()
        mc, mim = _RE_AT_CAT.search(bloque), _RE_AT_IMG.search(bloque)
        cat, tipo = clasificar(nombre_crudo, mc.group(1) if mc else "")
        out.append(ItemTecno(
            tienda=site["name"], nombre=_bonito(nombre_crudo), referencia=f"at-{mi.group(1)}", marca=detectar_marca(nombre_crudo),
            categoria=cat, tipo=tipo, precio_lista=lista, precio_actual=actual, descuento_pct=_dto(lista, actual), url=mt.group(1),
            imagen=(mim.group(1) or mim.group(2)) if mim else ""))   # la "referencia" de Armytech es interna: no sirve para comparar
    return out


def scrap_armytech(sesion, site: dict, max_paginas: int) -> list[ItemTecno]:
    vistos: dict = {}
    for pagina in range(1, min(max_paginas, site.get("pag_max", 40)) + 1):
        _pausa()
        r = _pedir(sesion, "GET", f"https://{site['host']}/2-productos?page={pagina}", headers={"Accept": "text/html,application/xhtml+xml,*/*"})
        items = items_de_armytech(r.text, site)
        nuevos = 0
        for it in items:
            if it.clave not in vistos:
                vistos[it.clave] = it
                nuevos += 1
        if pagina % 5 == 1 or not nuevos:
            log.info("  pagina %d: %d productos, %d nuevos (acumulados %d)", pagina, len(items), nuevos, len(vistos))
        if not items or nuevos == 0:
            break
    return list(vistos.values())


# --------------------------------------------------------------------------- #
# Gamingcity (PHP propio; URLs amigables "<slug>--prod--<id>", paginas por offset de 25)
# --------------------------------------------------------------------------- #
_RE_GC_CAJA = re.compile(r'<div class="col-md-3 col-xs-6 cajasoferta">')
_RE_GC_URL = re.compile(r'href="([^"]*--det--(\d+))"')
_RE_GC_NOMBRE = re.compile(r'class="titprod"[^>]*>\s*([^<]*?)\s*<')
_RE_GC_IMG = re.compile(r'data-src="(https?://[^"]+)"')
_RE_GC_VENTA = re.compile(r'price-sales">\s*\$\s*([\d\.,]+)')
_RE_GC_ANTES = re.compile(r'price-standard">\s*\$\s*([\d\.,]+)')
_RE_GC_PRECIO = re.compile(r'<div class="price"><span>\s*(?:<span[^>]*>)?\s*\$\s*([\d\.,]+)')
_RE_GC_SIGUIENTE = re.compile(r'<a href="([^"]+)"[^>]*class="link">\s*Siguiente')


def items_de_gamingcity(html_text: str, site: dict, etiqueta: str = "") -> list[ItemTecno]:
    out = []
    for caja in _RE_GC_CAJA.split(html_text)[1:]:
        caja = caja.split("btn-compra")[0]
        mu, mn = _RE_GC_URL.search(caja), _RE_GC_NOMBRE.search(caja)
        if not (mu and mn):
            continue
        if re.search(r"sin stock|agotado|semaf_rojo", caja, re.I):
            continue
        mv, ma, mp = _RE_GC_VENTA.search(caja), _RE_GC_ANTES.search(caja), _RE_GC_PRECIO.search(caja)
        actual = _num_ar((mv or mp).group(1)) if (mv or mp) else 0.0
        if actual <= 0:
            continue
        antes = _num_ar(ma.group(1)) if ma else 0.0
        lista = antes if antes > actual else actual
        nombre_crudo = _html.unescape(mn.group(1)).strip()
        cat, tipo = clasificar(nombre_crudo, etiqueta)
        mi = _RE_GC_IMG.search(caja)
        out.append(ItemTecno(
            tienda=site["name"], nombre=_bonito(nombre_crudo), referencia=f"gc-{mu.group(2)}", marca=detectar_marca(nombre_crudo),
            categoria=cat, tipo=tipo, precio_lista=lista, precio_actual=actual, descuento_pct=_dto(lista, actual),
            url=f"https://{site['host']}/{mu.group(1)}", imagen=mi.group(1) if mi else ""))
    return out


def scrap_gamingcity(sesion, site: dict, max_paginas: int) -> list[ItemTecno]:
    vistos: dict = {}
    for ruta in site["categorias"]:
        n_cat, url, paginas = 0, f"https://{site['host']}/{ruta}", 0
        while url and paginas < min(max_paginas, site.get("pag_max", 60)):
            _pausa()
            r = _pedir(sesion, "GET", url, headers={"Accept": "text/html,application/xhtml+xml,*/*"})
            items = items_de_gamingcity(r.text, site, ruta.split("--")[0].replace("-", " "))
            nuevos = 0
            for it in items:
                if it.clave not in vistos:
                    vistos[it.clave] = it
                    nuevos += 1
            n_cat += nuevos
            paginas += 1
            ms = _RE_GC_SIGUIENTE.search(r.text)
            url = f"https://{site['host']}/{ms.group(1)}" if ms and nuevos else None
        log.info("    %-44s %2d pag, %4d nuevos (acumulados %d)", ruta, paginas, n_cat, len(vistos))
    return list(vistos.values())


def aplicar_historial(items: list[ItemTecno], site: dict, ahora: datetime) -> int:
    """Fullh4rd no publica precio anterior: la rebaja se mide contra el precio maximo que tuvo el producto en los ultimos
    N dias segun nuestras propias observaciones (corridas anteriores). Devuelve cuantos productos quedaron con rebaja."""
    try:
        import pymysql
        conn = pymysql.connect(charset="utf8mb4", autocommit=True, **DB_CONFIG)
    except Exception as e:  # noqa: BLE001
        log.warning("Sin historial de precios (%s): %s queda sin rebajas esta vez.", e, site["name"])
        return 0
    try:
        with conn.cursor() as cur:
            cur.execute(
                "SELECT p.nombre_norm, MAX(o.precio_actual) FROM observaciones o JOIN productos p ON p.id=o.producto_id "
                "JOIN tiendas t ON t.id=p.tienda_id JOIN corridas c ON c.id=o.corrida_id "
                "WHERE t.nombre=%s AND t.rubro=%s AND c.rubro=%s AND c.fecha_hora>=%s AND c.fecha_hora<%s GROUP BY p.nombre_norm",
                (site["name"], RUBRO, RUBRO, ahora - timedelta(days=site.get("historial_dias", 30)), ahora))
            previos = {nn: float(mx) for nn, mx in cur.fetchall()}
    except Exception as e:  # noqa: BLE001
        log.warning("No pude leer el historial de %s: %s", site["name"], e)
        return 0
    finally:
        conn.close()
    con = 0
    for it in items:
        if it.descuento_pct > 0:
            continue                                            # ya trae una rebaja publicada por la tienda
        mx = previos.get(it.referencia.lower())
        if mx and mx > it.precio_actual * 1.01:
            it.precio_lista = round(mx, 2)
            it.descuento_pct = _dto(it.precio_lista, it.precio_actual)
            con += 1
    log.info("  historial: %d productos con precio anterior en la base, %d bajaron de precio (>1%%)", len(previos), con)
    return con


# --------------------------------------------------------------------------- #
# Base de datos (MySQL): mismas tablas que perfumes y ropa, con rubro = 'tecnologia'
# --------------------------------------------------------------------------- #
DB_CONFIG = {
    "host": os.getenv("PERF_DB_HOST", "127.0.0.1"),
    "port": int(os.getenv("PERF_DB_PORT", "3306")),
    "user": os.getenv("PERF_DB_USER", "root"),
    "password": os.getenv("PERF_DB_PASS", ""),
    "database": os.getenv("PERF_DB_NAME", "perfumes_descuentos"),
}


def guardar_en_mysql(items: list, fecha_hora: datetime, reporte: list, estado_inicial: str = "completa") -> bool:
    """Inserta la corrida (rubro tecnologia), hace UPSERT de tiendas/productos y agrega las observaciones. Guarda TODOS los
    productos vistos (con rebaja -> 'rebaja'; sin rebaja -> 'ninguno': el dashboard solo muestra 'rebaja', pero asi queda el
    catalogo y el historial de precios). Idempotente por (rubro, fecha_hora); el estado solo se fija al CREAR la corrida."""
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
            pcts = [i.descuento_pct for i in items if i.descuento_pct > 0]
            cur.execute(
                "INSERT INTO corridas (rubro, fecha_hora, total_productos, min_descuento, max_descuento, estado) VALUES (%s,%s,%s,%s,%s,%s) "
                "ON DUPLICATE KEY UPDATE total_productos=VALUES(total_productos), min_descuento=VALUES(min_descuento), "
                "max_descuento=VALUES(max_descuento), id=LAST_INSERT_ID(id)",
                (RUBRO, fecha_hora, len(items), round(min(pcts), 2) if pcts else None, round(max(pcts), 2) if pcts else None, estado_inicial))
            corrida_id = cur.lastrowid

            tienda_id = {}
            for nombre in {i.tienda for i in items}:
                cur.execute("INSERT INTO tiendas (nombre, rubro) VALUES (%s,%s) ON DUPLICATE KEY UPDATE id=LAST_INSERT_ID(id)", (nombre, RUBRO))
                tienda_id[nombre] = cur.lastrowid

            prod_id = {}
            for it in items:
                nn = it.referencia.strip().lower()[:255]
                if (it.tienda, nn) in prod_id:
                    continue
                cur.execute(
                    "INSERT INTO productos (tienda_id, nombre, nombre_norm, marca, genero, url, categoria, tipo, referencia, imagen, primera_vez, ultima_vez) "
                    "VALUES (%s,%s,%s,%s,'unisex',%s,%s,%s,%s,%s,%s,%s) "
                    "ON DUPLICATE KEY UPDATE nombre=VALUES(nombre), marca=VALUES(marca), url=VALUES(url), categoria=VALUES(categoria), "
                    "tipo=VALUES(tipo), referencia=VALUES(referencia), imagen=VALUES(imagen), ultima_vez=VALUES(ultima_vez), id=LAST_INSERT_ID(id)",
                    (tienda_id[it.tienda], it.nombre[:255], nn, it.marca or None, it.url[:600] or None, it.categoria[:40],
                     (it.tipo or None), (it.pn or it.referencia)[:80], (it.imagen[:600] or None), fecha_hora, fecha_hora))
                prod_id[(it.tienda, nn)] = cur.lastrowid

            fuente_de = {s["name"]: _FUENTE[s["platform"]] for s in SITES}
            filas, vistos = [], set()
            for it in items:
                pid = prod_id[(it.tienda, it.referencia.strip().lower()[:255])]
                if pid in vistos:
                    continue
                vistos.add(pid)
                filas.append((corrida_id, pid, round(it.precio_lista, 2), round(it.precio_actual, 2), it.descuento_pct,
                              fuente_de.get(it.tienda, "html"), "rebaja" if it.descuento_pct > 0 else "ninguno"))
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
                    [(corrida_id, r["tienda"], r["plataforma"], r["total"], r["con"], r["estado"], r.get("mensaje"), r["segundos"]) for r in reporte])
        conn.commit()
        log.info("MySQL: corrida #%d (tecnologia) guardada: %d observaciones, %d productos.", corrida_id, len(filas), len(prod_id))
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
            n = cur.execute("UPDATE corridas SET estado=%s WHERE rubro=%s AND fecha_hora=%s", (estado, RUBRO, fecha_hora))
        if not n:
            log.error("No encontre la corrida %s (tecnologia) para marcarla '%s'.", fecha_hora, estado)
            return False
        log.info("Corrida %s (tecnologia) marcada '%s'.", fecha_hora, estado)
        return True
    except Exception as e:  # noqa: BLE001
        log.error("Error cerrando la corrida: %s", e)
        return False
    finally:
        conn.close()


def reclasificar_en_bd() -> int:
    """Sin scrapear: vuelve a aplicar clasificar()/detectar_marca() a los productos de tecnologia guardados que quedaron en
    'Otros' o sin marca (datos derivados). Nunca cambia un producto que ya tiene una categoria distinta de 'Otros': la
    categoria que publica la tienda (que no se guarda) puede haber sido mas precisa que el nombre solo."""
    import pymysql
    conn = pymysql.connect(charset="utf8mb4", autocommit=False, **DB_CONFIG)
    try:
        with conn.cursor() as cur:
            cur.execute("SELECT p.id, p.nombre, p.categoria, p.tipo, p.marca FROM productos p JOIN tiendas t ON t.id=p.tienda_id WHERE t.rubro=%s", (RUBRO,))
            cambios = []
            for pid, nombre, cat, tipo, marca in cur.fetchall():
                ncat, ntipo = (cat, tipo)
                if not cat or cat == "Otros":
                    c2, t2 = clasificar(nombre, "")
                    if c2 != "Otros":
                        ncat, ntipo = c2, (t2 or None)
                nmarca = marca or detectar_marca(nombre) or None
                if (ncat, ntipo, nmarca) != (cat, tipo, marca):
                    cambios.append((ncat, ntipo, nmarca, pid))
            cur.executemany("UPDATE productos SET categoria=%s, tipo=%s, marca=%s WHERE id=%s", cambios)
        conn.commit()
        log.info("Reclasificados %d productos de tecnologia (los que estaban en 'Otros' o sin marca).", len(cambios))
        return 0
    except Exception as e:  # noqa: BLE001
        conn.rollback()
        log.error("Error reclasificando (rollback): %s", e)
        return 4
    finally:
        conn.close()


# --------------------------------------------------------------------------- #
# Main
# --------------------------------------------------------------------------- #
_ADAPTADORES = {"cg_json": scrap_compragamer, "maximus_ws": scrap_maximus, "fh_html": scrap_fullh4rd, "gz_html": scrap_gezatek,
                "wt_json": scrap_wiztech, "at_html": scrap_armytech, "gc_html": scrap_gamingcity}


def main() -> int:
    ap = argparse.ArgumentParser(description="Scraper de descuentos de tecnologia (PC y electro)")
    ap.add_argument("--solo", nargs="*", metavar="TIENDA", help="solo estas tiendas (ej. compragamer)")
    ap.add_argument("--max-paginas", type=int, default=400, help="tope de paginas por tienda")
    ap.add_argument("--no-db", action="store_true", help="no guardar en MySQL")
    ap.add_argument("--fecha", metavar="AAAA-MM-DDTHH:MM:SS", help="guardar dentro de la corrida existente con esa fecha_hora (no la cierra)")
    ap.add_argument("--force-db", action="store_true", help="publicar la corrida aunque fallen tiendas")
    ap.add_argument("--umbral-tiendas", type=float, default=0.6, help="fraccion minima de tiendas OK para publicar la corrida")
    ap.add_argument("--json", metavar="RUTA", help="ademas volcar todos los items a JSON")
    ap.add_argument("--reclasificar", action="store_true", help="sin scrapear: reclasifica los productos guardados en 'Otros' o sin marca")
    args = ap.parse_args()
    if args.reclasificar:
        return reclasificar_en_bd()

    # sin microsegundos: DATETIME(0) los trunca al insertar y cerrar_corrida() no encontraria la fila
    ahora = datetime.fromisoformat(args.fecha) if args.fecha else datetime.now().replace(microsecond=0)
    sitios = [s for s in SITES if (s["name"] in args.solo if args.solo else s.get("activo", True))]
    if not sitios:
        log.error("Ninguna tienda coincide con --solo %s", args.solo)
        return 2

    import requests
    sesion = requests.Session()
    sesion.headers.update({"User-Agent": USER_AGENT, "Accept-Language": "es-AR,es;q=0.9"})
    todos: dict = {}
    reporte: list = []
    guardar_inc = not args.no_db
    inc_ok = True
    for site in sitios:
        log.info("== %s ==", site["name"])
        t0 = time.time()
        mensaje = None
        try:
            items = _ADAPTADORES[site["platform"]](sesion, site, args.max_paginas)
            if site.get("historial_dias") and not args.no_db:
                aplicar_historial(items, site, ahora)
            items = [i for i in items if not _RE_EXCLUIR.search(i.nombre)]
            estado = "ok" if items else "vacio"
        except Bloqueado as e:
            log.error("  BLOQUEADO: %s (dejo la tienda y sigo)", e)
            items, estado, mensaje = [], "bloqueado", str(e)[:250]
        except Exception as e:  # noqa: BLE001
            log.exception("  fallo")
            items, estado, mensaje = [], "error", str(e)[:250]
        nuevos = []
        for it in items:
            if it.clave not in todos:
                todos[it.clave] = it
                nuevos.append(it)
        con = sum(1 for i in nuevos if i.descuento_pct > 0)
        log.info("  -> %d productos (%d con rebaja)", len(nuevos), con)
        rep = {"tienda": site["name"], "plataforma": site["platform"], "total": len(nuevos), "con": con, "estado": estado,
               "segundos": round(time.time() - t0, 1), "mensaje": mensaje}
        reporte.append(rep)
        if guardar_inc and nuevos:
            if not guardar_en_mysql(nuevos, ahora, [rep], "completa" if args.fecha else "en_curso"):
                inc_ok = False
                log.warning("No pude guardar %s ahora; se reintenta con todo al final de la corrida.", site["name"])
        elif guardar_inc and not nuevos:
            guardar_en_mysql([], ahora, [rep], "completa" if args.fecha else "en_curso")        # deja constancia del error en corrida_sitios
        time.sleep(random.uniform(2, 5))

    items = list(todos.values())
    log.info("TOTAL: %d productos unicos, %d con rebaja", len(items), sum(1 for i in items if i.descuento_pct > 0))
    for r in reporte:
        log.info("  %-14s %-10s %5d productos, %5d con rebaja, %.0fs", r["tienda"], r["estado"], r["total"], r["con"], r["segundos"])
    cats: dict = {}
    for i in items:
        cats[i.categoria] = cats.get(i.categoria, 0) + 1
    log.info("Categorias: %s", ", ".join(f"{k} {v}" for k, v in sorted(cats.items(), key=lambda kv: -kv[1])))
    if args.json:
        with open(args.json, "w", encoding="utf-8") as f:
            json.dump([asdict(i) for i in items], f, ensure_ascii=False, indent=1)
        log.info("JSON: %s", args.json)
    for it in sorted([i for i in items if i.descuento_pct > 0], key=lambda i: -i.descuento_pct)[:8]:
        log.info("  %5.1f%%  %-12s %-40s ($%d -> $%d)", it.descuento_pct, it.tienda, it.nombre[:40], it.precio_lista, it.precio_actual)

    if args.no_db:
        return 0 if items else 1
    fallidas = [r for r in reporte if r["estado"] in ("bloqueado", "error")]
    ok_ratio = 1 - len(fallidas) / len(reporte) if reporte else 0
    if not items or (ok_ratio < args.umbral_tiendas and not args.force_db):
        log.error("CORRIDA INCOMPLETA: %d de %d tiendas bloqueadas/con error (minimo %.0f%% sin fallar). No la publico para no tapar "
                  "el ultimo snapshot bueno (queda 'incompleta' en la base). Usa --force-db para publicarla igual.",
                  len(fallidas), len(reporte), args.umbral_tiendas * 100)
        if not args.fecha and inc_ok:
            cerrar_corrida(ahora, "incompleta")
        return 3
    if args.fecha:
        return 0
    if args.solo and not args.force_db:
        log.warning("Corrida parcial (--solo): se la deja 'incompleta' para no publicar un snapshot con tiendas faltantes. "
                    "Para sumar una tienda a la corrida visible usa --fecha; para publicarla igual, --force-db.")
        cerrar_corrida(ahora, "incompleta")
        return 0
    if not inc_ok and not guardar_en_mysql(items, ahora, reporte, "en_curso"):
        return 4
    return 0 if cerrar_corrida(ahora, "completa") else 4


if __name__ == "__main__":
    sys.exit(main())
