# Fase 02E: condiciones y cancelaciones

## Estado

Implementada en primera iteración funcional.

## Alcance implementado

- evaluación de condiciones de entrada del flow en scheduling,
- evaluación de condiciones por step en scheduling,
- persistencia de `cancel_rules` por step desde `steps_json`,
- reevaluación operativa previa al envío dentro del worker,
- transición formal a `skipped` o `cancelled` sin borrar histórico,
- cancelación operativa de jobs `cart_abandoned` pendientes cuando el carrito se recupera o se convierte en pedido,
- trazabilidad de cancelaciones en `mailsendvx_queue` y `mailsendvx_log`,
- ampliación de operadores del evaluador con `empty`, `not_empty`, `starts_with` y `ends_with`.

## Archivos principales

- `src/Service/Flow/FlowConditionEvaluator.php`
- `src/Service/Flow/FlowSchedulerService.php`
- `src/Service/Flow/FlowWorkerService.php`
- `src/Service/Cart/AbandonedCartService.php`
- `src/Repository/MailSendVxFlowRepository.php`
- `src/Repository/MailSendVxQueueRepository.php`

## Objetivo

Evitar envios fuera de contexto mediante validaciones al inicio del flow y antes del envio.

## Niveles de condicion

- condiciones de entrada del flow,
- condiciones por step,
- reglas de cancelacion de jobs pendientes.

## Operadores soportados

- `eq`, `=`
- `neq`, `!=`
- `gt`, `gte`, `lt`, `lte`
- `exists`, `not_exists`
- `contains`
- `in`, `not_in`
- `empty`, `not_empty`
- `starts_with`, `ends_with`

## Filtros iniciales ya posibles

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

## Reglas de cancelacion implementadas

- cancelar jobs de `cart_abandoned` si el carrito se convierte en pedido,
- cancelar jobs de `cart_abandoned` si el cron detecta carrito ya recuperado,
- cancelar jobs si `cancel_rules` del step hacen match en la reevaluacion del worker,
- marcar `skipped` cuando fallan `conditions` del flow o del step en la reevaluacion,
- conservar histórico operativo en vez de borrar jobs.

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

## Flujo tecnico actual

```txt
Evento detectado
|
Scheduler evalua condiciones de flow
|
Scheduler evalua condiciones del step
|
Se agenda job con payload persistido
|
Worker bloquea job vencido
|
Worker reevaluá flow.conditions, step.conditions y step.cancel_rules
|
Job pasa a sent, skipped o cancelled
```

## Casos cubiertos hoy

- un flow no agenda jobs si sus `conditions_json` no coinciden con el payload,
- un step no agenda jobs si sus `conditions` no coinciden con el payload,
- un job ya agendado pasa a `skipped` si el flow o step deja de cumplir condiciones al reevaluarse,
- un job pasa a `cancelled` si el step está inactivo o si `cancel_rules` coincide,
- jobs pendientes de `cart_abandoned` se cancelan cuando `markRecoveredFromOrderParams()` detecta conversión a pedido,
- jobs pendientes de `cart_abandoned` se cancelan cuando `syncRecoveredCarts()` detecta recuperación previa.

## Limitaciones actuales

- la reevaluación usa el payload persistido del job y no reconstruye entidades vivas antes del envío,
- no existe todavía cancelación manual desde panel operativo,
- no existe evento de baja de newsletter modelado aparte de `newsletter_registered`,
- no hay grupos lógicos complejos tipo `AND/OR` anidados en el evaluador; la lista actual funciona como `AND`.

## Criterios de aceptacion

- Se pueden evaluar condiciones al inicio del flow.
- Se pueden reevaluar condiciones antes del envio.
- Se pueden cancelar jobs sin borrar historico.
- Los estados `cancelled` y `skipped` quedan trazables.
- `cart_abandoned` cancela jobs pendientes al recuperarse el carrito.

## Validacion realizada

- `php -l src/Service/Flow/FlowConditionEvaluator.php`
- `php -l src/Service/Flow/FlowWorkerService.php`
- `php -l src/Service/Cart/AbandonedCartService.php`
- `php -l src/Repository/MailSendVxQueueRepository.php`
- `php -l config/components/service/services.yml`
