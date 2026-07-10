# Fase 03B: Infraestructura AJAX comun

## Estado

Implementada en primera iteracion funcional.

## Objetivo

Construir la base reutilizable para extender las grids del Dashboard con refresh parcial, mensajes de resultado y reinicializacion de eventos tras cada reemplazo de HTML.

## Alcance

- crear un JavaScript comun para grids del Dashboard,
- definir convenciones `data-*` para `refresh`, `detail`, acciones y metadata de grid,
- agregar soporte de loader, manejo de errores y mensajes de exito,
- definir respuestas parciales o JSON segun el tipo de accion,
- establecer el punto comun para rebind de eventos tras cada refresh.

## Componentes esperados

- inicializador JS comun del Dashboard,
- helper para refresh de tabla conservando estado,
- helper para envio de acciones AJAX con feedback visual,
- contrato comun para endpoints de render parcial,
- contrato comun para respuestas JSON de acciones.

## Reglas tecnicas

- reutilizar PrestaShop Grid Symfony en lugar de reemplazarla con tablas custom,
- no perder filtros, orden ni pagina actual durante el refresh,
- evitar listeners directos sobre elementos reemplazables; usar delegacion,
- reinicializar componentes despues de cada actualizacion de la grid.

## Dependencias

- resultado de `FASE_03A_AUDITORIA_GRIDS_Y_BRECHAS.md`
- skill `prestashop-module-development`, especialmente referencia de Grid

## Criterios de aceptacion

- una grid piloto puede refrescarse por AJAX sin recargar la pagina,
- los mensajes de exito y error se muestran de forma consistente,
- la infraestructura se puede reutilizar en multiples tablas sin duplicar JS,
- queda definido un patron de endpoint comun para subfases posteriores.

## Implementacion aplicada

- nuevo servicio `AdminAjaxResponseBuilder` para respuestas JSON consistentes,
- nuevos endpoints parciales:
  - `mailsendvx_dashboard_grid`
  - `mailsendvx_templates_grid`
- wrapper Twig comun `mailsendvx-grid-shell` con metadata `data-*` por grid,
- partial Twig reutilizable `views/templates/admin/partials/grid_panel.html.twig`,
- nuevo gestor JS comun en `views/js/mailsendvx-grid.bundle.js` para:
  - interceptar filtros,
  - interceptar ordenamiento,
  - interceptar paginacion,
  - refrescar HTML parcial de grid,
  - reinicializar Grid de PrestaShop tras cada replace,
  - mostrar feedback inline,
  - habilitar acciones POST AJAX en grids marcadas como compatibles.

## Cobertura actual

- `Dashboard > Eventos`: refresh parcial listo,
- `Dashboard > Queue`: refresh parcial listo, acciones POST todavia reservadas para Fase 03C,
- `Dashboard > Logs`: refresh parcial listo,
- `Templates`: refresh parcial y piloto de acciones AJAX para borrado simple y masivo.

## Limitaciones actuales

- la lista `Flows` sigue fuera de esta capa porque no usa Grid nativa,
- la cancelacion AJAX de queue y limpieza operativa aun requieren la Fase 03C,
- el modal de detalle por fila todavia no forma parte de esta entrega y queda para Fase 03D.
