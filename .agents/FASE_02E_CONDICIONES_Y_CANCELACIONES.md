# Fase 02E: condiciones y cancelaciones

## Objetivo

Evitar envios fuera de contexto mediante validaciones al inicio del flow y antes del envio.

## Niveles de condicion

- condiciones de entrada del flow,
- condiciones por step,
- reglas de cancelacion de jobs pendientes.

## Filtros iniciales recomendados

- tienda
- idioma
- grupo de cliente
- total del pedido
- moneda
- categorias
- productos
- pais
- suscripcion activa
- carrito aun no convertido

## Reglas de cancelacion recomendadas

- cancelar jobs de `cart_abandoned` si el carrito se convierte en pedido,
- cancelar jobs de nurturing si el cliente se da de baja,
- cancelar jobs postcompra si el estado posterior invalida el flujo,
- cancelar manualmente desde panel operativo sin borrar historico.

## Principio de implementacion

- si el flow no cumple condiciones de entrada, no crea cola,
- si el job ya existe pero falla la reevaluacion, pasa a `skipped` o `cancelled`,
- nunca se debe borrar historico operativo solo para "limpiar".

## Ejemplos

Caso `cart_abandoned`:

- trigger crea jobs,
- cliente compra antes del siguiente step,
- jobs pendientes pasan a `cancelled`.

Caso `newsletter_registered`:

- step 2 depende de suscripcion activa,
- si el cliente se da de baja antes del envio,
- job pasa a `cancelled` o `skipped` segun la regla definida.

## Criterios de aceptacion

- Se pueden evaluar condiciones al inicio del flow.
- Se pueden reevaluar condiciones antes del envio.
- Se pueden cancelar jobs sin borrar historico.
- Los estados `cancelled` y `skipped` quedan trazables.
