# Changelog — descuentos_master.py

Registro de cambios realizados sobre el script de scraping.  
El backup previo a cada cambio se nombra `descuentos_master.YYYY-MM-DD.py`.

---

## 2026-06-26 — Filtro de fuentes: solo `tachado` son descuentos reales

**Backup:** `descuentos_master.2026-06-26.py`

### Contexto

Al revisar Juleriaque (y confirmado en otras tiendas HTML) se detectó que los
productos con fuente `badge_%` y `dos_precios` son **falsos descuentos**: el
badge de porcentaje aparece en el listado pero al entrar al link el producto
NO tiene rebaja real en el sitio. Solo los productos con precio explícitamente
tachado (`<del>`, `line-through`, clases `old`/`list`) corresponden a una
rebaja genuina.

Las fuentes VTEX (`api`) y Tiendanube (`tiendanube`) son confiables porque
vienen de la API oficial del e-commerce y tienen su propia lógica de validación.

### Cambios en `descuentos_master.py`

1. **`_cards_a_items()` — nuevo default de fuentes válidas para HTML**  
   Antes: si el sitio no declaraba `fuentes_validas`, se aceptaban todas las
   fuentes (`tachado`, `dos_precios`, `badge_%`).  
   Ahora: el default es `["tachado"]`. Para aceptar otras fuentes, el sitio
   debe declararlas explícitamente en `SITES`.

2. **Config `parfumerie` en `SITES`**  
   Antes: `"fuentes_validas": ["tachado", "badge_%"]`.  
   Ahora: `"fuentes_validas": ["tachado"]` (se elimina `badge_%`).

### Cambios en `app/Models/Observacion.php`

3. **`scopeRebajas()` — excluye fuentes falsas de los históricos**  
   Antes: filtraba solo por `tipo_descuento = 'rebaja'`.  
   Ahora: además excluye `fuente IN ('dos_precios', 'badge_%')`.  
   Esto retroactivamente corrige los datos históricos ya guardados:
   la web desestima los registros grabados con fuente falsa.

---

---

## 2026-06-26 — Soporte de género (masculino / femenino / unisex)

**Backup:** `descuentos_master.2026-06-26.py` (mismo backup que el cambio anterior del día)

### Contexto

Hasta ahora el script solo raspaba perfumes masculinos. Se agrega soporte
para femenino y unisex en todas las tiendas que lo permiten.

### Cambios en `descuentos_master.py`

1. **`Item` dataclass — nuevo campo `genero`**  
   `genero: str = "masculino"` (default backward-compatible). Valores posibles:
   `"masculino"`, `"femenino"`, `"unisex"`.

2. **`SITES` — expansión completa y correcciones**

   | Tienda | Cambio |
   |---|---|
   | fiorani | Reemplazado `buscar_todo:True` por `category_path` específico (`fragancias/fragancias-masculinas`). Antes traía mezcla de géneros. |
   | parfumerie | Eliminada URL `parfusale`. Solo `fragancias/masculinas`. |
   | normy | URL masculino corregida: `/fragancias-masculinas/` (antes `/promociones/`). |
   | pigmento | Agregados femenino (`Perfume/Mujer`) y unisex (`Perfume/Unisex`). |
   | beauty24 | Agregados femenino y unisex. |
   | rouge | Agregados femenino y unisex. |
   | fiorani | Agregados femenino y unisex. |
   | juleriaque | Agregados femenino y unisex (con `category-3=femeninas/unisex`). |
   | parfumerie | Agregados femenino y unisex. |
   | ruizyroca | Agregados femenino (`femeninas/`) y unisex (`unisex3/`). |
   | normy | Agregado femenino. Sin unisex (no tiene). |
   | getthelook | Agregado femenino. Sin unisex (no tiene). |
   | farmalife | Agregado femenino (`premium/femeninas`). Sin unisex (pocos y mal catalogados). |
   | unishop | Agregados femenino y unisex. |
   | ambrosia | Agregados femenino (`Género=Femenino`) y unisex (`Género=Unisex`). |

3. **Loop principal — asignación de género post-scraping**  
   Después de obtener `prods` en ambos loops (VTEX/Unishop/Tiendanube y HTML),
   se aplica `p.genero = site.get("genero", "masculino")` a todos los items.
   No se modificaron las funciones de scraping individuales.

4. **`reporte_sitios` — clave compuesta `nombre_género`**  
   El campo `tienda` en `reporte_sitios` ahora es `f"{site['name']}_{genero}"`
   (ej. `"fiorani_masculino"`). Esto evita colisiones de DUPLICATE KEY en la
   tabla `corrida_sitios` cuando una misma tienda tiene múltiples entradas.
   Los datos de `tiendas` y `productos` siguen usando el nombre puro.

5. **`guardar_en_mysql()` — campo `genero` en productos**  
   El INSERT/UPSERT de `productos` incluye `genero` (actualizable en cada
   corrida para reflejar la categoría en que se encontró el producto).

### Cambio en base de datos requerido

```sql
ALTER TABLE productos
  ADD COLUMN genero ENUM('masculino','femenino','unisex')
  NOT NULL DEFAULT 'masculino'
  AFTER marca;
```

Los registros históricos quedan con `genero='masculino'` (correcto, ya que
solo se raspaban masculinos hasta ahora).

## 2026-10-29 — Correcciones (Ambrosia, min/max_descuento, new_page)

1. **Ambrosia (Tiendanube) devolvía 0 productos desde la corrida 149 (2026-10-22):** el sitio ahora parte
   el `<div` en varias líneas y `_RE_TN_ITEM` ya no matcheaba. Regex ahora `<div\s+class="js-product-item-private product-item`.
   Verificado contra el HTML real (12 ítems en página 1; con la regex vieja, 0).
2. **`corridas.min_descuento` / `max_descuento`** guardaban los argumentos CLI (siempre 0 y 90). Ahora guardan el mínimo y
   máximo REALES de las rebajas de la corrida (`guardar_en_mysql`). Las corridas históricas conservan los valores viejos.
3. **`browser.new_page` dentro del `try`** (bloque HTML/Playwright): si falla, se registra el sitio con `estado='error'`
   en vez de abortar toda la corrida; `page.close()` solo si la página existe.

## 2026-08-31 — Cambios no registrados antes (resumen)

Chequeo de red previo, control de corrida incompleta (`--umbral-tiendas`, `--force-db`), Normy paginado por path y
lectura de `<del>/<ins>` para precios tachados. Ver detalle en `ai-context/scraper-01-descripcion-y-ejecucion.md`.

<!-- próximas entradas debajo de esta línea -->
