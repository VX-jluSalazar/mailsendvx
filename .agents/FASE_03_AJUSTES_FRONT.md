# Fase 03: Ajustes front del Dashboard

## Estado

Pendiente de ejecucion.

## Objetivo

Mejorar la experiencia de administracion del Dashboard del modulo `mailsendvx` usando PrestaShop Grid Symfony como base, agregando comportamiento AJAX reutilizable y evitando recargas completas de pagina.

## Enfoque de implementacion

Esta fase se divide en subfases pequenas para reducir riesgo y permitir validacion incremental:

- `FASE_03A_AUDITORIA_GRIDS_Y_BRECHAS.md`
- `FASE_03B_INFRAESTRUCTURA_AJAX_COMUN.md`
- `FASE_03C_QUEUE_OPERATIVA_Y_ACCIONES_AJAX.md`
- `FASE_03D_MODAL_DETALLE_Y_ACCIONES_MASIVAS.md`
- `FASE_03E_NAVEGACION_AJAX_Y_CIERRE_OPERATIVO.md`

## Alcance global

- auditar que tablas usan Grid nativa y cuales siguen siendo implementacion personalizada,
- unificar refresco, busqueda, ordenamiento y paginacion por AJAX,
- corregir la operativa de queue sin redirecciones indeseadas,
- agregar modal reutilizable de detalle por fila,
- mover acciones individuales y agrupadas a un flujo AJAX consistente,
- conservar filtros, pagina y orden al actualizar una tabla.

## Criterios rectores

- priorizar PrestaShop Grid Symfony,
- evitar duplicar JavaScript por tabla,
- aislar controladores y respuestas AJAX reutilizables,
- mantener compatibilidad con el Back Office actual,
- preparar la base para futuras tablas del Dashboard.

## Dependencias

- skill `modules/mailsendvx/.agents/skills/prestashop-module-development`
- referencia de Grid del skill para rutas, controladores y extensiones JS
- arquitectura ya implementada en `Dashboard`, `Flows` y `Queue`

## Resultado esperado

Al cerrar esta fase, las tablas del Dashboard deben sentirse operativas y consistentes: acciones sin recarga completa, detalle bajo demanda, mejor feedback visual y una base comun mantenible para nuevas grids.
