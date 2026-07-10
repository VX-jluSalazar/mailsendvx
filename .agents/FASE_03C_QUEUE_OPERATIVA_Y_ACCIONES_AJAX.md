# Fase 03C: Queue operativa y acciones AJAX

## Estado

Implementada en primera iteracion funcional.

## Objetivo

Corregir y modernizar la tabla operativa de queue para que sus acciones funcionen correctamente desde la propia grid, sin redirecciones y con actualizacion parcial.

## Alcance

### Cancelacion de jobs

- revisar la accion actual de cancelacion,
- eliminar la redireccion hacia `flows`,
- ejecutar la cancelacion via AJAX,
- actualizar solo la tabla afectada,
- mantener filtros, orden y paginacion,
- mostrar confirmacion o error al usuario.

### Columna `Programado`

- mostrar fecha y hora completas en queue,
- usar formato `DD/MM/YYYY HH:mm:ss`.

### Boton `Limpiar tabla`

- agregar accion para limpieza de registros,
- solicitar confirmacion previa,
- validar permisos,
- ejecutar via AJAX,
- refrescar la grid sin recarga completa.

## Alcance adicional

- adaptar otras acciones individuales de queue para el mismo patron AJAX cuando aplique,
- unificar respuestas JSON de acciones operativas,
- registrar claramente estados finales de los jobs tras actuar sobre ellos.

## Riesgos a controlar

- inconsistencias entre estado visual y estado persistido del job,
- acciones que hoy dependen de redirect y flash messages legacy,
- permisos insuficientes en endpoints AJAX nuevos.

## Criterios de aceptacion

- cancelar un job desde queue funciona y actualiza el estado visible,
- la UI permanece en la misma tabla sin redirigir al usuario,
- la columna `Programado` muestra fecha y hora completas,
- `Limpiar tabla` funciona con confirmacion, permisos y feedback correcto.

## Implementacion aplicada

- acciones `cancelar`, `cancelar seleccionados`, `procesar queue ahora` y `limpiar tabla` con respuesta JSON cuando la peticion es AJAX,
- fallback con redirect solo para compatibilidad cuando la accion no llega como AJAX,
- permisos validados con `isGranted()` sobre `AdminMailsendvxDashboard`,
- nuevo endpoint `mailsendvx_queue_clear`,
- `redirect_route` de la grid de queue corregida hacia Dashboard,
- columna `Programado` fijada en formato `d/m/Y H:i:s`,
- boton `Limpiar tabla` agregado en el panel de queue del Dashboard,
- limpieza implementada sobre historico terminal de queue.

## Decision aplicada para limpieza

`Limpiar tabla` elimina solo registros terminales:

- `sent`
- `failed`
- `cancelled`
- `skipped`

Se conservan registros activos:

- `pending`
- `scheduled`
- `processing`

Esta decision evita borrar jobs aun operativos mientras se limpia el historico.
