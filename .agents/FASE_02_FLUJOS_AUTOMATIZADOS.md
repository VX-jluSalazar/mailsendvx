# Fase 02: flujos automatizados

## Objetivo

Construir un motor de automatizacion capaz de programar emails, evaluar condiciones, procesar una cola con cron y cancelar o reintentar envios segun el comportamiento del cliente.

Esta fase transforma Mail Send VX de un sistema de emails instantaneos en un motor de automatizacion comercial.

## Dependencia de arquitectura

Los flujos de postcompra no deben construirse sobre un unico evento `order_status_updated`.

Antes o durante esta fase, la capa de eventos debe exponer:

- `order_created` como trigger canonico de pedido confirmado.
- `order_status_changed` como trigger generico.
- `order_status_changed_{state_key}` como trigger especifico por estado destino.

Sin esta separacion, los flujos de confirmacion, entrega, envio, pago aceptado, cancelacion o reembolso quedan ambiguos y requieren condiciones excesivas para diferenciarse.

## Alcance funcional

| Subfase | Objetivo | Complejidad |
| --- | --- | --- |
| 2.1 Motor de eventos | Registrar eventos como `cart_abandoned`, `order_created`, `order_status_changed_delivered` y `customer_registered`. | Media-alta |
| 2.2 Motor de flujos | Crear flujos con pasos, delays, plantillas y condiciones. | Alta |
| 2.3 Cola de envios | Programar emails para minutos, horas o dias posteriores. | Alta |
| 2.4 Cron o comando | Procesar cola automaticamente. | Media-alta |
| 2.5 Flujo abandoned cart | Enviar 3 emails en tiempos diferentes. | Alta |
| 2.6 Cancelacion por compra | Cancelar emails pendientes si el carrito se convierte en pedido. | Alta |
| 2.7 Flujo postcompra | Programar emails despues de compra, envio, entrega o cambio de estado. | Alta |
| 2.8 Flujo suscriptor | Crear secuencia para nuevos suscriptores. | Media-alta |
| 2.9 Condiciones por flujo | Evaluar estado, total, idioma, tienda, grupo, productos y categorias. | Alta |
| 2.10 Reintentos automaticos | Reintentar emails fallidos segun intentos maximos. | Media |
| 2.11 Estados de cola | Controlar `pending`, `scheduled`, `processing`, `sent`, `failed`, `cancelled` y `skipped`. | Media |
| 2.12 Panel de monitoreo | Ver cola, proximos envios, enviados, fallidos y cancelados. | Media-alta |

## Flujo tecnico

```txt
Evento detectado
|
Flow Engine busca flujos activos
|
Condition Engine valida reglas iniciales
|
Scheduler crea registros en cola
|
Cron toma jobs vencidos
|
Valida condiciones antes de enviar
|
Renderiza plantilla
|
Envia email
|
Actualiza estado y registra log
```

## Estados de cola recomendados

| Estado | Uso |
| --- | --- |
| `pending` | Registro creado, aun no preparado para envio. |
| `scheduled` | Email programado con `scheduled_at`. |
| `processing` | Job tomado por cron. |
| `sent` | Envio exitoso. |
| `failed` | Envio fallido sin reintentos disponibles o con error persistente. |
| `cancelled` | Job cancelado por compra, baja o regla de negocio. |
| `skipped` | Job omitido por condicion no cumplida. |

## Flujos comerciales iniciales

### Carrito abandonado

- Email 1: 1 hora despues del abandono.
- Email 2: 24 horas despues si no compro.
- Email 3: 72 horas despues si no compro.
- Cancelar todos los pendientes si se crea un pedido con ese carrito o cliente.

### Postcompra

- Email de confirmacion inmediata al ocurrir `order_created`.
- Email de agradecimiento despues de `order_status_changed_payment_accepted`.
- Email de seguimiento cuando ocurra `order_status_changed_shipped`.
- Email de review cuando ocurra `order_status_changed_delivered`.

## Triggers recomendados para flows

| Tipo | Trigger |
| --- | --- |
| Canonico | `order_created` |
| Generico | `order_status_changed` |
| Especifico | `order_status_changed_payment_accepted` |
| Especifico | `order_status_changed_shipped` |
| Especifico | `order_status_changed_delivered` |
| Especifico | `order_status_changed_canceled` |
| Especifico | `order_status_changed_refunded` |
| Otros | `customer_registered`, `newsletter_registered`, `cart_abandoned` |

### Suscriptores

- Email de bienvenida inmediato o programado.
- Email educativo/promocional a los 2 dias.
- Email de incentivo a los 5 dias, si aplica.

## Patrones recomendados

- Event Driven Architecture para eventos internos.
- Command y Command Handler para crear jobs y procesarlos.
- Queue/Scheduler para envios diferidos.
- Chain of Responsibility para condiciones.
- State para transiciones de cola.
- Strategy para providers y renderers.
- Repository para persistencia.

## Dependencias

- Fase 0 completa.
- Fase 1 funcional para renderizado, plantillas y logs.
- Plantillas activas por evento/flujo.
- Token de cron configurado.

## Como probar la funcionalidad

### Prueba 1: programar un email en cola

1. Crear un flujo activo con trigger `customer_registered`.
2. Configurar un paso con delay corto, por ejemplo 5 minutos.
3. Registrar un cliente de prueba.
4. Revisar que se cree un registro en `PREFIX_mailsendvx_queue` con estado `scheduled`.
5. Confirmar que `scheduled_at` respeta el delay configurado.

### Prueba 2: procesar cron

1. Crear o ajustar un job con `scheduled_at` en el pasado.
2. Ejecutar el cron o controller correspondiente.
3. Confirmar que el job pasa por `processing`.
4. Confirmar que termina como `sent`, `failed`, `skipped` o `cancelled`.
5. Revisar que se cree un log asociado.

### Prueba 3: carrito abandonado

1. Crear un carrito con email identificado.
2. Esperar o simular la condicion de abandono.
3. Confirmar que se crean 3 jobs programados.
4. Convertir el carrito en pedido antes del segundo envio.
5. Confirmar que los jobs pendientes quedan `cancelled`.

### Prueba 4: reintentos

1. Forzar un error de provider con un destinatario o configuracion invalida.
2. Ejecutar cron.
3. Confirmar que `attempts` aumenta.
4. Confirmar que se reprograma si quedan intentos.
5. Confirmar que termina en `failed` al superar el maximo.

### Prueba 5: condiciones por flujo

1. Crear un flujo condicionado por idioma, tienda o total de pedido.
2. Ejecutar eventos que cumplan y no cumplan la condicion.
3. Confirmar que solo se programan o envian los casos validos.
4. Confirmar que los casos rechazados quedan registrados como `skipped` o no generan cola, segun la regla definida.

## Consultas utiles de validacion

```sql
SELECT id_mailsendvx_queue, event_name, recipient, status, attempts, scheduled_at, processed_at, last_error
FROM PREFIX_mailsendvx_queue
ORDER BY id_mailsendvx_queue DESC
LIMIT 50;

SELECT id_mailsendvx_flow, name, trigger_event, active, date_add, date_upd
FROM PREFIX_mailsendvx_flow
ORDER BY id_mailsendvx_flow DESC;

SELECT event_name, recipient, status, id_queue, message, date_add
FROM PREFIX_mailsendvx_log
ORDER BY id_mailsendvx_log DESC
LIMIT 50;
```

## Criterios de aceptacion

- Los eventos pueden activar flujos.
- Los flujos pueden arrancar desde `order_created` para la confirmacion inicial del pedido.
- Los flujos postcompra pueden apuntar a estados de pedido especificos sin depender de un filtro manual adicional sobre un evento global.
- Los pasos de flujo crean jobs en cola con fechas correctas.
- El cron procesa solo jobs vencidos y disponibles.
- Los jobs no se duplican accidentalmente.
- Las compras cancelan emails pendientes de carrito abandonado.
- Los errores generan reintentos controlados.
- El panel permite monitorear pendientes, programados, enviados, fallidos y cancelados.

## Riesgos

- Sin idempotencia, un hook repetido puede crear jobs duplicados.
- Un cron sin bloqueo puede procesar dos veces el mismo job si hay ejecuciones paralelas.
- Las condiciones pueden volverse complejas; conviene versionar `conditions_json` y `steps_json`.
- El volumen de cola requiere indices correctos en `status` y `scheduled_at`.
