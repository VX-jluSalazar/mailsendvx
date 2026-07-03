# Fase 02: flujos automatizados

## Estado

Pendiente de implementacion.

La base necesaria ya existe:

- Fase 00 implementada como base tecnica del modulo, instalacion, tablas, configuracion, dashboard y logs.
- Fase 01 implementada para eventos inmediatos, templates activas y taxonomia moderna de eventos de pedido.
- Fase 01B implementada en primera iteracion funcional con motor Twig, contextos enriquecidos, preview mejorado y wrappers editables.
- Fase 01C implementada para `cart_abandoned` como trigger canonico y deduplicado.

## Objetivo general

Construir un motor de automatizacion capaz de:

- reaccionar a eventos internos ya normalizados,
- iniciar flujos activos por trigger,
- programar pasos con tiempos de espera configurables,
- renderizar cada envio con Twig y contexto enriquecido,
- reevaluar condiciones antes de enviar,
- cancelar o reprogramar jobs segun reglas de negocio,
- registrar trazabilidad completa en cola y logs.

## Decision de arquitectura

Para esta fase se separan tres conceptos:

- `event_name`: define disparo automatico directo.
- `trigger_event`: define que evento activa un flow.
- `context_type`: define compatibilidad de payload.

La regla base queda asi:

- un template puede tener `event_name` o no tenerlo,
- un flow siempre tiene `trigger_event`,
- flow y template deben declarar `context_type`,
- un flow puede usar dos o mas templates a traves de sus steps,
- los flows resuelven templates por `template_id`, no por `event_name`.

## Subfases documentadas

- `02A` Modelo base de flows: `modules/mailsendvx/.agents/FASE_02A_MODELO_BASE_DE_FLOWS.md`
- `02B` Templates reutilizables: `modules/mailsendvx/.agents/FASE_02B_TEMPLATES_REUTILIZABLES.md`
- `02C` Scheduler y queue: `modules/mailsendvx/.agents/FASE_02C_SCHEDULER_Y_QUEUE.md`
- `02D` Worker, locking e idempotencia: `modules/mailsendvx/.agents/FASE_02D_WORKER_LOCKING_E_IDEMPOTENCIA.md`
- `02E` Condiciones y cancelaciones: `modules/mailsendvx/.agents/FASE_02E_CONDICIONES_Y_CANCELACIONES.md`
- `02F` UI operativa y casos comerciales: `modules/mailsendvx/.agents/FASE_02F_UI_OPERATIVA_Y_CASOS_COMERCIALES.md`
- `02G` Pruebas automatizadas: `modules/mailsendvx/.agents/FASE_02G_PRUEBAS_AUTOMATIZADAS.md`

## Orden recomendado de implementacion

1. `02A` para estabilizar el modelo de flow y steps.
2. `02B` para desacoplar templates de `event_name`.
3. `02C` para programar jobs persistidos.
4. `02D` para procesar cola en forma segura.
5. `02E` para evitar envios fuera de contexto.
6. `02F` para cerrar UI y casos comerciales iniciales.
7. `02G` para blindar scheduler, queue, worker y regresiones con pruebas automatizadas.

## Dependencias heredadas

- motor Twig como renderer oficial,
- `TemplateContextPayloadBuilder` y builders de segmentos como fuente de datos,
- taxonomia moderna de eventos como fuente de triggers,
- `context_type` como contrato de compatibilidad entre flow, step y template,
- sistema de logs ya existente para observabilidad.

## Nota de implementacion

Fase 02 no debe reabrir la arquitectura de Fase 01.

La prioridad correcta es:

1. definir modelo estable de flows, steps y templates reutilizables,
2. soportar delays configurables por paso,
3. garantizar idempotencia y locking,
4. conectar el worker con el mailer Twig ya existente,
5. recien despues construir la UI operativa completa,
6. formalizar una suite de pruebas antes de seguir ampliando casos comerciales.
