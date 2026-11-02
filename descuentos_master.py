#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
descuentos_master.py
====================
Script maestro: recorre TODAS las perfumerías en un solo paso y genera UN
único Excel ordenado de mayor a menor descuento + hoja resumen por tienda.

Cada ejecución genera un archivo nuevo (no pisa el anterior): se guarda dentro
de la subcarpeta "excel_perfumes" con el nombre + _fecha_hora. Además compara
contra el Excel más reciente de esa carpeta, emparejando por NOMBRE de producto
(sin importar la tienda), y resalta:
  - EN VERDE   los productos que ahora son mejor negocio (mejor descuento % o
               precio más bajo que la corrida anterior).
  - EN AMARILLO los productos nuevos que no figuraban en el Excel anterior.

Dos motores:
  - VTEX  -> API de catálogo (JSON exacto). Si el path de categoría no trae
             nada, AUTODESCUBRE la categoría desde el árbol de VTEX.
  - HTML  -> Playwright + barrido heurístico del DOM. Detecta precio TACHADO
             real (<del>/<s>/line-through/clases old-list-regular) y badges %.

DEPENDENCIAS
------------
    pip install requests openpyxl playwright pymysql
    playwright install chromium

USO
---
    python descuentos_master.py                      # los 8 sitios -> Excel
    python descuentos_master.py --solo juleriaque parfumerie
    python descuentos_master.py --min-descuento 30
    python descuentos_master.py --no-playwright      # saltea los HTML
    python descuentos_master.py -o salida.xlsx
    python descuentos_master.py --headful            # ver el navegador (debug)

NOTA: los adaptadores VTEX dan datos exactos. Los HTML son heurísticos: si
algún sitio sigue trayendo poco, corré con --headful y fijate las cards reales.
"""

from __future__ import annotations

import argparse
import glob
import json
import logging
import os
import random
import re
import sys
import time
import unicodedata
from dataclasses import dataclass, asdict
from typing import Optional

import requests

try:
    from openpyxl import Workbook
    from openpyxl.styles import Font, PatternFill, Alignment, Border, Side
    from openpyxl.formatting.rule import ColorScaleRule
    OPENPYXL_OK = True
except ImportError:
    OPENPYXL_OK = False


# --------------------------------------------------------------------------- #
# Configuración de sitios
# --------------------------------------------------------------------------- #
# platform: "vtex" | "html"
# Para VTEX: category_path (intento 1) y cat_keywords (autodescubrimiento).
# Si el path no resuelve y el autodescubrimiento agarra una categoría angosta,
# podés fijar IDs exactos con "cat_ids": [123, 456] (lo más confiable). Los IDs
# los sacás del árbol: https://HOST/api/catalog_system/pub/category/tree/50
# Para HTML: url + scrolls.
SITES: list[dict] = [
    # Cada tienda puede tener hasta 3 entradas (masculino / femenino / unisex).
    # Las entradas comparten el mismo "name" → mismo registro en la tabla
    # "tiendas"; el campo "genero" diferencia los productos en "productos".
    # Con --solo <tienda> se raspan todos los géneros de esa tienda.

    # ── Pigmento ────────────────────────────────────────────────────────── #
    {"name": "pigmento", "platform": "vtex", "genero": "masculino",
     "host": "www.perfumeriaspigmento.com.ar",
     "category_path": "perfume/hombre",
     "cat_keywords": ["hombre", "masculin", "men"]},
    {"name": "pigmento", "platform": "vtex", "genero": "femenino",
     "host": "www.perfumeriaspigmento.com.ar",
     "category_path": "Perfume/Mujer",
     "cat_keywords": ["mujer", "femeni", "women"]},
    {"name": "pigmento", "platform": "vtex", "genero": "unisex",
     "host": "www.perfumeriaspigmento.com.ar",
     "category_path": "Perfume/Unisex",
     "cat_keywords": ["unisex"]},

    # ── Beauty24 ────────────────────────────────────────────────────────── #
    {"name": "beauty24", "platform": "vtex", "genero": "masculino",
     "host": "www.beauty24.com.ar",
     "category_path": "perfumes-y-fragancias/perfumes-de-hombre",
     "cat_keywords": ["hombre", "masculin", "men"]},
    {"name": "beauty24", "platform": "vtex", "genero": "femenino",
     "host": "www.beauty24.com.ar",
     "category_path": "perfumes-y-fragancias/perfumes-de-mujer",
     "cat_keywords": ["mujer", "femeni", "women"]},
    {"name": "beauty24", "platform": "vtex", "genero": "unisex",
     "host": "www.beauty24.com.ar",
     "category_path": "perfumes-y-fragancias/perfumes-unisex",
     "cat_keywords": ["unisex"]},

    # ── Rouge (sc=1 obligatorio) ─────────────────────────────────────────── #
    {"name": "rouge", "platform": "vtex", "genero": "masculino",
     "host": "www.perfumeriasrouge.com",
     "category_path": "perfumes-y-fragancias/perfumes-de-hombre",
     "sc": 1,
     "cat_keywords": ["hombre", "masculin", "men"]},
    {"name": "rouge", "platform": "vtex", "genero": "femenino",
     "host": "www.perfumeriasrouge.com",
     "category_path": "perfumes-y-fragancias/perfumes-de-mujer",
     "sc": 1,
     "cat_keywords": ["mujer", "femeni", "women"]},
    {"name": "rouge", "platform": "vtex", "genero": "unisex",
     "host": "www.perfumeriasrouge.com",
     "category_path": "perfumes-y-fragancias/perfumes-unisex",
     "sc": 1,
     "cat_keywords": ["unisex"]},

    # ── Fiorani (antes usaba buscar_todo; ahora paths específicos) ────────── #
    {"name": "fiorani", "platform": "vtex", "genero": "masculino",
     "host": "www.fiorani.com",
     "category_path": "fragancias/fragancias-masculinas",
     "cat_keywords": ["hombre", "masculin", "caballero"]},
    {"name": "fiorani", "platform": "vtex", "genero": "femenino",
     "host": "www.fiorani.com",
     "category_path": "fragancias/fragancias-femeninas",
     "cat_keywords": ["mujer", "femeni"]},
    {"name": "fiorani", "platform": "vtex", "genero": "unisex",
     "host": "www.fiorani.com",
     "category_path": "fragancias/fragancias-unisex",
     "cat_keywords": ["unisex"]},

    # ── Juleriaque ──────────────────────────────────────────────────────── #
    {"name": "juleriaque", "platform": "html", "genero": "masculino",
     "host": "www.juleriaque.com.ar",
     "url": ("https://www.juleriaque.com.ar/fragancias?category-1=fragancias"
             "&category-3=masculinas&sort=score_desc&page=0"),
     "pag_param": "page", "pag_base": 0, "pag_max": 10,
     "scrolls": 8, "variantes_detalle": True},
    {"name": "juleriaque", "platform": "html", "genero": "femenino",
     "host": "www.juleriaque.com.ar",
     "url": ("https://www.juleriaque.com.ar/fragancias?category-1=fragancias"
             "&category-2=premium&category-2=low-cost"
             "&category-3=femeninas&fuzzy=0&operator=and"
             "&facets=category-1%2Ccategory-2%2Ccategory-3%2Cfuzzy%2Coperator"
             "&sort=score_desc&page=0"),
     "pag_param": "page", "pag_base": 0, "pag_max": 10,
     "scrolls": 8, "variantes_detalle": True},
    {"name": "juleriaque", "platform": "html", "genero": "unisex",
     "host": "www.juleriaque.com.ar",
     "url": ("https://www.juleriaque.com.ar/fragancias?category-1=fragancias"
             "&category-2=premium&category-2=low-cost"
             "&category-3=unisex&fuzzy=0&operator=and"
             "&facets=category-1%2Ccategory-2%2Ccategory-3%2Cfuzzy%2Coperator"
             "&sort=score_desc&page=0"),
     "pag_param": "page", "pag_base": 0, "pag_max": 10,
     "scrolls": 8, "variantes_detalle": True},

    # ── Parfumerie (se quitó parfusale; solo categorías estructuradas) ───── #
    {"name": "parfumerie", "platform": "html", "genero": "masculino",
     "host": "www.parfumerie.com.ar",
     "url": "https://www.parfumerie.com.ar/fragancias/masculinas",
     "scrolls": 16},
    {"name": "parfumerie", "platform": "html", "genero": "femenino",
     "host": "www.parfumerie.com.ar",
     "url": "https://www.parfumerie.com.ar/fragancias/femeninas",
     "scrolls": 16},
    {"name": "parfumerie", "platform": "html", "genero": "unisex",
     "host": "www.parfumerie.com.ar",
     "url": "https://www.parfumerie.com.ar/fragancias/unisex",
     "scrolls": 16},

    # ── Ruizyroca ───────────────────────────────────────────────────────── #
    {"name": "ruizyroca", "platform": "html", "genero": "masculino",
     "host": "www.ruizyrocaperfumeria.com",
     "url": "https://www.ruizyrocaperfumeria.com/fragancias1/masculinas/",
     "pag_param": "mpage", "pag_base": 1, "pag_max": 12,
     "scrolls": 6},
    {"name": "ruizyroca", "platform": "html", "genero": "femenino",
     "host": "www.ruizyrocaperfumeria.com",
     "url": "https://www.ruizyrocaperfumeria.com/fragancias1/femeninas/",
     "pag_param": "mpage", "pag_base": 1, "pag_max": 12,
     "scrolls": 6},
    {"name": "ruizyroca", "platform": "html", "genero": "unisex",
     "host": "www.ruizyrocaperfumeria.com",
     "url": "https://www.ruizyrocaperfumeria.com/fragancias1/unisex3/",
     "pag_param": "mpage", "pag_base": 1, "pag_max": 12,
     "scrolls": 6},

    # ── Normy (sin unisex) ──────────────────────────────────────────────── #
    # WooCommerce: paginacion clasica por path (/page/2/), no scroll infinito.
    # Los nombres ya traen el tamano ("100ML VAP."), asi que no hace falta
    # entrar al detalle a expandir variantes.
    {"name": "normy", "platform": "html", "genero": "masculino",
     "host": "www.perfumerianormy.com.ar",
     "url": "https://www.perfumerianormy.com.ar/categoria-producto/fragancias/fragancias-masculinas/",
     "pag_path": "page/{n}/", "pag_base": 1, "pag_max": 20,
     "scrolls": 4, "variantes_detalle": False},
    {"name": "normy", "platform": "html", "genero": "femenino",
     "host": "www.perfumerianormy.com.ar",
     "url": "https://www.perfumerianormy.com.ar/categoria-producto/fragancias/fragancias-femeninas/",
     "pag_path": "page/{n}/", "pag_base": 1, "pag_max": 20,
     "scrolls": 4, "variantes_detalle": False},

    # ── Getthelook (sc=1; sin unisex) ───────────────────────────────────── #
    {"name": "getthelook", "platform": "vtex", "genero": "masculino",
     "host": "www.getthelook.com.ar",
     "category_path": "fragancias/masculinas",
     "sc": 1,
     "cat_keywords": ["masculin", "hombre", "men", "caballero"]},
    {"name": "getthelook", "platform": "vtex", "genero": "femenino",
     "host": "www.getthelook.com.ar",
     "category_path": "fragancias/femeninas",
     "sc": 1,
     "cat_keywords": ["mujer", "femeni", "women"]},

    # ── Farmalife (sin unisex; femenino solo premium) ────────────────────── #
    {"name": "farmalife", "platform": "vtex", "genero": "masculino",
     "host": "www.farmalife.com.ar",
     "category_paths": ["fragancias/premium/masculinas",
                        "fragancias/semiselectivas/masculinas"],
     "sc": 1,
     "cat_keywords": ["masculin", "hombre", "men", "caballero"]},
    {"name": "farmalife", "platform": "vtex", "genero": "femenino",
     "host": "www.farmalife.com.ar",
     "category_paths": ["fragancias/premium/femeninas"],
     "sc": 1,
     "cat_keywords": ["mujer", "femeni", "women"]},

    # ── Unishop (SPA Vue SSR; sin navegador) ────────────────────────────── #
    {"name": "unishop", "platform": "unishop", "genero": "masculino",
     "host": "unishop.com.ar",
     "url": "https://unishop.com.ar/perfumes-y-fragancias/importadas/masculinas",
     "pag_max": 30},
    {"name": "unishop", "platform": "unishop", "genero": "femenino",
     "host": "unishop.com.ar",
     "url": "https://unishop.com.ar/perfumes-y-fragancias/importadas/femeninas",
     "pag_max": 30},
    {"name": "unishop", "platform": "unishop", "genero": "unisex",
     "host": "unishop.com.ar",
     "url": "https://unishop.com.ar/perfumes-y-fragancias/importadas/unisex",
     "pag_max": 30},

    # ── Ambrosia (Tiendanube; descuentos principalmente por transferencia) ── #
    {"name": "ambrosia", "platform": "tiendanube", "genero": "masculino",
     "host": "www.ambrosiaperfumes.com.ar",
     "url": "https://www.ambrosiaperfumes.com.ar/fragancias/?G%C3%A9nero=Masculino",
     "pag_max": 12},
    {"name": "ambrosia", "platform": "tiendanube", "genero": "femenino",
     "host": "www.ambrosiaperfumes.com.ar",
     "url": "https://www.ambrosiaperfumes.com.ar/fragancias/?G%C3%A9nero=Femenino",
     "pag_max": 12},
    {"name": "ambrosia", "platform": "tiendanube", "genero": "unisex",
     "host": "www.ambrosiaperfumes.com.ar",
     "url": "https://www.ambrosiaperfumes.com.ar/fragancias/?G%C3%A9nero=Unisex",
     "pag_max": 12},
]

USER_AGENTS = [
    "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 "
    "(KHTML, like Gecko) Chrome/124.0 Safari/537.36",
    "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 "
    "(KHTML, like Gecko) Version/17.4 Safari/605.1.15",
]

logging.basicConfig(level=logging.INFO,
                    format="%(asctime)s  %(levelname)-7s  %(message)s",
                    datefmt="%H:%M:%S")
log = logging.getLogger("master")


# --------------------------------------------------------------------------- #
# Modelo
# --------------------------------------------------------------------------- #
@dataclass
class Item:
    tienda: str
    marca: str
    nombre: str
    precio_lista: float
    precio_actual: float
    descuento_pct: float
    fuente: str          # "api" | "tachado" | "dos_precios" | "badge_%" | "tiendanube"
    url: str
    tipo_descuento: str = "rebaja"
    precio_transferencia: Optional[float] = None
    genero: str = "masculino"   # "masculino" | "femenino" | "unisex"
    presentacion: str = ""      # texto del tamaño/variante: "100 ML", "50 ML", etc.
    imagen: str = ""            # URL (https) de la foto del producto; "" si no hay

    @property
    def clave(self) -> tuple:
        return (self.tienda, self.nombre.lower().strip(), round(self.precio_actual, 2))


def _dto(lista: float, actual: float) -> float:
    return round((lista - actual) / lista * 100, 1) if lista > actual > 0 else 0.0


def _url_imagen(src, host: str = "") -> str:
    """Normaliza la URL de una foto: https, absoluta, sin data: ni vacíos.
    Las fotos de VTEX (/arquivos/ids/<id>/...) se piden a 400 px de ancho (la
    original pesa ~40 KB-1 MB; el dashboard solo muestra miniaturas)."""
    s = (src or "").strip()
    if not s or s.startswith("data:"):
        return ""
    if s.startswith("//"):
        s = "https:" + s
    elif s.startswith("http://"):
        s = "https://" + s[7:]
    elif s.startswith("/"):
        if not host:
            return ""
        s = f"https://{host}{s}"
    elif not s.startswith("https://"):
        if not host:
            return ""
        s = f"https://{host}/{s}"
    s = re.sub(r"(/arquivos/ids/\d+)/", r"\1-400-auto/", s, count=1)
    return s[:600]


def _norm(s: str) -> str:
    s = unicodedata.normalize("NFKD", s or "")
    return "".join(c for c in s if not unicodedata.combining(c)).lower()


# --------------------------------------------------------------------------- #
# Persistencia en MySQL (opcional)
# --------------------------------------------------------------------------- #
# Config por variables de entorno, con defaults para XAMPP local. Para cambiarla
# sin tocar el código, definí PERF_DB_HOST / PERF_DB_USER / PERF_DB_PASS / etc.
DB_CONFIG = {
    "host": os.getenv("PERF_DB_HOST", "127.0.0.1"),
    "port": int(os.getenv("PERF_DB_PORT", "3306")),
    "user": os.getenv("PERF_DB_USER", "root"),
    "password": os.getenv("PERF_DB_PASS", ""),
    "database": os.getenv("PERF_DB_NAME", "perfumes_descuentos"),
}

_RE_ML_DB = re.compile(r"(\d+(?:[.,]\d+)?)\s*ml\b")


def _tamano_ml(nombre: str) -> Optional[int]:
    """Extrae el tamaño en ml del nombre (None si no lo aclara)."""
    m = _RE_ML_DB.search(_norm(nombre))
    if not m:
        return None
    try:
        return int(round(float(m.group(1).replace(",", "."))))
    except ValueError:
        return None


def _nombre_norm(nombre: str) -> str:
    """Nombre normalizado (minúsculas, sin acentos, espacios colapsados)."""
    return re.sub(r"\s+", " ", _norm(nombre)).strip()[:255]


def _nombre_con_tamano(nombre_prod: str, texto_variante: str) -> str:
    """Devuelve el nombre final de un SKU incluyendo el tamaño si no estaba ya.
    - Si nombre_prod ya contiene un patrón de ml → sin cambios
    - Si texto_variante es una extensión de nombre_prod (ej: "Libre EDP 90 ML") → usa texto_variante
    - Si texto_variante solo agrega un tamaño (ej: "90 ML") → lo añade al final
    - Si texto_variante no aporta ml → sin cambios
    """
    nprod_n = _nombre_norm(nombre_prod)
    nvar_n  = _nombre_norm(texto_variante)

    if _RE_ML_DB.search(nprod_n):
        return nombre_prod  # ya tiene tamaño en el nombre

    if nprod_n and nprod_n in nvar_n and _RE_ML_DB.search(nvar_n):
        return texto_variante.strip()  # variante es el nombre completo

    m = re.search(r"\d+(?:[.,]\d+)?\s*ml", texto_variante, re.I)
    if m:
        return f"{nombre_prod.rstrip()} {m.group().strip().upper()}"

    return nombre_prod


def _presentacion(texto_variante: str, nombre_prod: str = "") -> str:
    """Devuelve el texto de presentación del SKU (p.ej. '100 ML', '50 ML').
    Prioriza el texto de variante si contiene ml; si no, extrae del nombre."""
    if texto_variante:
        fuente = texto_variante.strip()
        if _RE_ML_DB.search(_norm(fuente)):
            return fuente[:150]
    if nombre_prod:
        m = re.search(r"\d+(?:[.,]\d+)?\s*ml\b", nombre_prod, re.I)
        if m:
            return m.group().strip().upper()
    return ""


def guardar_en_mysql(items: list, fecha_hora, args, reporte_sitios=None) -> bool:
    """Guarda la corrida en MySQL: inserta la corrida, hace UPSERT de tiendas y
    productos, inserta las observaciones (precio/descuento de esta corrida) y el
    log de auditoría por sitio. No pisa el histórico: cada corrida agrega sus
    observaciones."""
    reporte_sitios = reporte_sitios or []
    try:
        import pymysql
    except ImportError:
        log.error("Falta PyMySQL para guardar en la base: pip install pymysql "
                  "(omito el guardado en MySQL).")
        return False
    try:
        conn = pymysql.connect(charset="utf8mb4", autocommit=False, **DB_CONFIG)
    except Exception as e:  # noqa: BLE001
        log.error("No pude conectar a MySQL (%s). ¿Está MySQL/XAMPP levantado y "
                  "creada la base '%s'?", e, DB_CONFIG["database"])
        return False
    try:
        with conn.cursor() as cur:
            # 1) corrida (idempotente por fecha_hora gracias al UNIQUE)
            # min/max_descuento = valores REALES de las rebajas de esta corrida
            # (antes se guardaban los argumentos CLI --min/--max-descuento: 0 y 90)
            _pcts = [it.descuento_pct for it in items
                     if getattr(it, "tipo_descuento", "rebaja") == "rebaja"]
            min_real = round(min(_pcts), 2) if _pcts else None
            max_real = round(max(_pcts), 2) if _pcts else None
            cur.execute(
                "INSERT INTO corridas (fecha_hora, total_productos, "
                "min_descuento, max_descuento) VALUES (%s,%s,%s,%s) "
                "ON DUPLICATE KEY UPDATE total_productos=VALUES(total_productos), "
                "min_descuento=VALUES(min_descuento), "
                "max_descuento=VALUES(max_descuento), id=LAST_INSERT_ID(id)",
                (fecha_hora, len(items), min_real, max_real))
            corrida_id = cur.lastrowid

            # 2) tiendas (UPSERT) -> cache de ids
            tienda_id: dict[str, int] = {}
            for nombre in {it.tienda for it in items}:
                cur.execute(
                    "INSERT INTO tiendas (nombre) VALUES (%s) "
                    "ON DUPLICATE KEY UPDATE id=LAST_INSERT_ID(id)", (nombre,))
                tienda_id[nombre] = cur.lastrowid

            # 3) productos (UPSERT) -> cache de ids por (tienda, nombre_norm)
            prod_id: dict[tuple, int] = {}
            for it in items:
                nn = _nombre_norm(it.nombre)
                clave = (it.tienda, nn)
                if clave in prod_id:
                    continue
                cur.execute(
                    "INSERT INTO productos (tienda_id, nombre, nombre_norm, "
                    "marca, genero, tamano_ml, presentacion, url, imagen, primera_vez, ultima_vez) "
                    "VALUES (%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s) "
                    "ON DUPLICATE KEY UPDATE nombre=VALUES(nombre), "
                    "marca=VALUES(marca), genero=VALUES(genero), "
                    "tamano_ml=VALUES(tamano_ml), "
                    "presentacion=VALUES(presentacion), "
                    "url=VALUES(url), "
                    "imagen=COALESCE(VALUES(imagen), imagen), "
                    "ultima_vez=VALUES(ultima_vez), "
                    "id=LAST_INSERT_ID(id)",
                    (tienda_id[it.tienda], it.nombre[:255], nn,
                     it.marca or None, it.genero,
                     _tamano_ml(it.nombre),
                     (it.presentacion[:150] if it.presentacion
                      else _presentacion("", it.nombre) or None),
                     it.url or None,
                     it.imagen or None,
                     fecha_hora, fecha_hora))
                prod_id[clave] = cur.lastrowid

            # 4) observaciones (una por producto en esta corrida)
            filas = []
            vistos = set()
            for it in items:
                pid = prod_id[(it.tienda, _nombre_norm(it.nombre))]
                if pid in vistos:          # mismo producto repetido en la corrida
                    continue
                vistos.add(pid)
                ptransf = (round(it.precio_transferencia, 2)
                           if it.precio_transferencia else None)
                filas.append((corrida_id, pid, round(it.precio_lista, 2),
                              round(it.precio_actual, 2), it.descuento_pct,
                              it.fuente, it.tipo_descuento, ptransf))
            cur.executemany(
                "INSERT INTO observaciones (corrida_id, producto_id, "
                "precio_lista, precio_actual, descuento_pct, fuente, "
                "tipo_descuento, precio_transferencia) "
                "VALUES (%s,%s,%s,%s,%s,%s,%s,%s) "
                "ON DUPLICATE KEY UPDATE precio_lista=VALUES(precio_lista), "
                "precio_actual=VALUES(precio_actual), "
                "descuento_pct=VALUES(descuento_pct), fuente=VALUES(fuente), "
                "tipo_descuento=VALUES(tipo_descuento), "
                "precio_transferencia=VALUES(precio_transferencia)",
                filas)

            # 5) log de auditoría por sitio (qué encontró cada perfumería)
            if reporte_sitios:
                cur.executemany(
                    "INSERT INTO corrida_sitios (corrida_id, tienda, plataforma, "
                    "total_encontrados, con_descuento, estado, mensaje, segundos) "
                    "VALUES (%s,%s,%s,%s,%s,%s,%s,%s) "
                    "ON DUPLICATE KEY UPDATE "
                    "total_encontrados=VALUES(total_encontrados), "
                    "con_descuento=VALUES(con_descuento), estado=VALUES(estado), "
                    "mensaje=VALUES(mensaje), segundos=VALUES(segundos)",
                    [(corrida_id, r["tienda"], r.get("plataforma"),
                      r.get("total", 0), r.get("con", 0), r.get("estado", "ok"),
                      (r.get("mensaje") or None), r.get("segundos"))
                     for r in reporte_sitios])
        conn.commit()
        log.info("MySQL: corrida #%d guardada (%d observaciones, %d productos).",
                 corrida_id, len(filas), len(prod_id))
        return True
    except Exception as e:  # noqa: BLE001
        conn.rollback()
        log.error("Error guardando en MySQL (hice rollback): %s", e)
        return False
    finally:
        conn.close()


# --------------------------------------------------------------------------- #
# Salud de la corrida y chequeo de red
# --------------------------------------------------------------------------- #
# Hosts de control: uno externo y dos perfumerías. Si no resuelven, la máquina
# no tiene red usable todavía (típico cuando la tarea arranca junto con Windows
# y el wifi/DNS aún no levantó) y el barrido saldría vacío.
HOSTS_CONTROL = ["www.google.com",
                 "www.perfumeriaspigmento.com.ar",
                 "www.juleriaque.com.ar"]

# Fracción mínima de perfumerías que deben traer datos para considerar la
# corrida completa y guardarla. Ver _salud_corrida().
UMBRAL_TIENDAS_OK = 0.70


def hay_red(hosts=None, timeout: float = 6.0) -> bool:
    """True si al menos un host de control resuelve por DNS y contesta."""
    import socket
    for host in (hosts or HOSTS_CONTROL):
        try:
            socket.getaddrinfo(host, 443, proto=socket.IPPROTO_TCP)
        except OSError:
            continue
        try:
            with socket.create_connection((host, 443), timeout=timeout):
                return True
        except OSError:
            continue
    return False


def esperar_red(intentos: int = 6, espera: float = 20.0) -> bool:
    """Espera a que haya red antes de empezar. Devuelve False si nunca llegó.

    Sin esto, una corrida lanzada con la red caída termina con casi todos los
    sitios en "vacio" y (antes de este control) pisaba la última corrida buena
    en la base, dejando el dashboard con 5 perfumerías en vez de 12."""
    for i in range(1, intentos + 1):
        if hay_red():
            if i > 1:
                log.info("Red disponible (intento %d).", i)
            return True
        if i < intentos:
            log.warning("Sin conectividad (intento %d/%d). Reintento en %.0fs...",
                        i, intentos, espera)
            time.sleep(espera)
    return False


def _salud_corrida(reporte_sitios) -> dict:
    """Resume cuántas perfumerías trajeron datos.

    Agrupa por tienda y no por entrada tienda/género: alcanza con que un solo
    género traiga productos para dar por respondida a esa perfumería."""
    por_tienda: dict[str, int] = {}
    for r in reporte_sitios:
        # "juleriaque_femenino" -> "juleriaque" (el género nunca lleva "_")
        nombre = r["tienda"].rsplit("_", 1)[0]
        por_tienda[nombre] = por_tienda.get(nombre, 0) + r.get("total", 0)
    con_datos = sorted(n for n, t in por_tienda.items() if t > 0)
    sin_datos = sorted(n for n, t in por_tienda.items() if t == 0)
    total = len(por_tienda)
    return {"total": total, "con_datos": con_datos, "sin_datos": sin_datos,
            "ratio": (len(con_datos) / total) if total else 0.0}


def nueva_session() -> requests.Session:
    s = requests.Session()
    s.headers.update({
        "User-Agent": random.choice(USER_AGENTS),
        "Accept": "application/json, text/plain, */*",
        "Accept-Language": "es-AR,es;q=0.9",
    })
    return s


def _get(session, url, **kw):
    try:
        r = session.get(url, timeout=25, **kw)
        if r.status_code in (200, 206):
            return r
        log.debug("  %s -> HTTP %s", url, r.status_code)
    except requests.RequestException as e:
        log.debug("  fallo GET %s: %s", url, e)
    return None


# --------------------------------------------------------------------------- #
# VTEX: autodescubrimiento de categoría
# --------------------------------------------------------------------------- #
def descubrir_categorias(session, host: str, keywords: list[str]) -> list[int]:
    """Recorre el arbol de categorias de VTEX y devuelve los IDs cuyo nombre
    matchee alguna keyword."""
    r = _get(session, f"https://{host}/api/catalog_system/pub/category/tree/50")
    if r is None:
        return []
    try:
        tree = r.json()
    except ValueError:
        return []

    kws = [_norm(k) for k in keywords]
    encontrados: list[int] = []

    def recorrer(nodos):
        for n in nodos:
            nombre = _norm(n.get("name", ""))
            if any(k in nombre for k in kws):
                encontrados.append(n["id"])
            if n.get("children"):
                recorrer(n["children"])

    recorrer(tree)
    return encontrados


def _parse_vtex_productos(data, host, tienda) -> list[Item]:
    """Parsea productos VTEX iterando TODOS los SKUs (items) de cada producto,
    no solo el primero. Así captura las distintas presentaciones/tamaños."""
    items = []
    for prod in data:
        prod_nombre = (prod.get("productName") or "").strip()
        marca       = (prod.get("brand") or "").strip()
        link        = prod.get("linkText", "")
        url_base    = f"https://{host}/{link}/p" if link else f"https://{host}"

        skus = prod.get("items") or []
        if not skus:
            continue

        # foto: la del producto (primer SKU con imagen), igual para todas las presentaciones
        imagen = ""
        for sku in skus:
            imgs = sku.get("images") or []
            if imgs:
                imagen = _url_imagen(imgs[0].get("imageUrl"), host)
                if imagen:
                    break

        for sku in skus:
            try:
                offer  = sku["sellers"][0]["commertialOffer"]
                actual = float(offer.get("Price") or 0)
                lista  = float(offer.get("ListPrice") or 0)
            except (KeyError, IndexError, TypeError):
                continue
            if not actual:
                continue

            disponible = offer.get("IsAvailable")
            if disponible is None:
                disponible = (offer.get("AvailableQuantity") or 0) > 0
            if not disponible:
                continue

            # nameComplete = nombre del producto + valores de variante (ej: "D By Diesel EDT 100 ML + Weekend Bag")
            # name        = solo la variante (ej: "100 ML" o "D BY DIESEL EDT 100 ML + WEEKEND BAG")
            sku_nombre_variant = (sku.get("name") or "").strip()
            sku_nombre_full    = (sku.get("nameComplete") or sku_nombre_variant).strip()
            nombre             = _nombre_con_tamano(prod_nombre, sku_nombre_full)
            presentacion       = _presentacion(sku_nombre_variant, nombre)

            sku_id = sku.get("itemId") or ""
            url    = f"{url_base}?skuId={sku_id}" if sku_id else url_base

            items.append(Item(
                tienda=tienda,
                marca=marca,
                nombre=nombre,
                precio_lista=lista or actual,
                precio_actual=actual,
                descuento_pct=_dto(lista, actual),
                fuente="api",
                url=url,
                presentacion=presentacion,
                imagen=imagen,
            ))
    return items


def scrap_vtex(session, site, max_items) -> list[Item]:
    host, tienda = site["host"], site["name"]
    session.headers["Referer"] = f"https://{host}/"
    path = (site.get("category_path") or "").strip("/")
    items: list[Item] = []
    paso = 50

    def paginar(url_base: str) -> list[Item]:
        out, desde = [], 0
        scp = f"&sc={site['sc']}" if site.get("sc") else ""
        while desde < max_items:
            hasta = min(desde + paso - 1, max_items - 1)
            sep = "&" if "?" in url_base else "?"
            r = _get(session, f"{url_base}{sep}_from={desde}&_to={hasta}{scp}")
            if r is None:
                break
            try:
                data = r.json()
            except ValueError:
                break
            if not data:
                break
            out.extend(_parse_vtex_productos(data, host, tienda))
            if len(data) < paso:
                break
            desde += paso
            time.sleep(random.uniform(0.4, 0.9))
        return out

    base_api = f"https://{host}/api/catalog_system/pub/products/search"

    # Estrategia 0: IDs de categoría fijados a mano en la config (lo más fiable).
    # Si se fijan cat_ids, mandan: NO caemos al autodescubrimiento (evita traer
    # categorías equivocadas como las de farmacia).
    cat_ids_fijos = site.get("cat_ids")
    if cat_ids_fijos:
        for cid in cat_ids_fijos:
            items.extend(paginar(f"{base_api}?fq=C:/{cid}/"))
            if len(items) >= max_items:
                break
        return items

    # Estrategia 1: por path de categoria
    if path:
        items = paginar(f"{base_api}/{path}")
        if items:
            return items

    # Estrategia 1b: varios paths de categoría fijados a mano (category_paths).
    # Igual que cat_ids: si se fijan, mandan y NO caemos al autodescubrimiento.
    paths_fijos = site.get("category_paths")
    if paths_fijos:
        for pth in paths_fijos:
            items.extend(paginar(f"{base_api}/{pth.strip('/')}"))
            if len(items) >= max_items:
                break
        return items

    # Estrategia 2: autodescubrir categoria y filtrar por ID
    cat_ids = descubrir_categorias(session, host, site.get("cat_keywords", []))
    if cat_ids:
        log.info("  categorias detectadas: %s%s",
                 cat_ids[:12], " ..." if len(cat_ids) > 12 else "")
    for cid in cat_ids:
        items.extend(paginar(f"{base_api}?fq=C:/{cid}/"))
        if len(items) >= max_items:
            break
    if items:
        return items

    # Estrategia 3: busqueda por texto (ft)
    for q in site.get("ft", []):
        from urllib.parse import quote
        items.extend(paginar(f"{base_api}?ft={quote(q)}"))
        if items:
            log.info("  encontrado via busqueda de texto: '%s'", q)
            return items

    # Estrategia 4: catalogo completo (solo si el sitio lo permite)
    if site.get("buscar_todo"):
        items = paginar(base_api)

    return items


# --------------------------------------------------------------------------- #
# Unishop: SPA Vue server-rendered. Los productos vienen en el JSON
# window.__SERVER_DATA__ embebido en el HTML (_resolvedData.shop.products).
# Cada producto tiene una variante de display con pvPrice {price, prevPrice,
# discountPct}. Se pagina con ?page=N (24 por página). Es SSR, así que alcanza
# con requests (sin navegador).
# --------------------------------------------------------------------------- #
_RE_SERVER_DATA = re.compile(
    r"window\.__SERVER_DATA__\s*=\s*(\{.*?\})\s*;?\s*"
    r"(?:window\.__USER_DATA__|</script>)", re.S)


def _extraer_server_data(html: str) -> Optional[dict]:
    m = _RE_SERVER_DATA.search(html or "")
    if not m:
        return None
    try:
        return json.loads(m.group(1))
    except ValueError:
        return None


def _item_de_unishop(prod: dict, host: str, tienda: str) -> Optional[Item]:
    variants = prod.get("variants") or []
    if not variants:
        return None
    v = next((x for x in variants if x.get("forDisplay")), variants[0])
    # saltear los sin stock (la web los muestra como "No disponible en stock")
    stock = v.get("stock") or {}
    disponible = (stock.get("availability") == "InStock"
                  or stock.get("infiniteQty")
                  or (stock.get("qty") or 0) > 0)
    if not disponible:
        return None
    pv = v.get("pvPrice") or {}
    try:
        price = float(pv.get("price") or 0)
        prev = float(pv.get("prevPrice") or 0)
    except (TypeError, ValueError):
        return None
    if price <= 0:
        return None
    lista = prev if prev > price else price       # solo hay rebaja si prev>price
    marca = ((prod.get("brand") or {}).get("name") or "").strip()
    nombre = (prod.get("name") or "").strip()
    url_name = prod.get("urlName") or ""
    pid = v.get("productId") or ""
    url = (f"https://{host}/p/{url_name}/{pid}"
           if url_name and pid else f"https://{host}")
    # foto: imagen de la variante (imageIdx) en formato cuadrado (270 px); si no, la thumb
    imagen = ""
    imgs = prod.get("images") or []
    if imgs:
        idx = v.get("imageIdx") or 0
        im = imgs[idx] if 0 <= idx < len(imgs) else imgs[0]
        if isinstance(im, dict):
            imagen = _url_imagen(im.get("square", {}).get("fullUrl")
                                 or im.get("thumb", {}).get("fullUrl"), host)
    return Item(tienda=tienda, marca=marca, nombre=nombre,
                precio_lista=lista, precio_actual=price,
                descuento_pct=_dto(lista, price), fuente="api", url=url,
                presentacion=_presentacion("", nombre), imagen=imagen)


def scrap_unishop(session, site, max_items) -> list[Item]:
    host, tienda = site["host"], site["name"]
    base_url = site["url"]
    items: list[Item] = []
    page, last_page = 1, 1
    tope_pag = site.get("pag_max", 30)
    while page <= last_page and len(items) < max_items:
        sep = "&" if "?" in base_url else "?"
        r = _get(session, f"{base_url}{sep}page={page}",
                 headers={"Accept": "text/html,application/xhtml+xml,*/*"})
        if r is None:
            break
        data = _extraer_server_data(r.text)
        if not data:
            if page == 1:
                log.warning("  no encontré window.__SERVER_DATA__ (¿cambió el "
                            "sitio o no devolvió el HTML server-rendered?)")
            break
        shop = data.get("_resolvedData", {}).get("shop", {})
        prods = shop.get("products") or []
        last_page = min(shop.get("pagination", {}).get("lastPage", 1), tope_pag)
        if not prods:
            break
        for p in prods:
            it = _item_de_unishop(p, host, tienda)
            if it:
                items.append(it)
        page += 1
        time.sleep(random.uniform(0.4, 0.9))
    return items


# --------------------------------------------------------------------------- #
# Tiendanube (Nuvemshop): SSR. Cada card es un <div class="js-product-item...">
# con un atributo data-variants que trae el JSON de precios:
#   price_number, compare_at_price_number, promotional_price_number,
#   price_with_payment_discount_short (precio por transferencia).
# Distinguimos REBAJA real (compare_at/promotional) de TRANSFERENCIA (solo más
# barato pagando por transferencia) y las guardamos separadas. SSR -> requests.
# --------------------------------------------------------------------------- #
import html as _html

_RE_TN_ITEM = re.compile(r'<div\s+class="js-product-item-private product-item')   # \s+: desde oct-2026 el HTML parte el <div en varias líneas
_RE_TN_VARIANTS = re.compile(r'data-variants="(.*?)"', re.S)
_RE_TN_NOMBRE = re.compile(r'js-item-name[^>]*>\s*([^<]+?)\s*<')
_RE_TN_URL = re.compile(r'href="(https?://[^"]*/productos/[^"]+?)"')
_RE_TN_SRCSET = re.compile(r'data-srcset="([^"]+)"')


def _imagen_tiendanube(block: str, d: dict, host: str) -> str:
    """Foto de una tarjeta Tiendanube: la entrada mayor (<= 640 px) del data-srcset
    de la primera <img>; si no hay, `image_url` de la variante con el tamaño
    reducido (-1024-1024 -> -480-0)."""
    m = _RE_TN_SRCSET.search(block)
    if m:
        cand = []
        for parte in m.group(1).split(","):
            partes = parte.strip().split()
            if len(partes) == 2 and partes[1].endswith("w"):
                try:
                    cand.append((int(partes[1][:-1]), partes[0]))
                except ValueError:
                    pass
        cand = [c for c in cand if c[0] <= 640] or cand
        if cand:
            return _url_imagen(max(cand)[1], host)
    u = d.get("image_url") or ""
    return _url_imagen(re.sub(r"-1024-1024(\.\w+)$", r"-480-0\1", u), host)


def _num_ar(s) -> Optional[float]:
    """Convierte '$60.000,00' (formato AR) a 60000.0. None si no se puede."""
    if not s:
        return None
    s = re.sub(r"[^\d,.]", "", str(s)).replace(".", "").replace(",", ".")
    try:
        n = float(s)
        return n if n > 0 else None
    except ValueError:
        return None


def _items_de_tiendanube(html_text: str, host: str, tienda: str) -> list[Item]:
    out: list[Item] = []
    pos = [m.start() for m in _RE_TN_ITEM.finditer(html_text)]
    if not pos:
        return out
    pos.append(len(html_text))
    for i in range(len(pos) - 1):
        block = html_text[pos[i]:pos[i + 1]]
        mv = _RE_TN_VARIANTS.search(block)
        if not mv:
            continue
        try:
            arr = json.loads(_html.unescape(mv.group(1)))
        except ValueError:
            continue
        d = arr[0] if isinstance(arr, list) and arr else (
            arr if isinstance(arr, dict) else None)
        if not d:
            continue
        # saltear los sin stock
        if d.get("available") is False:
            continue
        if d.get("stock") is not None and (d.get("stock") or 0) <= 0:
            continue
        try:
            price = float(d.get("price_number") or 0)
        except (TypeError, ValueError):
            continue
        if price <= 0:
            continue
        cap = d.get("compare_at_price_number")
        promo = d.get("promotional_price_number")
        transfer = _num_ar(d.get("price_with_payment_discount_short"))
        cap = float(cap) if cap else None
        promo = float(promo) if promo else None

        # ¿rebaja real?
        if promo and promo < price:
            actual, lista, tipo = promo, price, "rebaja"
        elif cap and cap > price:
            actual, lista, tipo = price, cap, "rebaja"
        elif transfer and transfer < price:
            actual, lista, tipo = price, price, "transferencia"
        else:
            continue   # ni rebaja ni transferencia: no nos interesa

        ptransfer = transfer if (transfer and transfer < price) else None
        mn = _RE_TN_NOMBRE.search(block)
        nombre = _html.unescape(mn.group(1)).strip() if mn else ""
        if not nombre:
            continue
        mu = _RE_TN_URL.search(block)
        url = mu.group(1) if mu else f"https://{host}"
        out.append(Item(
            tienda=tienda, marca="", nombre=nombre,
            precio_lista=lista, precio_actual=actual,
            descuento_pct=_dto(lista, actual), fuente="tiendanube", url=url,
            tipo_descuento=tipo, precio_transferencia=ptransfer,
            presentacion=_presentacion("", nombre),
            imagen=_imagen_tiendanube(block, d, host)))
    return out


def scrap_tiendanube(session, site, max_items) -> list[Item]:
    host, tienda = site["host"], site["name"]
    base_url = site["url"]
    items: list[Item] = []
    vistos: set = set()
    page, tope_pag = 1, site.get("pag_max", 12)
    while page <= tope_pag and len(items) < max_items:
        sep = "&" if "?" in base_url else "?"
        r = _get(session, f"{base_url}{sep}page={page}",
                 headers={"Accept": "text/html,application/xhtml+xml,*/*"})
        if r is None:
            break
        nuevos = _items_de_tiendanube(r.text, host, tienda)
        if not nuevos:
            if page == 1:
                log.warning("  no encontré productos Tiendanube (data-variants) "
                            "en la página (¿cambió el sitio?)")
            break
        agregados = 0
        for it in nuevos:
            if it.clave not in vistos:
                vistos.add(it.clave)
                items.append(it)
                agregados += 1
        if agregados == 0:        # página repetida -> no hay más
            break
        page += 1
        time.sleep(random.uniform(0.4, 0.9))
    return items


# --------------------------------------------------------------------------- #
# HTML: motor heurístico (Playwright)
# --------------------------------------------------------------------------- #
JS_EXTRAER = r"""
(cfg) => {
  const requiereLink = !(cfg && cfg.requiereLink === false);
  function norm(s){return (s||'').toString().toLowerCase();}
  function parsePrecio(txt){
    if(!txt) return null;
    const m = txt.match(/\$\s*\d[\d.\s]*(?:,\d{1,2})?/);
    if(!m) return null;
    let s = m[0].replace(/[^\d.,]/g,'').replace(/\./g,'').replace(',', '.');
    const n = parseFloat(s);
    return (isFinite(n) && n > 0) ? n : null;
  }
  // basura: cuotas, impuestos, envio. Incluye patron "12 x $"
  const BASURA = /(cuota|inter[eé]s|env[ií]o|gratis|sin\s*cargo|c\/u|por\s*mes|mensual|ahora\s*\d+|sin\s*imp|s\/\s*imp|impuesto|\d+\s*x\s*\$)/i;
  // nombres que son botones / UI, no productos
  const UI = /^(aceptar|comprar|ver|ver\s*m[aá]s|agregar|a[ñn]adir|favorito|comparar|inicio|home|cuenta|ofertas?|buscar|men[uú]|cerrar)$/i;

  function esTachado(node){
    const tag = (node.tagName||'').toLowerCase();
    if(tag==='del'||tag==='s'||tag==='strike') return true;
    const cls = norm(node.getAttribute && node.getAttribute('class'));
    if(/old|list|regular|strike|tachad|antes|prev|compare|\bwas\b/.test(cls)) return true;
    try {
      const st = getComputedStyle(node);
      const td = (st.textDecorationLine || st.textDecoration || '');
      if(td.includes('line-through')) return true;
    } catch(e){}
    return false;
  }
  // texto del nodo + su padre directo (para detectar cuota/impuesto cercano)
  // texto del nodo + contenedores CHICOS hacia arriba (label+valor de ESE
  // precio). Para al toparse con un contenedor grande que mezcla varios precios.
  function ctxTexto(node){
    let t = node.innerText || '';
    let q = node.parentElement, i = 0;
    while(q && i < 3){
      const qt = q.innerText || '';
      if(qt.length > 55) break;   // contenedor con varios precios: no mezclar
      t = qt; q = q.parentElement; i++;
    }
    return t;
  }
  function esLinkProducto(href){
    return href && !/\/marcas?\//i.test(href) && !/\/categor/i.test(href)
                && !/\/coleccion/i.test(href) && !/\/brand/i.test(href);
  }
  // datos estructurados Schema.org Product (Tiendanube y otros los embeben
  // por card en <script type="application/ld+json">). Da marca y nombre limpios.
  function leerJsonLd(el){
    let q = el;
    for(let i=0; i<3 && q; i++, q=q.parentElement){
      const scripts = q.querySelectorAll('script[type="application/ld+json"]');
      for(const s of scripts){
        try {
          const data = JSON.parse(s.textContent);
          const arr = Array.isArray(data) ? data : [data];
          for(const d of arr){
            const tipo = d && d['@type'];
            const esProd = tipo==='Product' || tipo==='product'
                           || (Array.isArray(tipo) && tipo.indexOf('Product')>=0);
            if(esProd){
              let marca = '';
              if(d.brand) marca = (typeof d.brand==='string' ? d.brand : (d.brand.name||''));
              return { nombre: (d.name||'').trim(), marca: (marca||'').trim() };
            }
          }
        } catch(e){}
      }
    }
    return null;
  }
  // numero suelto (sin $) formato AR -> float
  function parseNum(s){
    if(!s) return null;
    s = s.replace(/[^\d.,]/g,'').replace(/\./g,'').replace(',', '.');
    const n = parseFloat(s);
    return (isFinite(n) && n>0) ? n : null;
  }
  // numeros que NO son el precio de venta: cuotas y precio sin impuestos.
  // Se buscan por su etiqueta en el texto lineal (robusto ante cualquier DOM).
  function preciosProhibidos(txt){
    const proh = new Set();
    let re = /(?:\d+\s*cuotas?[^$]*|cuotas?\s*de[^$]*|\d+\s*x\s*)\$\s*([\d.,]+)/gi, m;
    while((m = re.exec(txt))){ const n = parseNum(m[1]); if(n) proh.add(Math.round(n)); }
    re = /(?:sin\s*imp|s\/\s*imp|impuesto|imp\.\s*nac)[^$]*\$\s*([\d.,]+)/gi;
    while((m = re.exec(txt))){ const n = parseNum(m[1]); if(n) proh.add(Math.round(n)); }
    // precio especial por medio de pago: el numero PRECEDE a "transferencia"/"efectivo"
    re = /\$\s*([\d.,]+)[^$]*?(?:transferencia|efectivo|en\s*efvo)/gi;
    while((m = re.exec(txt))){ const n = parseNum(m[1]); if(n) proh.add(Math.round(n)); }
    return proh;
  }
  // precio sin impuestos identificado por la CLASE del contenedor
  // (ej. Parfumerie: <div class="final-price-excl-tax">). Robusto: no depende
  // del texto ni del timing de render.
  function esPrecioExcluido(node, tope){
    let q = node;
    for(let i=0; i<5 && q && q!==tope.parentElement; i++, q=q.parentElement){
      const cls = norm(q.getAttribute && q.getAttribute('class'));
      if(/excl.?tax|tax.?excl|sin.?imp|base-?price|s\/imp|impuesto/.test(cls)) return true;
    }
    return false;
  }

  const all = Array.from(document.querySelectorAll('*'));
  const cand = [];
  for(const el of all){
    const txt = el.innerText || '';
    if(!txt.includes('$')) continue;
    if(el.querySelector('a[href]')) { /* tiene link: ok */ }
    else if(!requiereLink && el.querySelector('img')) { /* sin link pero con imagen: ok */ }
    else continue;
    if(txt.length > 1400) continue;
    cand.push(el);
  }
  cand.sort((a,b) => a.getElementsByTagName('*').length - b.getElementsByTagName('*').length);

  const cards = [];
  for(const el of cand){
    let p = el, dentro = false;
    while(p){ if(p.dataset && p.dataset.cardused){ dentro = true; break; } p = p.parentElement; }
    if(dentro) continue;
    if(el.querySelector('[data-cardused]')) continue;

    const listaT = [], normales = [];
    const vistos = new Set();
    const prohibidos = preciosProhibidos(el.innerText || '');

    // Patron semantico estandar (WooCommerce y afines): <del>viejo</del> +
    // <ins>nuevo</ins>. Es inequivoco, asi que se lee directo y se marca como
    // tachado sin pasar por la heuristica de hojas, que se pierde el numero
    // cuando cuelga de <bdi>/<span> anidados. Se usa textContent (no innerText)
    // porque estos nodos suelen venir con aria-hidden.
    {
      const delEl = el.querySelector('del'), insEl = el.querySelector('ins');
      if(delEl && insEl){
        const pv = parsePrecio(delEl.textContent || '');
        const pn = parsePrecio(insEl.textContent || '');
        if(pv && pn && pv > pn){
          listaT.push(pv); normales.push(pn);
          vistos.add(pv); vistos.add(pn);   // que el bucle no los redescubra
        }
      }
    }

    for(const node of el.querySelectorAll('*')){
      if(node.childElementCount !== 0) continue;        // solo hojas
      const tnode = node.innerText || '';
      if(!tnode.includes('$')) continue;
      if(esPrecioExcluido(node, el)) continue;          // precio sin impuestos por clase
      if(BASURA.test(ctxTexto(node))) continue;         // descarta cuota/impuesto
      const pr = parsePrecio(tnode);
      if(!pr || vistos.has(pr)) continue;
      if(prohibidos.has(Math.round(pr))) continue;      // cuota / precio sin imp.
      vistos.add(pr);
      let tach = false, q = node;
      for(let i=0; i<3 && q && q!==el.parentElement; i++, q=q.parentElement){
        if(esTachado(q)){ tach = true; break; }
      }
      (tach ? listaT : normales).push(pr);
    }

    // badge de %
    let pct = null;
    {
      const m = (el.innerText||'').match(/(\d{1,3})\s*%/);
      if(m){ const v = parseInt(m[1],10);
        if(v>0 && v<100 && /%|off|desc|ahorr|menos|-\s*\d/.test(norm(el.innerText))) pct = v; }
    }

    if(listaT.length===0 && normales.length<2 && pct===null) continue;
    if(listaT.length===0 && normales.length===0) continue;

    // marca y nombre de producto (separados)
    function limpio(s){ return (s||'').replace(/\$\s*\d[\d.,\s]*/g,'').replace(/\s+/g,' ').trim(); }
    function txtSel(sel){ const e = el.querySelector(sel); return e ? (e.innerText||'').trim() : ''; }

    // 1) datos estructurados (JSON-LD) tienen prioridad: marca y nombre limpios
    let marca = '', nombre = '';
    const ld = leerJsonLd(el);
    if(ld){ marca = ld.marca || ''; nombre = ld.nombre || ''; }

    // 2) si falta, buscar marca en data-id / data-fs-brand-name / itemprop / class
    if(!marca)
      marca = txtSel('[data-fs-brand-name],[data-id="brand-name"],[data-id*="brand"],[itemprop="brand"],[class*="brand"],[class*="marca"]');

    // 3) si falta el nombre, selectores especificos evitando la marca
    if(!nombre || norm(nombre)===norm(marca))
      nombre = txtSel('[data-fs-pdp-product-title],[data-id="product-name"],[data-id*="product-name"],[class*="product-name"],[class*="productName"]') || nombre;
    if(!nombre || norm(nombre)===norm(marca)){
      for(const hh of el.querySelectorAll('h1,h2,h3,h4,[class*="title"],[class*="name"],[class*="nombre"]')){
        const tt = (hh.innerText||'').trim();
        if(tt.length>2 && norm(tt)!==norm(marca)){ nombre = tt; break; }
      }
    }
    if(!nombre || norm(nombre)===norm(marca)){
      for(const a of el.querySelectorAll('a[href]')){
        if(!esLinkProducto(a.getAttribute('href')||'')) continue;
        const tt = (a.getAttribute('title')||a.innerText||'').trim();
        if(tt && tt.length>2 && norm(tt)!==norm(marca)){ nombre = tt; break; }
      }
    }
    if(!nombre){ const im = el.querySelector('img[alt]'); if(im) nombre = (im.getAttribute('alt')||'').trim(); }

    marca = limpio(marca);
    nombre = limpio(nombre);
    // si el nombre arranca con la marca, sacarla para no duplicar
    if(marca && nombre && norm(nombre).startsWith(norm(marca))){
      nombre = nombre.slice(marca.length).replace(/^\s*[-|:·]?\s*/,'').trim();
    }
    const chequeo = nombre || marca;
    if(chequeo.length < 3 || UI.test(chequeo)) continue;

    // link de producto (evitar /marcas/ /categoria/)
    let href = '';
    const links = Array.from(el.querySelectorAll('a[href]'));
    for(const a of links){ if(esLinkProducto(a.href)){ href = a.href; break; } }
    if(!href && links.length) href = links[0].href;

    // foto: primera <img> de la tarjeta con una fuente real (lazy: data-src/srcset)
    function imgDe(root){
      for(const im of root.querySelectorAll('img')){
        const cand = [im.currentSrc, im.getAttribute('data-src'), im.getAttribute('data-lazy-src'),
                      im.getAttribute('data-original'), im.getAttribute('src')];
        const ss = im.getAttribute('data-srcset') || im.getAttribute('srcset') || '';
        if(ss){ const partes = ss.split(',').map(x => x.trim().split(/\s+/)[0]).filter(Boolean);
                if(partes.length) cand.push(partes[Math.floor(partes.length/2)]); }
        const u = cand.find(x => x && !/^data:/.test(x) && !/(placeholder|loading|spinner|blank|pixel|\.svg|logo)/i.test(x));
        if(u){ try { return new URL(u, document.baseURI).href; } catch(e){ return u; } }
      }
      return '';
    }
    let img = imgDe(el);
    if(!img){
      // la <img> puede colgar de un contenedor padre: subir mientras no aparezcan otros precios
      const n$ = ((el.innerText||'').match(/\$/g)||[]).length;
      let q = el.parentElement;
      for(let i=0; i<3 && q && !img; i++, q=q.parentElement){
        if(((q.innerText||'').match(/\$/g)||[]).length !== n$) break;
        img = imgDe(q);
      }
    }

    el.dataset.cardused = '1';
    cards.push({ marca: marca.slice(0,80), nombre: nombre.slice(0,160),
                 lista: listaT, normales: normales, pct: pct, href: href, img: img });
  }
  return cards;
}
"""


# JS: lee el título del producto en la página de detalle (h1 con nombre completo + variante)
JS_TITULO_DETALLE = """() => {
  const sels = [
    'h1[data-fs-pdp-product-title]', '[data-fs-pdp-product-title]',
    'h1[itemprop="name"]', 'h1[class*="product"]', 'h1'
  ];
  for (const sel of sels) {
    const el = document.querySelector(sel);
    if (el) { const t = (el.innerText || el.textContent || '').trim(); if (t.length > 3) return t; }
  }
  return '';
}"""

# JS: encuentra botones de selección de tamaño en una página de detalle
JS_VARIANTES_BOTONES = r"""() => {
  const regML = /\b\d+(?:[.,]\d+)?\s*ml\b/i;
  const seen = new Set();
  const botones = [];
  const sels = 'button,[role="radio"],[role="option"],[class*="sku"],[class*="variant"],[class*="size"],[class*="tamano"],label';
  document.querySelectorAll(sels).forEach(el => {
    const txt = (el.textContent || el.getAttribute('data-value') || '').trim();
    if (regML.test(txt) && txt.length < 30 && !seen.has(txt.toLowerCase())) {
      seen.add(txt.toLowerCase());
      botones.push(txt);
    }
  });
  return botones;
}"""

# JS: extrae precio actual y precio lista de una página de detalle tras seleccionar variante
JS_PRECIO_DETALLE = r"""() => {
  const toNum = s => {
    if (!s) return 0;
    s = (s || '').replace(/[^0-9,.]/g, '').trim();
    if (!s || s.length < 2) return 0;
    if (s.includes(',')) return parseFloat(s.replace(/\./g, '').replace(',', '.'));
    if (/\.\d{1,2}$/.test(s) && (s.match(/\./g)||[]).length === 1) return parseFloat(s);
    return parseFloat(s.replace(/\./g, ''));
  };
  let lista = 0, actual = 0;
  document.querySelectorAll('del,s,[class*="old"],[class*="anterior"],[class*="compare"],[class*="list-price"],[class*="price-compare"]').forEach(el => {
    const v = toNum(el.textContent);
    if (v > 1000 && v > lista) lista = v;
  });
  const promSels = [
    '.vtex-product-price-1-x-sellingPrice',
    '.vtex-product-price-1-x-spotPrice',
    '[class*="sellingPrice"]',
    '[class*="spotPrice"]',
    '[class*="sale-price"]',
    '[class*="current-price"]',
    '[class*="precio-oferta"]',
    '[class*="product-price"]',
    '.price-best-price',
  ];
  for (const sel of promSels) {
    const el = document.querySelector(sel);
    if (el && !el.closest('del') && !el.closest('s')) {
      const v = toNum(el.textContent);
      if (v > 1000) { actual = v; break; }
    }
  }
  return { actual, lista };
}"""


def _scrap_detalle_variantes(page, item_base: Item, fuentes_ok: list) -> list[Item]:
    """Visita la página de detalle de un producto y devuelve una lista de Items,
    uno por cada variante de tamaño con descuento. Si no hay variantes (o falla),
    devuelve el item original."""
    if not item_base.url or item_base.url.endswith("/"):
        return [item_base]

    try:
        page.goto(item_base.url, wait_until="domcontentloaded", timeout=30000)
        page.wait_for_timeout(1200)
    except Exception as e:
        log.debug("  variantes_detalle: error abriendo %s: %s", item_base.url, e)
        return [item_base]

    # Leer el h1 de la página de detalle: contiene el nombre completo con variante
    # p.ej. "D By Diesel EDT 100 ml + Weekend Bag" en lugar de solo "D By Diesel EDT"
    titulo_detalle = ""
    try:
        titulo_detalle = (page.evaluate(JS_TITULO_DETALLE) or "").strip()
    except Exception:
        pass

    try:
        botones = page.evaluate(JS_VARIANTES_BOTONES)
    except Exception:
        return [item_base]

    # Sin botones de talla: intentar usar el h1 para mejorar el nombre
    if not botones:
        nombre_h1 = _nombre_con_tamano(item_base.nombre, titulo_detalle) if titulo_detalle else ""
        if nombre_h1 and nombre_h1 != item_base.nombre:
            presentacion = _presentacion(titulo_detalle, nombre_h1)
            return [Item(**{**asdict(item_base), "nombre": nombre_h1, "presentacion": presentacion})]
        return [item_base]

    if len(botones) == 1:
        # Preferir h1 (más completo) sobre solo el texto del botón
        nombre_h1 = _nombre_con_tamano(item_base.nombre, titulo_detalle) if titulo_detalle else ""
        nombre    = nombre_h1 if (nombre_h1 and nombre_h1 != item_base.nombre) \
                    else _nombre_con_tamano(item_base.nombre, botones[0])
        presentacion = _presentacion(botones[0], nombre)
        return [Item(**{**asdict(item_base), "nombre": nombre, "presentacion": presentacion})]

    variantes: list[Item] = []
    for texto_btn in botones:
        try:
            locator = page.locator(
                "button, [role='radio'], [role='option'], label"
            ).filter(has_text=re.compile(re.escape(texto_btn), re.I)).first
            locator.click(timeout=5000)
            page.wait_for_timeout(900)

            p = page.evaluate(JS_PRECIO_DETALLE)
            actual = float(p.get("actual") or 0)
            lista  = float(p.get("lista") or 0)

            if not actual or lista <= actual:
                continue
            fuente = "tachado"
            if fuente not in fuentes_ok:
                continue
            dto = _dto(lista, actual)
            if dto < 0.5:
                continue

            # Tras el click re-leer el h1: refleja la variante seleccionada
            titulo_var = ""
            try:
                titulo_var = (page.evaluate(JS_TITULO_DETALLE) or "").strip()
            except Exception:
                pass
            nombre_h1    = _nombre_con_tamano(item_base.nombre, titulo_var) if titulo_var else ""
            nombre       = nombre_h1 if (nombre_h1 and nombre_h1 != item_base.nombre) \
                           else _nombre_con_tamano(item_base.nombre, texto_btn)
            presentacion = _presentacion(texto_btn, nombre)
            variantes.append(Item(
                tienda=item_base.tienda,
                marca=item_base.marca,
                nombre=nombre,
                precio_lista=lista,
                precio_actual=actual,
                descuento_pct=dto,
                fuente=fuente,
                url=item_base.url,
                tipo_descuento=item_base.tipo_descuento,
                genero=item_base.genero,
                presentacion=presentacion,
                imagen=item_base.imagen,
            ))
        except Exception as e:
            log.debug("  variantes_detalle: fallo en '%s': %s", texto_btn, e)
            continue

    return variantes if variantes else [item_base]


def _urls_de_sitio(site) -> list[str]:
    """Construye la lista de URLs a visitar: soporta varias URLs fijas
    (campo 'urls') y/o paginación por parametro (pag_param)."""
    from urllib.parse import urlsplit, urlunsplit, parse_qs, urlencode
    bases = site.get("urls") or ([site["url"]] if site.get("url") else [])
    if not bases:
        return []
    n = site.get("pag_max", 6)
    base0 = site.get("pag_base", 1)

    # Paginacion por PATH (WooCommerce: /categoria/page/2/). 'pag_path' es la
    # plantilla del segmento a agregar; la primera pagina usa la URL tal cual.
    ppath = site.get("pag_path")
    if ppath:
        out = []
        for base in bases:
            for i in range(base0, base0 + n):
                out.append(base if i == base0
                           else base.rstrip("/") + "/" + ppath.format(n=i))
        return out

    pp = site.get("pag_param")
    if not pp:
        return bases
    out = []
    for base in bases:
        partes = urlsplit(base)
        q = parse_qs(partes.query)
        for i in range(base0, base0 + n):
            q[pp] = [str(i)]
            out.append(urlunsplit((partes.scheme, partes.netloc, partes.path,
                                   urlencode(q, doseq=True), partes.fragment)))
    return out


# Fuentes aceptadas por defecto para sitios HTML. Solo "tachado" garantiza
# un descuento real (precio explícitamente tachado en el DOM). "dos_precios"
# y "badge_%" producen falsos positivos: el badge aparece en el listado pero
# el producto no tiene rebaja al entrar al link.
_FUENTES_HTML_DEFAULT = ["tachado"]


def _cards_a_items(cards, tienda, url_default, fuentes_validas=None) -> list[Item]:
    fuentes_ok = fuentes_validas if fuentes_validas is not None else _FUENTES_HTML_DEFAULT
    items: list[Item] = []
    for c in cards:
        listaT = sorted(set(c.get("lista") or []))
        normales = sorted(set(c.get("normales") or []))
        pct = c.get("pct")
        marca = (c.get("marca") or "").strip()
        nombre = (c.get("nombre") or "").strip() or marca
        href = c.get("href") or url_default

        actual = lista = 0.0
        fuente = ""
        if listaT and normales:
            # el actual con impuestos es el MAYOR de los normales;
            # el "precio sin impuestos" (si se colo) siempre es menor.
            actual, lista, fuente = max(normales), max(listaT), "tachado"
        elif len(normales) >= 2:
            actual, lista, fuente = normales[0], normales[-1], "dos_precios"
        elif len(normales) == 1 and pct:
            actual = normales[0]
            lista = round(actual / (1 - pct / 100.0), 2)
            fuente = "badge_%"
        else:
            continue
        if fuente not in fuentes_ok:
            continue
        if lista <= actual:
            continue
        dto = _dto(lista, actual)
        if pct and fuente in ("tachado", "dos_precios") and abs(dto - pct) > 3:
            dto = float(pct)
        items.append(Item(
            tienda=tienda, marca=marca, nombre=nombre,
            precio_lista=lista, precio_actual=actual,
            descuento_pct=dto, fuente=fuente, url=href,
            imagen=_url_imagen(c.get("img")),
        ))
    return items


def scrap_html(page, site) -> list[Item]:
    tienda = site["name"]
    urls = _urls_de_sitio(site)
    scrolls = site.get("scrolls", 10)
    fuentes_ok = site.get("fuentes_validas") or _FUENTES_HTML_DEFAULT
    variantes_detalle = site.get("variantes_detalle", True)
    vistos: set = set()
    listing_items: list[Item] = []

    # ── Paso 1: recorrer páginas del listado ─────────────────────────────────
    for idx, url in enumerate(urls):
        try:
            page.goto(url, wait_until="domcontentloaded", timeout=45000)
        except Exception as e:  # noqa: BLE001
            log.warning("  no se pudo abrir %s: %s", url, e)
            continue
        try:
            page.wait_for_function(
                "() => ((document.body.innerText||'').match(/\\$/g)||[]).length > 5",
                timeout=10000)
        except Exception:  # noqa: BLE001
            pass
        for _ in range(scrolls):
            page.mouse.wheel(0, 4000)
            page.wait_for_timeout(800)
        try:
            cards = page.evaluate(JS_EXTRAER, {"requiereLink": site.get("requiere_link", True)})
        except Exception as e:  # noqa: BLE001
            log.warning("  fallo extrayendo DOM en %s: %s", url, e)
            continue

        nuevos = 0
        for it in _cards_a_items(cards, tienda, url, fuentes_ok):
            if it.clave not in vistos:
                vistos.add(it.clave)
                listing_items.append(it)
                nuevos += 1
        log.info("  pagina %d: %d tarjetas, %d nuevos (total %d)",
                 idx + 1, len(cards), nuevos, len(listing_items))

        if (site.get("pag_param") or site.get("pag_path"))                 and idx > 0 and nuevos == 0:
            break

    if not variantes_detalle:
        return listing_items

    # ── Paso 2: visitar páginas de detalle para expandir variantes de tamaño ─
    log.info("  expandiendo variantes en %d productos con descuento...", len(listing_items))
    items: list[Item] = []
    vistos_final: set = set()
    for it in listing_items:
        subs = _scrap_detalle_variantes(page, it, fuentes_ok)
        for sub in subs:
            if sub.clave not in vistos_final:
                vistos_final.add(sub.clave)
                items.append(sub)
    log.info("  total tras expandir variantes: %d", len(items))
    return items


# --------------------------------------------------------------------------- #
# Comparación con la corrida anterior (emparejado por NOMBRE de producto)
# --------------------------------------------------------------------------- #
def _clave_comp(nombre: str) -> str:
    """Clave para emparejar el MISMO producto entre corridas: nombre
    normalizado (sin acentos, minúsculas, espacios colapsados). NO usa la
    tienda: si el producto mejoró en cualquier perfumería, igual se compara."""
    return re.sub(r"\s+", " ", _norm(nombre)).strip()


def _ruta_con_timestamp(ruta: str, ahora=None) -> str:
    """Mete el archivo en una subcarpeta 'excel_perfumes' (en el mismo
    directorio que la ruta indicada) y le agrega _fecha_hora al nombre,
    para no pisar el de ejecuciones anteriores.
    Ej: descuentos_perfumes.xlsx
        -> excel_perfumes/descuentos_perfumes_16-6-2026_19-46-32.xlsx
    """
    import datetime
    carpeta = os.path.join(os.path.dirname(os.path.abspath(ruta)), "excel_perfumes")
    os.makedirs(carpeta, exist_ok=True)
    base, ext = os.path.splitext(os.path.basename(ruta))
    if ahora is None:
        ahora = datetime.datetime.now()
    sello = (f"{ahora.day}-{ahora.month}-{ahora.year}_"
             f"{ahora.hour:02d}-{ahora.minute:02d}-{ahora.second:02d}")
    return os.path.join(carpeta, f"{base}_{sello}{ext}")


def _excel_mas_reciente(carpeta: str) -> Optional[str]:
    """Ruta del .xlsx más reciente de la carpeta (por fecha de modificación),
    ignorando temporales de Excel (~$...). None si no hay ninguno."""
    if not os.path.isdir(carpeta):
        return None
    cand = [f for f in glob.glob(os.path.join(carpeta, "*.xlsx"))
            if not os.path.basename(f).startswith("~$")]
    return max(cand, key=os.path.getmtime) if cand else None


def _cargar_descuentos_previos(ruta: Optional[str]) -> dict:
    """Lee la hoja 'Descuentos' de un Excel anterior y arma, POR NOMBRE de
    producto (sin importar la tienda), el MEJOR descuento y el MENOR precio
    de la corrida anterior:
        {nombre: {'pct': mejor_descuento, 'precio': menor_precio_actual}}
    El % se RECALCULA desde precio lista (E) y precio actual (F), que son
    valores guardados (la columna A es fórmula)."""
    previos: dict = {}
    if not ruta or not os.path.exists(ruta):
        return previos
    try:
        from openpyxl import load_workbook
        wb = load_workbook(ruta, read_only=True, data_only=True)
    except Exception as e:  # noqa: BLE001
        log.warning("  no pude leer el Excel previo (%s): %s", ruta, e)
        return previos
    if "Descuentos" not in wb.sheetnames:
        wb.close()
        return previos
    for row in wb["Descuentos"].iter_rows(min_row=3, values_only=True):
        if not row or len(row) < 6:
            continue
        nombre, lista, actual = row[3], row[4], row[5]
        if not nombre or lista is None or actual is None:
            continue
        try:
            lista = float(lista)
            actual = float(actual)
        except (TypeError, ValueError):
            continue
        pct = _dto(lista, actual)
        k = _clave_comp(str(nombre))
        ant = previos.get(k)
        if ant is None:
            previos[k] = {"pct": pct, "precio": actual}
        else:
            ant["pct"] = max(ant["pct"], pct)
            ant["precio"] = min(ant["precio"], actual)
    wb.close()
    return previos


def _mejoro(it: "Item", previos: dict, eps: float = 0.1) -> bool:
    """True si el producto ya estaba en la corrida anterior y AHORA es mejor
    negocio que antes: mejor descuento % (con margen eps para evitar ruido de
    redondeo) O precio más bajo que el menor de antes. No importa la tienda."""
    ant = previos.get(_clave_comp(it.nombre))
    if ant is None:
        return False
    mejor_dto = it.descuento_pct > ant["pct"] + eps
    mas_barato = round(it.precio_actual, 2) < round(ant["precio"], 2)
    return mejor_dto or mas_barato


def _es_nuevo(it: "Item", previos: dict) -> bool:
    """True si el producto NO figuraba (por nombre) en la corrida anterior."""
    return _clave_comp(it.nombre) not in previos


# --------------------------------------------------------------------------- #
# Export a Excel
# --------------------------------------------------------------------------- #
def _guardar_seguro(wb, ruta: str) -> str:
    """Guarda el workbook. Si el archivo esta abierto/bloqueado (tipico en
    Windows con Excel abierto), guarda con sufijo de fecha para no perder
    el trabajo. Devuelve la ruta finalmente usada."""
    import datetime
    try:
        wb.save(ruta)
        return ruta
    except PermissionError:
        base, ext = os.path.splitext(ruta)
        alt = f"{base}_{datetime.datetime.now():%Y%m%d_%H%M%S}{ext}"
        wb.save(alt)
        log.warning("No pude escribir '%s' (probablemente lo tenes abierto "
                    "en Excel). Lo guarde como '%s'. Cerra el archivo y volve "
                    "a correr si queres el nombre original.", ruta, alt)
        return alt


def escribir_excel(items: list[Item], ruta: str, previos: dict | None = None) -> str:
    if not OPENPYXL_OK:
        log.error("Falta openpyxl: pip install openpyxl")
        return ruta

    previos = previos or {}
    AZUL = "1F3864"; AZUL_CLARO = "DCE6F1"; GRIS = "F2F2F2"
    VERDE = "92D050"; AMARILLO = "FFFF00"; FUENTE = "Arial"
    borde = Border(*[Side(style="thin", color="D9D9D9")] * 4)
    wb = Workbook()

    ws = wb.active
    ws.title = "Descuentos"
    headers = ["Descuento", "Tienda", "Marca", "Producto", "Precio lista",
               "Precio actual", "Ahorro", "Fuente", "Link"]
    ws.merge_cells("A1:I1")
    t = ws["A1"]
    t.value = f"Perfumes con descuento · {len(items)} productos · de mayor a menor"
    t.font = Font(name=FUENTE, size=13, bold=True, color="FFFFFF")
    t.fill = PatternFill("solid", fgColor=AZUL)
    t.alignment = Alignment(horizontal="left", vertical="center", indent=1)
    ws.row_dimensions[1].height = 26
    for col, h in enumerate(headers, start=1):
        c = ws.cell(row=2, column=col, value=h)
        c.font = Font(name=FUENTE, bold=True, color="FFFFFF")
        c.fill = PatternFill("solid", fgColor=AZUL)
        c.alignment = Alignment(horizontal="center", vertical="center")
        c.border = borde

    r = 3
    filas_mejoradas: set[int] = set()   # verde: ya estaba y ahora es mejor negocio
    filas_nuevas: set[int] = set()      # amarillo: no estaba en la corrida anterior
    for it in items:
        if previos:                      # solo comparar si hay un Excel anterior
            if _es_nuevo(it, previos):
                filas_nuevas.add(r)
            elif _mejoro(it, previos):
                filas_mejoradas.add(r)
        ws.cell(row=r, column=1, value=f"=(E{r}-F{r})/E{r}")
        ws.cell(row=r, column=2, value=it.tienda)
        ws.cell(row=r, column=3, value=it.marca)
        ws.cell(row=r, column=4, value=it.nombre)
        ws.cell(row=r, column=5, value=round(it.precio_lista, 2))
        ws.cell(row=r, column=6, value=round(it.precio_actual, 2))
        ws.cell(row=r, column=7, value=f"=E{r}-F{r}")
        ws.cell(row=r, column=8, value=it.fuente)
        link = ws.cell(row=r, column=9, value="ver producto")
        if it.url:
            link.hyperlink = it.url
            link.font = Font(name=FUENTE, color="0563C1", underline="single")
        r += 1
    ultima = r - 1

    for row in range(3, r):
        ws.cell(row=row, column=1).number_format = "0.0%"
        for col in (5, 6, 7):
            ws.cell(row=row, column=col).number_format = '"$"#,##0'
        if row in filas_mejoradas:
            for col in range(2, 10):   # B..I en VERDE (mejor negocio); la A (Descuento) queda igual
                ws.cell(row=row, column=col).fill = PatternFill("solid", fgColor=VERDE)
        elif row in filas_nuevas:
            for col in range(2, 10):   # B..I en AMARILLO (producto nuevo); la A queda igual
                ws.cell(row=row, column=col).fill = PatternFill("solid", fgColor=AMARILLO)
        elif row % 2 == 1:
            for col in range(1, 10):
                ws.cell(row=row, column=col).fill = PatternFill("solid", fgColor=GRIS)
        for col in range(1, 10):
            c = ws.cell(row=row, column=col)
            c.border = borde
            if c.font.name != FUENTE:
                c.font = Font(name=FUENTE)
        ws.cell(row=row, column=1).alignment = Alignment(horizontal="center")
        ws.cell(row=row, column=2).alignment = Alignment(horizontal="center")
        ws.cell(row=row, column=8).alignment = Alignment(horizontal="center")

    if ultima >= 3:
        ws.conditional_formatting.add(
            f"A3:A{ultima}",
            ColorScaleRule(start_type="min", start_color="FFFFFF",
                           end_type="max", end_color="2E7D32"))
    for col, w in {"A": 11, "B": 12, "C": 16, "D": 40, "E": 13,
                   "F": 13, "G": 12, "H": 12, "I": 14}.items():
        ws.column_dimensions[col].width = w
    ws.freeze_panes = "A3"
    if ultima >= 2:
        ws.auto_filter.ref = f"A2:I{ultima}"

    # Resumen por tienda
    ws2 = wb.create_sheet("Resumen por tienda")
    tiendas = sorted({it.tienda for it in items})
    ws2.merge_cells("A1:E1")
    tt = ws2["A1"]
    tt.value = "Resumen por tienda"
    tt.font = Font(name=FUENTE, size=13, bold=True, color="FFFFFF")
    tt.fill = PatternFill("solid", fgColor=AZUL)
    tt.alignment = Alignment(horizontal="left", vertical="center", indent=1)
    for col, h in enumerate(["Tienda", "Productos", "Descuento promedio",
                             "Descuento máximo", "Ahorro total"], start=1):
        c = ws2.cell(row=2, column=col, value=h)
        c.font = Font(name=FUENTE, bold=True, color="FFFFFF")
        c.fill = PatternFill("solid", fgColor=AZUL)
        c.alignment = Alignment(horizontal="center", vertical="center")
        c.border = borde
    rng_t = f"Descuentos!$B$3:$B${ultima}"
    rng_d = f"Descuentos!$A$3:$A${ultima}"
    rng_a = f"Descuentos!$G$3:$G${ultima}"
    rr = 3
    for t_name in tiendas:
        ws2.cell(row=rr, column=1, value=t_name)
        ws2.cell(row=rr, column=2, value=f'=COUNTIF({rng_t},A{rr})')
        ws2.cell(row=rr, column=3, value=f'=AVERAGEIF({rng_t},A{rr},{rng_d})')
        ws2.cell(row=rr, column=4, value=f'=SUMPRODUCT(MAX(({rng_t}=A{rr})*{rng_d}))')
        ws2.cell(row=rr, column=5, value=f'=SUMIF({rng_t},A{rr},{rng_a})')
        ws2.cell(row=rr, column=3).number_format = "0.0%"
        ws2.cell(row=rr, column=4).number_format = "0.0%"
        ws2.cell(row=rr, column=5).number_format = '"$"#,##0'
        for col in range(1, 6):
            ws2.cell(row=rr, column=col).border = borde
            ws2.cell(row=rr, column=col).font = Font(name=FUENTE)
        rr += 1
    ws2.cell(row=rr, column=1, value="TOTAL").font = Font(name=FUENTE, bold=True)
    ws2.cell(row=rr, column=2, value=f"=SUM(B3:B{rr-1})").font = Font(name=FUENTE, bold=True)
    ws2.cell(row=rr, column=2).number_format = "#,##0"
    ws2.cell(row=rr, column=5, value=f"=SUM(E3:E{rr-1})").font = Font(name=FUENTE, bold=True)
    ws2.cell(row=rr, column=5).number_format = '"$"#,##0'
    for col in range(1, 6):
        ws2.cell(row=rr, column=col).fill = PatternFill("solid", fgColor=AZUL_CLARO)
        ws2.cell(row=rr, column=col).border = borde
    for col, w in {"A": 14, "B": 12, "C": 20, "D": 18, "E": 14}.items():
        ws2.column_dimensions[col].width = w
    ws2.freeze_panes = "A3"

    return _guardar_seguro(wb, ruta)


# --------------------------------------------------------------------------- #
# Orquestación
# --------------------------------------------------------------------------- #
def rellenar_imagenes(sitios: list, max_items: int) -> int:
    """Completa `productos.imagen` (solo donde está vacía) leyendo los catálogos
    VTEX / Unishop / Tiendanube (HTTP, sin navegador). NO crea corrida ni
    observaciones ni Excel: es un parche único para productos que se guardaron
    antes de que el scraper capturara la foto (los sitios HTML la completan solos
    en la próxima corrida normal). Devuelve la cantidad de fotos cargadas."""
    try:
        import pymysql
    except ImportError:
        log.error("Falta PyMySQL: pip install pymysql")
        return 0
    try:
        conn = pymysql.connect(charset="utf8mb4", autocommit=False, **DB_CONFIG)
    except Exception as e:  # noqa: BLE001
        log.error("No pude conectar a MySQL (%s).", e)
        return 0
    total = 0
    try:
        for site in sitios:
            if site["platform"] not in ("vtex", "unishop", "tiendanube"):
                continue
            log.info("== %s (%s) ==", site["name"], site["platform"])
            try:
                session = nueva_session()
                if site["platform"] == "vtex":
                    prods = scrap_vtex(session, site, max_items)
                elif site["platform"] == "unishop":
                    prods = scrap_unishop(session, site, max_items)
                else:
                    prods = scrap_tiendanube(session, site, max_items)
            except Exception as e:  # noqa: BLE001
                log.error("  fallo: %s", e)
                continue
            filas = {}
            for it in prods:
                if it.imagen:
                    filas[_nombre_norm(it.nombre)] = it.imagen
            with conn.cursor() as cur:
                cur.executemany(
                    "UPDATE productos p JOIN tiendas t ON t.id = p.tienda_id "
                    "SET p.imagen = %s "
                    "WHERE t.nombre = %s AND p.nombre_norm = %s "
                    "AND (p.imagen IS NULL OR p.imagen = '')",
                    [(img, site["name"], nn) for nn, img in filas.items()])
                n = cur.rowcount
            conn.commit()
            total += max(n, 0)
            log.info("  %d fotos leídas, %d productos completados", len(filas), max(n, 0))
            time.sleep(random.uniform(0.8, 1.6))
    finally:
        conn.close()
    log.info("Listo: %d productos con foto nueva.", total)
    return total


def main(argv=None) -> int:
    ap = argparse.ArgumentParser(description="Scraper maestro de descuentos (perfumerías AR)")
    ap.add_argument("-o", "--output", default="descuentos_perfumes.xlsx")
    ap.add_argument("--solo", nargs="*", metavar="TIENDA")
    ap.add_argument("--min-descuento", type=float, default=0.0)
    ap.add_argument("--max-descuento", type=float, default=90.0,
                    help="descarta descuentos mayores a este %% (falsos positivos)")
    ap.add_argument("--max-items", type=int, default=800)
    ap.add_argument("--no-playwright", action="store_true")
    ap.add_argument("--headful", action="store_true")
    ap.add_argument("--no-db", action="store_true",
                    help="no guardar en MySQL (solo genera el Excel)")
    ap.add_argument("--force-db", action="store_true",
                    help="forzar guardado en MySQL aun con --solo o con una "
                         "corrida incompleta (por defecto --solo NO guarda, "
                         "para no crear corridas parciales)")
    ap.add_argument("--umbral-tiendas", type=float, default=UMBRAL_TIENDAS_OK,
                    metavar="FRACCION",
                    help="fraccion minima de perfumerias que deben traer datos "
                         "para guardar la corrida en MySQL (default %(default)s)")
    ap.add_argument("--sin-chequeo-red", action="store_true",
                    help="no esperar conectividad antes de empezar")
    ap.add_argument("--rellenar-imagenes", action="store_true",
                    help="solo completa productos.imagen vacía (sitios VTEX/Unishop/"
                         "Tiendanube, sin navegador); no crea corrida ni Excel")
    args = ap.parse_args(argv)

    # Sin red no tiene sentido arrancar: el barrido saldria vacio.
    if not args.sin_chequeo_red and not esperar_red():
        log.error("No hay conectividad despues de varios intentos. Aborto sin "
                  "scrapear (no genero Excel ni corrida en la base).")
        return 2

    if args.rellenar_imagenes:
        quiero = {x.lower() for x in (args.solo or [])}
        rellenar_imagenes([x for x in SITES if not x.get("disabled")
                           and (not quiero or x["name"] in quiero)], args.max_items)
        return 0

    import datetime as _dt
    ahora = _dt.datetime.now()   # misma marca de tiempo para Excel y corrida DB

    # Una corrida en la base representa un barrido COMPLETO. Con --solo el
    # barrido es parcial, así que por defecto NO guardamos (no queremos un
    # snapshot con una sola tienda, que ensuciaría la "foto más reciente" del
    # dashboard). Se puede forzar con --force-db.
    if args.solo and not args.no_db and not args.force_db:
        log.info("Modo --solo: no guardo en MySQL para no crear una corrida "
                 "parcial (el dashboard muestra el último snapshot). Si igual "
                 "querés guardarla, agregá --force-db.")
        args.no_db = True

    sitios = SITES
    if args.solo:
        quiero = {s.lower() for s in args.solo}
        sitios = [s for s in SITES if s["name"] in quiero]
        if not sitios:
            log.error("No coincide ninguna tienda con %s", args.solo)
            return 1
    else:
        # en corridas completas salteamos las tiendas marcadas como "disabled"
        # (las que todavía no están afinadas). Con --solo igual se pueden probar.
        sitios = [s for s in sitios if not s.get("disabled")]

    hay_html = any(s["platform"] == "html" for s in sitios) and not args.no_playwright
    todos: list[Item] = []
    reporte_sitios: list[dict] = []

    # VTEX, Unishop y Tiendanube (requests, sin navegador)
    for site in sitios:
        if site["platform"] not in ("vtex", "unishop", "tiendanube"):
            continue
        log.info("== %s (%s) ==", site["name"], site["platform"])
        t0 = time.time()
        try:
            session = nueva_session()
            if site["platform"] == "vtex":
                prods = scrap_vtex(session, site, args.max_items)
            elif site["platform"] == "unishop":
                prods = scrap_unishop(session, site, args.max_items)
            else:
                prods = scrap_tiendanube(session, site, args.max_items)
            g = site.get("genero", "masculino")
            for p in prods:
                p.genero = g
            con = sum(1 for p in prods if p.descuento_pct > 0)
            transf = sum(1 for p in prods if p.tipo_descuento == "transferencia")
            if transf:
                log.info("  %d productos (%d con descuento, %d por transferencia)",
                         len(prods), con, transf)
            else:
                log.info("  %d productos (%d con descuento)", len(prods), con)
            todos.extend(prods)
            estado = "vacio" if len(prods) == 0 else "ok"
            mensaje = ("0 productos: posible error de scraping"
                       if len(prods) == 0 else None)
            _log_key = f"{site['name']}_{site.get('genero', 'masculino')}"
            reporte_sitios.append({
                "tienda": _log_key, "plataforma": site["platform"],
                "total": len(prods), "con": con, "estado": estado,
                "mensaje": mensaje, "segundos": round(time.time() - t0, 2)})
        except Exception as e:  # noqa: BLE001
            log.error("  fallo: %s", e)
            _log_key = f"{site['name']}_{site.get('genero', 'masculino')}"
            reporte_sitios.append({
                "tienda": _log_key, "plataforma": site["platform"],
                "total": 0, "con": 0, "estado": "error",
                "mensaje": str(e)[:500], "segundos": round(time.time() - t0, 2)})
        time.sleep(random.uniform(0.8, 1.6))

    # HTML (Playwright)
    if hay_html:
        try:
            from playwright.sync_api import sync_playwright
        except ImportError:
            log.error("Falta Playwright para los sitios HTML: "
                      "pip install playwright && playwright install chromium")
            sync_playwright = None
        if sync_playwright:
            with sync_playwright() as p:
                browser = p.chromium.launch(headless=not args.headful)
                for site in sitios:
                    if site["platform"] != "html":
                        continue
                    log.info("== %s (html) ==", site["name"])
                    page = None
                    t0 = time.time()
                    try:
                        # new_page DENTRO del try: si falla (browser caído) se
                        # registra el sitio con error en vez de abortar la corrida
                        page = browser.new_page(
                            user_agent=random.choice(USER_AGENTS), locale="es-AR")
                        prods = scrap_html(page, site)
                        g = site.get("genero", "masculino")
                        for p in prods:
                            p.genero = g
                        con = sum(1 for pr in prods if pr.descuento_pct > 0)
                        log.info("  %d productos (%d con descuento)", len(prods), con)
                        todos.extend(prods)
                        estado = "vacio" if len(prods) == 0 else "ok"
                        mensaje = ("0 productos: posible error de scraping"
                                   if len(prods) == 0 else None)
                        _log_key = f"{site['name']}_{site.get('genero', 'masculino')}"
                        reporte_sitios.append({
                            "tienda": _log_key, "plataforma": "html",
                            "total": len(prods), "con": con, "estado": estado,
                            "mensaje": mensaje,
                            "segundos": round(time.time() - t0, 2)})
                    except Exception as e:  # noqa: BLE001
                        log.error("  fallo: %s", e)
                        _log_key = f"{site['name']}_{site.get('genero', 'masculino')}"
                        reporte_sitios.append({
                            "tienda": _log_key, "plataforma": "html",
                            "total": 0, "con": 0, "estado": "error",
                            "mensaje": str(e)[:500],
                            "segundos": round(time.time() - t0, 2)})
                    finally:
                        if page is not None:
                            try:
                                page.close()
                            except Exception:  # noqa: BLE001
                                pass
                    time.sleep(random.uniform(1.0, 2.0))
                browser.close()

    # dedup + filtro + orden
    unicos: dict[tuple, Item] = {}
    for it in todos:
        unicos[it.clave] = it
    # res = REBAJAS reales -> Excel + comparación (lo de siempre).
    res = [it for it in unicos.values()
           if it.tipo_descuento != "transferencia"
           and max(args.min_descuento, 0.01) <= it.descuento_pct <= args.max_descuento]
    res.sort(key=lambda i: i.descuento_pct, reverse=True)
    # transferencia = sin rebaja real, solo más barato por transferencia.
    # NO va al Excel (no es un descuento), pero SÍ a la base, marcado aparte.
    transfer = [it for it in unicos.values()
                if it.tipo_descuento == "transferencia"]
    res_db = res + transfer

    # buscar el Excel más reciente en la carpeta destino para comparar
    carpeta_destino = os.path.join(
        os.path.dirname(os.path.abspath(args.output)), "excel_perfumes")
    previo = _excel_mas_reciente(carpeta_destino)
    if previo:
        log.info("Comparando contra corrida anterior: %s", os.path.basename(previo))
    previos = _cargar_descuentos_previos(previo)

    ruta_destino = _ruta_con_timestamp(args.output, ahora)
    ruta_final = escribir_excel(res, ruta_destino, previos)

    # alertas por sitio (aunque no se use MySQL): vacíos o con error
    problemas = [r for r in reporte_sitios if r["estado"] != "ok"]
    if problemas:
        for r in problemas:
            log.warning("ALERTA %s [%s]: %s (%d encontrados)",
                        r["tienda"], r["estado"],
                        r.get("mensaje") or "-", r.get("total", 0))

    # ── Control de calidad: no pisar la ultima corrida buena con una rota ──
    # El dashboard muestra siempre el ultimo snapshot. Si media docena de
    # perfumerias vino vacia (red caida, DNS, bloqueo), guardar esta corrida
    # taparia la ultima buena y la web quedaria mostrando 5 tiendas.
    salud = _salud_corrida(reporte_sitios)
    log.info("Salud de la corrida: %d/%d perfumerias con datos (%.0f%%).",
             len(salud["con_datos"]), salud["total"], salud["ratio"] * 100)
    if salud["sin_datos"]:
        log.warning("Sin datos: %s", ", ".join(salud["sin_datos"]))

    corrida_incompleta = (not args.solo
                          and salud["ratio"] < args.umbral_tiendas)
    if corrida_incompleta and not args.no_db and not args.force_db:
        log.error("CORRIDA INCOMPLETA: solo %d de %d perfumerias trajeron datos "
                  "(minimo %.0f%%). NO la guardo en MySQL para no tapar el "
                  "ultimo snapshot bueno del dashboard. Revisa la conectividad "
                  "y volve a correr; si igual la queres guardar, agrega "
                  "--force-db.",
                  len(salud["con_datos"]), salud["total"],
                  args.umbral_tiendas * 100)
        args.no_db = True

    # guardar en MySQL (rebajas + transferencia; misma fecha_hora que el Excel)
    if not args.no_db:
        guardar_en_mysql(res_db, ahora, args, reporte_sitios)

    if previos:
        n_mej = sum(1 for it in res if not _es_nuevo(it, previos) and _mejoro(it, previos))
        n_new = sum(1 for it in res if _es_nuevo(it, previos))
        log.info("Mejor negocio que antes (verde): %d | productos nuevos (amarillo): %d",
                 n_mej, n_new)

    log.info("")
    log.info("LISTO: %d con descuento real -> %s", len(res), ruta_final)
    if transfer:
        log.info("       + %d productos más baratos por transferencia "
                 "(guardados aparte en la base).", len(transfer))
    for it in res[:8]:
        log.info("  %5.1f%%  %-12s %s ($%.0f -> $%.0f) [%s]",
                 it.descuento_pct, it.tienda, it.nombre[:38],
                 it.precio_lista, it.precio_actual, it.fuente)
    # salida != 0 para que la tarea programada marque el fallo
    return 3 if corrida_incompleta else 0


if __name__ == "__main__":
    sys.exit(main())