# 10 · Frontend: layouts, componentes UI, tema y convenciones

## Layouts

| Layout | Archivo | Se usa en |
|---|---|---|
| `components.layouts.app` | `resources/views/components/layouts/app.blade.php` (~700 líneas) | Dashboard (`<x-layouts.app>` en `dashboard.blade.php`), `Notificaciones`, `Planes`, `Admin\Dashboard`, vistas `pago/*` |
| `components.layouts.auth` | `resources/views/components/layouts/auth.blade.php` | `Login`, `Register`, `VerifyEmail`, `ForgotPassword`, `ResetPassword` |

### Layout `app` — anatomía
* `@props(['activeTab' => 'joyas'])`; calcula `$verifiedUser` / `$unverifiedUser`.
* `<html>` con `x-data` Alpine global: `dark` (persistido en `localStorage.dark`, **por defecto oscuro** salvo que valga `'false'`), `sidebar`, `sidebarCollapsed`, `mobileMenuOpen`, `filterCount`. Un script síncrono en `<head>` agrega la clase `dark` antes del primer paint (evita parpadeo); un overlay `#theme-flash` suaviza el cambio de tema.
* `<head>`: fuente Inter, gtag.js (si hay `GOOGLE_ANALYTICS_ID`), `@vite(['resources/css/app.css','resources/js/app.js'])`, `@livewireStyles`, SweetAlert2 CSS y estilos de ApexCharts para modo oscuro.
* **Sidebar** `<aside class="app-sidebar">` (desktop fijo 240px, colapsable con el botón ☰ de la navbar; en móvil off-canvas). Desde 2026-10-30 **solo contiene**: selector de rubro, el contenedor `<div id="sidebar-filters">` (cada página teleporta ahí su panel de filtros con `x-teleport`; si la página no tiene filtros — planes, alertas, admin, marcas, distribución — muestra un aviso) y, abajo, cuenta de usuario + tema. **Sin logo y sin lista de pestañas**: la navegación entre secciones está únicamente en el header.
* **Navbar** sticky `glass-nav`: tabs en desktop, botón Alertas, Admin, login/logout; en móvil, menú hamburguesa + `#mobile-filters`.
* **Filtros en celular** (verificado con Playwright, 390 px): los filtros viven en el menú ☰ (`#mobile-filters`). Para que se encuentren, la barra de búsqueda (`livewire/dashboard/barra-busqueda.blade.php`) muestra un botón **⚙️ Filtros** (con contador `filterCount`, solo `< lg`) que abre ese menú (`@click.stop`: sin `.stop` el `@click.outside` del `<nav>` lo cerraba en el mismo clic), y el panel termina con un botón **Ver resultados (N filtros)** que lo cierra. Los enlaces `ver →` de las tablas llevan `whitespace-nowrap` (antes se partían en dos líneas).
* Final: `@livewireScripts`, SweetAlert2 JS (helper `window.swalConfirmar({titulo,texto,btnConfirmar,icono}, onConfirm)`), ApexCharts 3 (CDN), `@stack('scripts')`.
* Las pestañas de los menús (sidebar, navbar, barra móvil) salen de `config/rubros.php` (`tabs`) y hay un selector de rubro; el layout recibe el prop `rubro`. Ver [14](14-multirubro.md).

### Layout `auth`
Tarjeta centrada con logo 🧴, mismo sistema de tema oscuro, `{{ $slot }}` y link para volver al dashboard / cerrar sesión.

## Sistema de diseño (`resources/css/app.css`)
* Tailwind v4: `@import 'tailwindcss'`, `@custom-variant dark (&:where(.dark, .dark *))` (dark mode **por clase**).
* `@theme`: fuente Inter, escala `--color-primary-50…900` (violeta/magenta, 600 = `#8a1caf`), `--color-accent-*` (dorado), `--color-good-600`, `--color-bad-600`, `--color-surface`, `--color-border`.
* Variables de tema usadas en todas las vistas como `bg-[var(--c-card)]`, `text-[var(--c-text)]`, `border-[var(--c-border)]`, `text-[var(--c-muted)]`, `bg-[var(--c-bg)]`, `--c-sidebar`, `--c-nav`, `--c-input` (redefinidas bajo `html.dark`).
* Utilidades custom en `app.css`: `[x-cloak]`, `.scrollbar-none`, `.app-sidebar/.app-content` (colapso del sidebar), `.filter-section-closed`. Otras clases propias están definidas en el `<style>` inline del layout `app.blade.php`: `.collapsible-wrap/.is-open/.collapsible-inner` (KPI panel plegable), `.tab-animate-in` (animación de cambio de pestaña), `.glass-nav` (navbar), `.card-nuevos-oferta` y overrides de ApexCharts para dark mode.
* `@source` incluye blade, js, vendor de Livewire y vistas compiladas.
* Estilo visual dominante: tarjetas redondeadas (`rounded-2xl`, `border`, `bg-[var(--c-card)]`), acentos `violet-500/600`, modo oscuro con fondo `#0d0f1a`.

## Interactividad
* **Livewire**: `wire:click`, `wire:model.live.debounce.400ms`, Enlaces a tiendas: `target="_blank" rel="noopener noreferrer" referrerpolicy="no-referrer"` (ver [16](16-scraping-responsable-y-privacidad.md)). `wire:loading` (overlay con spinner en el dashboard: cubre toda la card y la ruedita es `position: sticky; top: 42vh`, así que queda **siempre centrada en lo que ve el usuario**, también tras scrollear con el scroll infinito de la tabla/Destacados), `@entangle('tabActiva')`.
* **Alpine**: estado de UI (`x-data`), `x-show`, `x-teleport` (filtros al sidebar/móvil), `x-cloak`.
* **Eventos de ventana** usados: `charts-refresh`, `seguir-refresh`, `tab-changed`, `filter-count-changed` (dashboard ↔ layout); `toast` (Notificaciones: componente local de toasts, `tipo` + `mensaje`); `login-failed` (Login).
* **ApexCharts** se instancian desde Alpine leyendo `$wire.<prop>Json`; siempre destruir (`_destroy(id)`) antes de recrear; colores dependen de `html.dark`.
* **SweetAlert2** para confirmaciones destructivas (p. ej. eliminar alerta).

## JS / build
* `resources/js/app.js` solo importa `bootstrap.js` (axios global con `X-Requested-With`). **Livewire 4 inyecta Alpine por sí mismo**: no hay `Alpine.start()` propio.
* Build: `npm run dev` (Vite + HMR) / `npm run build` (genera `public/build`, ignorado por git → hay que buildear en el servidor o subir el build).

## Vistas de emails (`resources/views/emails/`)
* `alerta_descuento.blade.php` — HTML con estilos inline/`<style>`; tres modos según `$resultados`: `true` (prueba), `Collection` con resultados reales, o vacío/ejemplo; muestra tarjetas con precio lista/actual/% y botón de URL; pie distinto para `@guest`/`@auth`.
* `verificacion_codigo.blade.php` — código de 6 dígitos.

## Reglas prácticas al tocar la UI
1. Usar los tokens `var(--c-*)` en lugar de colores fijos para que funcione el modo oscuro; probar ambos temas.
2. Al agregar/quitar una pestaña: agregarla en `config/rubros.php` (`tabs` y `tabs_rutas`; las rutas y los menús se generan solos), el `match` de `cargarDatosParaTab()` y `recargarTodo()` en `Dashboard.php`, el `@include` en `livewire/dashboard.blade.php` y esta documentación.
3. Al agregar un filtro global: tocar los dos bloques teleportados (`#sidebar-filters` y `#mobile-filters`) y el contador `$_fc`.
4. El diseño es mobile-first en el sentido de que hay variantes móviles (`lg:` breakpoint = 1024px para sidebar).

* La barra de búsqueda global (producto + marcas) está arriba del contenido, debajo de "Indicadores" (no en el sidebar): ver [05](05-dashboard-secciones.md).

## Zoom de imágenes de producto (2026-10-30)
Las miniaturas de la tabla de ofertas (`dashboard/tab-tabla.blade.php`, vista de escritorio y de tarjetas) emiten `img-zoom-show` / `img-zoom-hide` (`x-on:mouseenter` / `mouseleave` con `$dispatch`). Un único visor Alpine al final de `<body>` en `components/layouts/app.blade.php` muestra la imagen a 320 px junto al cursor (se invierte de lado si no entra, se oculta al hacer scroll). Solo con mouse. La línea de detalle bajo el nombre (tipo · color · talles) se muestra siempre que haya tipo, color o talles; sin talles dice "Talles: consultá en la tienda".

## Menú lateral de filtros: acordeón a pantalla completa (2026-10-30)
Los acordeones del panel (`Prenda / tipo`, `Deporte`, `Línea`, `Precio y colores`, `Talle`, `Tiendas`, `ml`) llevan la clase `acc-sec` y `:class="{ 'acc-sec-open': open }"`. Mientras alguno está abierto (CSS `.sidebar-filter-panel:has(> .acc-sec-open)`, definido en `components/layouts/app.blade.php`) el panel ocupa **todo el alto** del sidebar: los bloques fijos (género, categoría, descuento, "limpiar filtros") se pliegan con transición, los demás acordeones (cerrados) también se pliegan y el abierto se estira por todo el alto con scroll propio (para cambiar de filtro se cierra el abierto con su cabecera y se abre otro); al cerrarlo todo vuelve. Solo uno abierto a la vez (evento `acc-abrir`). Antes el panel tenía `max-height: 800px` con `overflow: hidden` (animación `sidebarFilterIn`), lo que **cortaba** los filtros largos como el de talles; ahora la animación es solo de opacidad. Tipografía de las opciones: chips `text-xs` con más relleno, títulos y enlaces de 11 px (antes 10 px). Se aplica al sidebar de escritorio; el menú móvil usa los mismos acordeones sin el modo de alto completo. Ojo al depurar: un `:class` de Alpine con cadena (`acc ? 'x' : ''`) dejó la clase pegada en este panel; se resolvió detectando el estado con `:has()` en vez de estado JS.

**Selector de rubro (2026-11-08):** con tres rubros el selector usa `nombre_corto` de `config/rubros.php` (Perfumes · Ropa · Tecnología) en el sidebar y en la barra móvil; el nombre completo se sigue usando en el header y en el formulario de alertas.
