# Fase 03D: Modal de detalle y acciones masivas

## Estado

Implementada en primera iteracion funcional.

## Objetivo

Agregar una capa reutilizable de detalle por fila y acciones agrupadas para las tablas del Dashboard, manteniendo compatibilidad con botones internos y comportamiento AJAX consistente.

## Alcance

### Modal de detalle por fila

- abrir modal al seleccionar una fila,
- cargar datos por AJAX,
- no interferir con botones, enlaces, dropdowns ni checkboxes,
- mostrar atributos relevantes del registro segun la tabla,
- reutilizar la misma estructura base en todas las grids.

### Acciones agrupadas

- habilitar seleccion multiple por checkbox,
- agregar bulk actions segun capacidades de cada grid,
- incluir confirmacion cuando corresponda,
- ejecutar acciones masivas via AJAX,
- refrescar la grid al terminar.

## Reglas de interaccion

- el click de fila debe usar delegacion y exclusiones por elemento interactivo,
- el modal debe tolerar registros inexistentes o eliminados entre click y carga,
- las acciones masivas deben reportar resultado agregado y errores parciales cuando existan.

## Ejemplos de acciones agrupadas esperadas

- eliminar seleccionados,
- cambiar estado,
- reprocesar,
- cancelar jobs en lote cuando la tabla lo permita.

## Dependencias

- infraestructura definida en `FASE_03B_INFRAESTRUCTURA_AJAX_COMUN.md`
- tablas auditadas en `FASE_03A_AUDITORIA_GRIDS_Y_BRECHAS.md`

## Criterios de aceptacion

- hacer click en una fila abre el detalle sin romper acciones internas,
- el modal carga informacion por AJAX y maneja errores de forma visible,
- al menos una grid soporta acciones masivas completas por AJAX,
- la implementacion es reutilizable para nuevas tablas del Dashboard.

## Implementacion aplicada

- nuevo proveedor reutilizable `AdminGridRecordDetailProvider`,
- nuevos endpoints AJAX de detalle para:
  - `events`
  - `queue`
  - `logs`
  - `templates`
- nueva accion `Detalle` en las grids soportadas,
- modal reutilizable compartido entre Dashboard y Templates,
- apertura por click de fila con exclusiones para:
  - botones
  - enlaces
  - checkboxes
  - controles interactivos
- render parcial reutilizable para contenido del modal.

## Acciones masivas cubiertas

- `queue`: cancelar seleccionados por AJAX,
- `templates`: eliminar seleccionados por AJAX.

## Limitaciones actuales

- `flows` sigue fuera de esta capa porque no usa Grid nativa,
- el modal muestra detalle estructurado y payload persistido, pero no edicion inline,
- no se agregaron nuevas bulk actions comerciales adicionales fuera de las ya soportadas por `queue` y `templates`.
