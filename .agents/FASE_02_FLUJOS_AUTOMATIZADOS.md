# Fase 02: flujos automatizados

## Estado

Pendiente de implementacion.

La base necesaria ya existe:

- Fase 00 implementada como base tecnica del modulo, instalacion, tablas, configuracion, dashboard y logs.
- Fase 01 implementada para eventos inmediatos, templates activas y taxonomia moderna de eventos de pedido.
- Fase 01B implementada en primera iteracion funcional con motor Twig, contextos enriquecidos, preview mejorado y wrappers editables.

Por lo tanto, esta fase ya no debe pensarse como una extension del MVP de placeholders simples ni como una capa especulativa sobre eventos incompletos. Debe construirse directamente sobre la arquitectura moderna ya disponible.

## Objetivo

Construir un motor de automatizacion capaz de:

- reaccionar a eventos internos ya normalizados,
- iniciar flujos activos por trigger,
- programar pasos con tiempos de espera configurables,
- renderizar cada envio con Twig y contexto enriquecido,
- reevaluar condiciones antes de enviar,
- cancelar o reprogramar jobs segun reglas de negocio,
- registrar trazabilidad completa en cola y logs.

Esta fase convierte Mail Send VX en un motor de automatizacion comercial usable para abandoned cart, postcompra, onboarding y nurturing basico.

## Dependencia de arquitectura

La implementacion debe apoyarse en capacidades ya definidas e implementadas en fases previas:

- `order_created` como trigger canonico de pedido confirmado.
- `order_status_changed` como trigger generico.
- `order_status_changed_{state_key}` como trigger especifico por estado destino.
- `customer_registered` y `newsletter_registered` como triggers inmediatos reutilizables en flows.
- `cart_abandoned` como trigger intermedio ya deduplicado y estabilizado por Fase 01C.
- motor Twig para `subject`, `html_content` y `text_content`.
- context builders por dominio y payloads enriquecidos para pedido, cliente y newsletter.
- wrappers editables desde Back Office.
- dashboard y logs como base de monitoreo.

No debe construirse nada nuevo sobre `order_status_updated` salvo compatibilidad interna temporal si hubiera datos legacy pendientes. La UI y la logica nueva deben usar la taxonomia moderna de eventos.

## Objetivo tecnico real de esta fase

La fase 02 debe introducir tres capacidades que hoy no existen de forma completa:

1. `Flow Engine` para resolver que flujos se activan cuando llega un evento.
2. `Scheduler/Queue Engine` para convertir pasos en jobs programados.
3. `Worker/Cron Engine` para procesar jobs vencidos con control de estado, reintentos e idempotencia.

## Tiempos de espera configurables

Los tiempos de espera no deben codificarse de forma fija en codigo ni depender de presets rigidos como "1 hora", "24 horas" o "72 horas".

Cada paso del flujo debe poder definir su propia espera mediante una configuracion explicita.

### Contrato recomendado para delays por paso

Cada paso debe guardar una estructura similar a:

```json
{
  "type": "email",
  "template_id": 12,
  "delay": {
    "value": 24,
    "unit": "hour",
    "mode": "after_previous_step"
  },
  "conditions": [],
  "cancel_rules": []
}
```

### Campos recomendados

| Campo | Uso |
| --- | --- |
| `delay.value` | Cantidad numerica de espera. |
| `delay.unit` | Unidad: `minute`, `hour`, `day` o `week`. |
| `delay.mode` | Referencia temporal del paso. |
| `delay.timezone` | Opcional si mas adelante se requieren ejecuciones por zona horaria. |

### Modos iniciales recomendados

| Modo | Significado |
| --- | --- |
| `immediate` | Se agenda para el momento actual. |
| `after_trigger` | Se agenda relativo al momento del evento que disparo el flujo. |
| `after_previous_step` | Se agenda relativo al paso anterior del mismo flujo. |

### Reglas de implementacion para delays

- `value = 0` debe ser valido y equivalente a envio inmediato.
- La combinacion `value + unit` debe convertirse a segundos o `DateInterval` en una sola capa de dominio.
- El calculo de `scheduled_at` debe persistirse al crear el job para evitar recalculos ambiguos.
- Si un flujo se edita, los jobs ya programados no deben mutar retroactivamente salvo que se implemente una reprogramacion explicita.
- La UI debe exponer los delays como campos configurables por paso, no como texto libre.

## Alcance funcional

| Subfase | Objetivo | Complejidad | Estado esperado |
| --- | --- | --- | --- |
| 2.1 Registro de triggers para flows | Reutilizar la taxonomia ya implementada y definir triggers disponibles en UI. | Media | Lista de triggers consistente con Fase 01. |
| 2.2 Modelo de flujo | Crear flujos con nombre, trigger, estado, condiciones, pasos y versionado basico. | Alta | Flujo persistible y editable. |
| 2.3 Modelo de pasos con delay configurable | Permitir pasos inmediatos o diferidos con `value`, `unit` y `mode`. | Alta | Scheduler basado en configuracion, no en tiempos fijos. |
| 2.4 Cola de envios | Crear jobs por paso, con `scheduled_at`, estado, intentos y trazabilidad. | Alta | Jobs programados correctamente. |
| 2.5 Cron o comando worker | Procesar solo jobs vencidos, bloquear concurrencia y actualizar estados. | Alta | Procesamiento seguro e idempotente. |
| 2.6 Reevaluacion de condiciones | Validar reglas al iniciar el flujo y tambien antes de enviar. | Alta | Menos envios fuera de contexto. |
| 2.7 Cancelacion de jobs | Cancelar pendientes por compra, baja, cambio de estado o regla de negocio. | Alta | Jobs anulables sin borrado fisico. |
| 2.8 Reintentos automaticos | Reprogramar fallos controlados hasta un maximo configurable. | Media-alta | Recuperacion operativa. |
| 2.9 Flujos comerciales iniciales | Implementar abandoned cart, postcompra y suscriptor. | Alta | Casos base listos para uso. |
| 2.10 Panel operativo | Mostrar cola, proximos envios, fallidos, cancelados y resumen por flujo. | Media-alta | Monitoreo desde Back Office. |

## Alineacion con Fase 00, Fase 01 y Fase 01B

### Base heredada de Fase 00

- tablas `mailsendvx_flow`, `mailsendvx_queue` y `mailsendvx_log` ya contempladas a nivel arquitectonico,
- configuracion general y token de cron ya previstos,
- dashboard y logs existentes como base para evolucion operativa,
- provider inicial `Mail::Send()` ya disponible como transporte.

### Base heredada de Fase 01

- eventos canonicos y especificos de pedido ya definidos,
- compatibilidad con `customer_registered` y `newsletter_registered`,
- templates activas por evento ya presentes,
- logs con `sent`, `failed` y `skipped` ya normalizados.

### Base heredada de Fase 01B

- render Twig disponible para asunto, HTML y texto,
- contextos enriquecidos por dominio,
- preview sobre payload historico,
- wrappers editables desde Back Office,
- compatibilidad temporal con templates legacy.

### Base heredada de Fase 01C

- evento `cart_abandoned` ya definido como trigger canonico,
- criterio uniforme para considerar un carrito abandonado,
- dedupe por ciclo de abandono resuelto antes de entrar en flows,
- soporte de template instantanea para validar el evento sin depender aun de cola.

### Impacto practico en Fase 02

La cola y los flujos deben usar directamente:

- el motor Twig como renderer oficial,
- los context builders existentes como fuente de datos,
- la taxonomia moderna de eventos como fuente de triggers,
- el sistema de logs ya existente para observabilidad.

No debe abrirse una segunda via de render ni una segunda taxonomia de eventos solo para automatizaciones.

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
| Cliente | `customer_registered` |
| Suscriptor | `newsletter_registered` |
| Carrito | `cart_abandoned` |

## Flujo tecnico objetivo

```txt
Evento detectado
|
Flow Engine busca flujos activos por trigger
|
Condition Engine valida reglas de entrada
|
Scheduler genera uno o varios jobs segun steps_json
|
Cada job persiste su scheduled_at y su snapshot minimo
|
Cron/Worker toma jobs vencidos y disponibles
|
Revalida condiciones y reglas de cancelacion
|
Renderiza template con Twig y contexto enriquecido
|
Envia email con provider actual
|
Actualiza estado, intentos, log y siguiente accion
```

## Modelo funcional recomendado

### Flujo

Campos recomendados para `mailsendvx_flow`:

- `id_mailsendvx_flow`
- `name`
- `trigger_event`
- `description`
- `active`
- `priority`
- `conditions_json`
- `steps_json`
- `version`
- `date_add`
- `date_upd`

### Step

Cada step dentro de `steps_json` debe contemplar:

- `id`
- `type`
- `template_id`
- `delay`
- `conditions`
- `cancel_rules`
- `active`

### Job de cola

Campos recomendados para `mailsendvx_queue`:

- `id_mailsendvx_queue`
- `id_flow`
- `flow_version`
- `step_id`
- `event_name`
- `id_template`
- `recipient`
- `payload_json`
- `status`
- `attempts`
- `max_attempts`
- `scheduled_at`
- `processed_at`
- `locked_at`
- `lock_token`
- `last_error`
- `cancel_reason`
- `date_add`
- `date_upd`

## Estados de cola recomendados

| Estado | Uso |
| --- | --- |
| `pending` | Registro creado, aun no calendarizado por completo o pendiente de preparacion. |
| `scheduled` | Job listo para ejecutarse en `scheduled_at`. |
| `processing` | Job tomado por worker. |
| `sent` | Envio exitoso. |
| `failed` | Fallo definitivo o sin reintentos restantes. |
| `cancelled` | Job anulado por regla de negocio o accion operativa. |
| `skipped` | Job omitido por condicion no cumplida al reevaluar. |

## Flujos comerciales iniciales

### Carrito abandonado

Secuencia sugerida totalmente configurable:

- Email 1: delay configurable, por ejemplo `1 hour`.
- Email 2: delay configurable, por ejemplo `24 hours`.
- Email 3: delay configurable, por ejemplo `72 hours`.
- Cancelar pendientes si el carrito se convierte en pedido o si el cliente ya no es elegible.

### Postcompra

- Email de confirmacion inmediata al ocurrir `order_created`.
- Email de agradecimiento con delay configurable despues de `order_status_changed_payment_accepted`.
- Email de seguimiento con delay configurable cuando ocurra `order_status_changed_shipped`.
- Email de review con delay configurable cuando ocurra `order_status_changed_delivered`.

### Suscriptores

- Email de bienvenida inmediato o diferido al ocurrir `newsletter_registered`.
- Email educativo con delay configurable.
- Email de incentivo con delay configurable y condicion opcional.

## Condiciones por flujo y por paso

La fase 02 debe soportar al menos dos niveles de condiciones:

- condiciones de entrada del flujo, evaluadas al dispararse el trigger,
- condiciones del paso, reevaluadas justo antes de ejecutar el job.

### Filtros iniciales recomendados

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

Las reglas de cancelacion deben poder aplicarse sin borrar historico.

Casos iniciales:

- cancelar jobs de `cart_abandoned` si existe un pedido asociado al carrito,
- cancelar jobs de nurturing si el cliente se da de baja,
- cancelar jobs postcompra si el flujo depende de un estado que luego deja de ser valido,
- cancelar manualmente desde panel operativo si un administrador lo decide.

## Reintentos automaticos

Los reintentos deben ser configurables a nivel global y opcionalmente sobrescribibles por step en una iteracion posterior.

Reglas base:

- incrementar `attempts` en cada fallo real de provider,
- si `attempts < max_attempts`, reprogramar el job,
- persistir `last_error`,
- pasar a `failed` cuando se supere el maximo.

El backoff de reintento tambien debe ser configurable y no hardcodeado.

Ejemplo recomendado:

- `retry_delay_value`
- `retry_delay_unit`
- `max_attempts`

## Patrones recomendados

- Event Driven Architecture para eventos internos.
- Command y Command Handler para crear jobs y procesarlos.
- Queue/Scheduler para envios diferidos.
- Chain of Responsibility para condiciones.
- State para transiciones de cola.
- Strategy para providers y renderers.
- Repository para persistencia.

## Dependencias

- Fase 00 implementada.
- Fase 01 implementada y usada como taxonomia oficial de eventos.
- Fase 01B implementada y usada como motor oficial de render.
- Fase 01C implementada para disponer de `cart_abandoned` como evento estable y deduplicado.
- Token de cron configurado.
- Templates activas listas para ser asociadas a flows y steps.

## Archivos y zonas a intervenir

Segun la arquitectura ya existente, esta fase deberia concentrarse alrededor de:

- `src/Service/` para engine de flows, scheduler, worker y condiciones,
- `src/Repository/` o repositorios equivalentes para flujos y cola,
- `src/Controller/Admin/` para UI operativa de flows y queue,
- formularios Symfony para crear flows y steps,
- tablas `mailsendvx_flow` y `mailsendvx_queue`,
- integracion con el logger y el mailer ya existentes.

## Como probar la funcionalidad

### Prueba 1: programar un email en cola

1. Crear un flujo activo con trigger `customer_registered`.
2. Configurar un step con delay configurable, por ejemplo `5 minutes`.
3. Registrar un cliente de prueba.
4. Revisar que se cree un registro en `PREFIX_mailsendvx_queue` con estado `scheduled`.
5. Confirmar que `scheduled_at` respeta exactamente el delay configurado.

### Prueba 2: delay inmediato

1. Crear un flujo con `delay.value = 0` y `delay.mode = after_trigger`.
2. Disparar el evento.
3. Confirmar que el job queda vencido de inmediato o con diferencia minima controlada.
4. Ejecutar worker.
5. Confirmar que el envio ocurre sin requerir un caso especial hardcodeado.

### Prueba 3: procesar cron

1. Crear o ajustar un job con `scheduled_at` en el pasado.
2. Ejecutar el cron o comando correspondiente.
3. Confirmar que el job pasa por `processing`.
4. Confirmar que termina como `sent`, `failed`, `skipped` o `cancelled`.
5. Revisar que se cree un log asociado.

### Prueba 4: carrito abandonado

1. Crear un carrito con email identificado.
2. Esperar o simular la condicion de abandono.
3. Confirmar que se crean varios jobs segun los delays configurados en el flujo.
4. Convertir el carrito en pedido antes del siguiente envio.
5. Confirmar que los jobs pendientes quedan `cancelled`.

### Prueba 5: reintentos

1. Forzar un error de provider con un destinatario o configuracion invalida.
2. Ejecutar worker.
3. Confirmar que `attempts` aumenta.
4. Confirmar que se reprograma si quedan intentos.
5. Confirmar que termina en `failed` al superar `max_attempts`.

### Prueba 6: condiciones por flujo

1. Crear un flujo condicionado por idioma, tienda o total de pedido.
2. Ejecutar eventos que cumplan y no cumplan la condicion.
3. Confirmar que solo se programan o envian los casos validos.
4. Confirmar que los casos rechazados quedan `skipped` o no generan cola, segun la regla definida.

### Prueba 7: render Twig desde cola

1. Asociar al flujo una plantilla Twig con listas o condicionales.
2. Programar un job desde un evento de pedido real.
3. Ejecutar worker.
4. Confirmar que el email se renderiza con el contexto enriquecido de Fase 01B.

## Consultas utiles de validacion

```sql
SELECT id_mailsendvx_queue, id_flow, step_id, event_name, recipient, status, attempts, scheduled_at, processed_at, last_error
FROM PREFIX_mailsendvx_queue
ORDER BY id_mailsendvx_queue DESC
LIMIT 50;

SELECT id_mailsendvx_flow, name, trigger_event, active, version, date_add, date_upd
FROM PREFIX_mailsendvx_flow
ORDER BY id_mailsendvx_flow DESC;

SELECT event_name, recipient, status, id_queue, message, date_add
FROM PREFIX_mailsendvx_log
ORDER BY id_mailsendvx_log DESC
LIMIT 50;
```

## Criterios de aceptacion

- Los eventos actuales pueden activar flujos sin crear una taxonomia paralela.
- Los flujos pueden arrancar desde `order_created`, `order_status_changed_{state_key}`, `customer_registered`, `newsletter_registered` y `cart_abandoned`.
- Cada step soporta tiempos de espera configurables por `value`, `unit` y `mode`.
- Los pasos crean jobs con `scheduled_at` correcto y persistido.
- El worker procesa solo jobs vencidos y disponibles.
- El render de cola usa Twig y el contexto enriquecido ya existente.
- Los jobs no se duplican accidentalmente ante hooks o cron repetidos.
- Las compras cancelan emails pendientes de carrito abandonado cuando corresponde.
- Los errores generan reintentos controlados y configurables.
- El panel permite monitorear pendientes, programados, enviados, fallidos y cancelados.

## Riesgos

- Sin idempotencia, un hook repetido puede crear jobs duplicados.
- Un worker sin bloqueo puede procesar dos veces el mismo job si hay ejecuciones paralelas.
- Las condiciones pueden crecer rapido; conviene versionar `conditions_json` y `steps_json`.
- El volumen de cola requiere indices correctos en `status`, `scheduled_at`, `id_flow` y campos de locking.
- Editar un flujo activo sin estrategia de versionado puede dejar jobs viejos con semantica distinta.

## Nota de implementacion

La implementacion de esta fase debe tratar a Fase 01 y Fase 01B como base cerrada y reutilizable, no como algo a rehacer.

La prioridad correcta es:

1. definir modelo estable de flows, steps y queue,
2. soportar delays configurables por paso,
3. garantizar idempotencia y locking,
4. conectar el worker con el mailer Twig ya existente,
5. recien despues construir la UI operativa completa.
