# Fase 03E: Navegacion AJAX y cierre operativo

## Estado

Implementada en primera iteracion funcional.

## Objetivo

Completar la experiencia operativa del Dashboard haciendo que busqueda, ordenamiento, paginacion y refresco funcionen por AJAX de forma uniforme en todas las grids alcanzadas por la fase.

## Alcance

### Busqueda y ordenamiento

- convertir filtros y ordenamiento a flujo AJAX,
- actualizar solo el HTML de la grid,
- conservar filtros activos, orden y pagina cuando corresponda.

### Paginacion

- mantener la paginacion nativa de PrestaShop Grid,
- interceptar navegacion para refresco parcial,
- preservar el estado actual del listado.

### Boton refrescar

- agregar boton de refresh en todas las tablas objetivo,
- mostrar loader o estado de carga,
- volver a renderizar la grid sin recargar la pagina completa.

## Cierre de fase

- validar comportamiento uniforme entre grids migradas,
- revisar puntos donde todavia se use navegacion completa por limitaciones del Back Office,
- documentar grids pendientes o excepciones justificadas.

## Validacion esperada

- filtros activos sobreviven a refresh, orden y cambio de pagina,
- no se pierden listeners ni comportamiento JS despues de reemplazar HTML,
- el usuario puede operar varias tablas del Dashboard sin saltos de contexto,
- queda una base lista para extender nuevas grids con el mismo patron.

## Criterios de aceptacion

- busqueda, orden, paginacion y refresh funcionan por AJAX en las grids cubiertas,
- el comportamiento es consistente entre tablas,
- no hay recargas completas de pagina para interacciones rutinarias del Dashboard,
- quedan documentadas las excepciones tecnicas si alguna tabla no puede migrarse aun.

## Implementacion aplicada

- filtros, ordenamiento, paginacion y refresh parcial unificados en el gestor comun `mailsendvx-grid.bundle.js`,
- persistencia del estado de cada grid en `sessionStorage` por pantalla:
  - filtros activos
  - orden actual
  - pagina / offset
- restauracion automatica del estado al volver a una tab del Dashboard,
- persistencia de la tab activa del Dashboard entre navegaciones internas,
- refresh AJAX reutilizable via `window.mailsendvxAdmin.refreshGrid(gridId)`,
- conservacion de listeners y rebind automatico tras cada reemplazo de HTML.

## Cobertura cerrada

- `Dashboard > Events`
- `Dashboard > Queue`
- `Dashboard > Logs`
- `Templates`

## Excepciones documentadas

- `Flows` sigue fuera de esta capa porque no usa Grid nativa de PrestaShop,
- la restauracion de estado se limita a pantallas ya migradas al shell AJAX del modulo,
- la persistencia es por sesion del navegador y no intenta sincronizar estado entre usuarios o dispositivos.
