# Fase 01E: recuperacion inmediata de carrito por hook

## Estado

Pendiente de implementacion.

## Motivo de la fase

La recuperacion de carritos abandonados hoy depende principalmente del cron de `abandoned cart`.

Eso funciona como reconciliacion, pero deja una ventana operativa donde:

- el cliente modifica un carrito ya marcado como `abandoned`,
- el sistema todavia conserva jobs pendientes de `cart_abandoned`,
- y si el `queue cron` corre antes de que vuelva a correr el cron de carritos abandonados, esos jobs pueden enviarse fuera de contexto.

El objetivo de esta fase es cerrar esa brecha mediante hooks de carrito que marquen recuperacion y cancelen jobs pendientes de forma casi inmediata.

## Estado real del modulo al iniciar esta fase

### Ya implementado

- tabla `PREFIX_mailsendvx_abandoned_cart` con `status`, `abandoned_at` y `recovered_at`,
- cancelacion de jobs pendientes cuando:
  - el carrito se convierte en pedido,
  - o el cron detecta actividad posterior al abandono,
- metodo `markRecoveredFromOrderParams()` para conversion a pedido,
- metodo `syncRecoveredCarts()` para reconciliacion por cron,
- metodo interno `cancelPendingCartJobs()` para cancelar jobs `cart_abandoned`,
- revalidacion de queue previa al envio,
- flows con `cancel_rules` por step.

### Limitacion actual

La recuperacion por actividad del carrito no ocurre en tiempo real.

Hoy la actualizacion de `recovered_at` por movimiento del carrito depende de volver a ejecutar:

- `controllers/front/abandonedcartcron.php`
- `AbandonedCartService::processDueCarts()`
- `AbandonedCartService::syncRecoveredCarts()`

Eso significa que el cron de queue puede ejecutar jobs pendientes con un payload historico antes de que el cron de carritos marque la recuperacion.

## Problema tecnico observado

### Comportamiento actual

1. El scheduler crea jobs de flow para `cart_abandoned`.
2. Cada job persiste un `payload_json` congelado.
3. El worker reevaluá condiciones y `cancel_rules` usando ese payload guardado.
4. Si el carrito cambia despues, el worker no reconstruye el contexto vivo del carrito.
5. Solo el cron de carritos puede detectar la recuperacion y cancelar jobs pendientes.

### Consecuencia

Entre el cambio del carrito y la siguiente corrida del cron de abandono puede haber envios incorrectos.

## Objetivo funcional

Cuando un carrito ya marcado como `abandoned` cambia por actividad del cliente, el modulo debe:

1. detectar el cambio mediante hook,
2. verificar si el carrito estaba en estado `abandoned`,
3. marcarlo `recovered`,
4. persistir `recovered_at`,
5. cancelar jobs pendientes de `cart_abandoned`,
6. dejar el cron de carritos como respaldo y reconciliacion, no como unica fuente de recuperacion.

## Principio de diseno recomendado

Implementar modo hibrido:

- hooks para recuperacion inmediata,
- cron para reconciliacion de seguridad,
- misma logica de negocio centralizada en `AbandonedCartService`.

No conviene duplicar reglas en cada hook.

La decision correcta es crear un punto unico del dominio para "marcar carrito como recuperado por actividad", y reutilizarlo desde:

- hooks de carrito,
- `syncRecoveredCarts()`,
- `markRecoveredFromOrderParams()`.

## Hooks candidatos

La seleccion exacta puede ajustarse segun version y comportamiento real de la tienda, pero la estrategia recomendada es evaluar estos hooks:

- `actionCartSave`
- `actionObjectCartUpdateAfter`
- `actionObjectCartAddAfter`

Si la tienda o el checkout instalado no emiten todos de forma consistente, mantener al menos uno principal y conservar el cron como respaldo.

## Recomendacion de implementacion

### 1. Nuevo metodo de dominio en `AbandonedCartService`

Agregar un metodo explicito, por ejemplo:

- `markRecoveredFromCartActivity(int $idCart, ?int $idShop = null): bool`

Responsabilidades:

- cargar el estado actual de `mailsendvx_abandoned_cart` por `id_cart`,
- salir sin hacer nada si no existe registro,
- salir sin hacer nada si el estado ya no es `abandoned`,
- opcionalmente validar que el carrito sigue existiendo,
- guardar:
  - `status = recovered`
  - `recovered_at = now`
  - `last_activity_at = cart.date_upd o now`
- cancelar jobs pendientes del evento `cart_abandoned`,
- devolver `true` si hubo recuperacion efectiva.

### 2. Reutilizar logica comun

La logica que hoy existe repartida entre:

- `markRecoveredFromOrderParams()`
- `syncRecoveredCarts()`
- `cancelPendingCartJobs()`

deberia converger en una rutina interna unica, por ejemplo:

- `markRecoveredState(array $state, int $idShop, string $reason, ?array $overrides = []): bool`

Objetivo:

- evitar divergencias,
- mantener mensajes de cancelacion consistentes,
- simplificar pruebas.

### 3. Registrar hooks en instalacion

Actualizar `Installer::$hooks` para incluir el hook o hooks seleccionados.

Ejemplo conceptual:

```php
private $hooks = [
    'displayBackOfficeHeader',
    'actionValidateOrder',
    'actionOrderStatusPostUpdate',
    'actionCustomerAccountAdd',
    'actionNewsletterRegistrationAfter',
    'actionCartSave',
];
```

## 4. Crear handlers en `mailsendvx.php`

Agregar uno o mas hooks en el modulo, por ejemplo:

- `hookActionCartSave(array $params): void`

Ese hook no debe contener logica de negocio compleja.

Solo debe:

- extraer `id_cart` o la entidad `Cart`,
- delegar en `AbandonedCartService`.

## 5. Politica de actividad real

No todo `save` del carrito implica actividad comercial relevante.

Por eso la fase debe definir una politica minima:

- si existe registro `abandoned`, cualquier cambio posterior al abandono lo saca del estado abandonado,
- no intentar distinguir aun entre "cambio importante" y "cambio menor",
- dejar una futura mejora opcional para filtrar eventos tecnicos si llegan a ser ruidosos.

Esto mantiene consistencia con la logica actual del cron, que ya trata `cart.date_upd > abandoned_at` como señal suficiente de recuperacion.

## Relacion con el queue worker

Esta fase no reemplaza la revalidacion del worker.

Pero sí reduce drásticamente la ventana de riesgo antes del envio.

Estado objetivo despues de esta fase:

- el hook cancela rapido los jobs pendientes,
- el cron sigue corrigiendo inconsistencias,
- el worker sigue siendo la ultima barrera operativa.

## Relacion con `cancel_rules`

Las `cancel_rules` por si solas no resuelven este caso porque:

- el worker las evalua con el `payload_json` persistido del job,
- ese payload no se rehidrata desde el carrito vivo,
- y hoy no incluye un `cart.recovered_at` actualizado en tiempo real.

Esta fase corrige el problema desde el estado operativo del carrito, no desde el payload historico del flow.

## Archivos principales a tocar

- `mailsendvx.php`
- `src/Install/Installer.php`
- `src/Service/Cart/AbandonedCartService.php`
- opcionalmente `src/Repository/MailSendVxAbandonedCartRepository.php`

## Propuesta de flujo final

```txt
Cliente modifica carrito
|
PrestaShop dispara hook de carrito
|
Modulo delega a AbandonedCartService
|
Si el carrito estaba abandoned:
  - marcar recovered
  - setear recovered_at
  - cancelar jobs pendientes
|
Queue cron posterior ya no encuentra esos jobs como pendientes
|
Cron de abandoned cart sigue reconciliando casos omitidos
```

## Casos de prueba recomendados

### Caso 1: recuperacion inmediata antes del siguiente step

1. Crear carrito abandonado.
2. Confirmar que el flow agenda step 2 y step 3.
3. Modificar el carrito.
4. Confirmar que el hook marca `recovered`.
5. Confirmar que jobs pendientes pasan a `cancelled` sin esperar el cron de abandono.
6. Ejecutar `queue cron`.
7. Confirmar que no se envian mails restantes.

### Caso 2: compatibilidad con cron

1. Simular un cambio de carrito que no dispare el hook esperado.
2. Ejecutar el cron de abandono.
3. Confirmar que `syncRecoveredCarts()` sigue recuperando el carrito.
4. Confirmar cancelacion de jobs.

### Caso 3: conversion a pedido

1. Crear carrito abandonado con jobs pendientes.
2. Convertir el carrito en pedido.
3. Confirmar que `markRecoveredFromOrderParams()` sigue funcionando.
4. Confirmar que no se generan regresiones frente al nuevo mecanismo por hook.

## Riesgos abiertos

- algunos temas o checkouts pueden no disparar siempre el mismo hook,
- ciertos guardados tecnicos del carrito podrian marcar recuperacion antes de lo esperado,
- si se dispara recuperacion con demasiada facilidad, el carrito podria salir y reingresar al ciclo con mucho ruido.

## Mitigacion recomendada

- mantener cron como respaldo,
- centralizar la logica en un solo servicio,
- registrar logs operativos al marcar recuperacion por hook durante la primera iteracion,
- validar en una tienda real que el hook elegido se emite en:
  - agregar producto,
  - quitar producto,
  - cambiar cantidad,
  - actualizar carrito desde checkout.

## Criterios de aceptacion

- un carrito `abandoned` puede pasar a `recovered` por hook sin esperar al cron,
- los jobs pendientes de `cart_abandoned` se cancelan inmediatamente tras la recuperacion,
- la conversion a pedido sigue funcionando igual,
- el cron sigue reconciliando casos no cubiertos por hook,
- no se duplican reglas entre hook, cron y orden.

## Siguiente paso sugerido

Implementar primero una version minima con un solo hook principal y logs de observabilidad.

Si la tienda real muestra huecos de cobertura, ampliar despues a mas hooks compatibles sin cambiar la logica de dominio central.
