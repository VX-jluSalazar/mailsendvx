# Fase 02G: pruebas automatizadas

## Objetivo

Agregar una capa de pruebas automatizadas que proteja la arquitectura de flows, queue, worker y reglas comerciales antes de seguir ampliando UI y casos de negocio.

## Alcance minimo recomendado

- pruebas unitarias de repositorios con foco en normalizacion de datos,
- pruebas unitarias de servicios de scheduling,
- pruebas unitarias de evaluacion de condiciones,
- pruebas unitarias del worker y transiciones de queue,
- pruebas de integracion liviana para eventos criticos,
- fixtures reutilizables para payloads `order`, `cart`, `customer` y `newsletter`.

## Componentes prioritarios a cubrir

### Flows

- validacion de `trigger_event` y `context_type`,
- normalizacion de `steps_json`,
- rechazo de templates incompatibles,
- versionado y persistencia base.

### Templates

- guardado con `event_name = NULL`,
- compatibilidad por `context_type`,
- resolucion de wrapper por defecto,
- preview basado en `context_type`.

### Scheduler

- busqueda de flows activos por `trigger_event`,
- validacion de condiciones de entrada,
- calculo correcto de `scheduled_at`,
- creacion de jobs con `id_flow`, `flow_version`, `step_id` e `id_template`,
- comportamiento correcto para delays `0`, `minute`, `hour`, `day` y `week`.

### Queue y worker

- cambio de estados `scheduled`, `pending`, `processing`, `sent`, `failed`, `skipped`,
- incremento de `attempts`,
- respeto de `max_attempts`,
- manejo de `locked_at` y `lock_token`,
- prevencion de doble procesamiento.

### Cancelaciones y reglas

- reevaluacion de `conditions`,
- cancelacion por contexto cambiado,
- jobs marcados como `cancelled` o `skipped` con razon persistida.

## Tipos de prueba recomendados

### Pruebas unitarias

Para clases puras o casi puras:

- `FlowSchedulerService`
- `FlowConditionEvaluator`
- `MailSendVxFlowRepository`
- `MailSendVxQueueRepository`
- `TemplateAdminService`

### Pruebas de integracion

Para flujos donde importa el cableado real:

- evento capturado -> scheduler crea jobs,
- job vencido -> worker procesa -> queue/log actualizados,
- template reusable + flow compatible -> envio correcto.

### Pruebas de regresion

Casos que no deben romperse con nuevas fases:

- eventos instantaneos de Fase 01,
- `cart_abandoned`,
- templates con wrapper por defecto,
- rutas de Back Office ya funcionales.

## Fixtures recomendados

- reusar `.agents/fixtures/order.json`
- reusar `.agents/fixtures/cart.json`
- reusar `.agents/fixtures/suscriber.json`
- agregar fixtures de flows con:
  - un step inmediato,
  - un step diferido,
  - varios steps encadenados,
  - templates incompatibles para pruebas negativas.

## Casos minimos obligatorios

### Caso 1

Flow `customer_registered` con `context_type = customer` y step a `5 minutes`.

Esperado:

- se crea job en `scheduled`,
- `scheduled_at` es mayor que `date_add`,
- `id_flow`, `step_id` e `id_template` quedan persistidos.

### Caso 2

Flow con delay `0`.

Esperado:

- el job nace como `pending` o inmediatamente procesable,
- no se pierde la referencia al flow ni al template.

### Caso 3

Flow con template de `context_type` incompatible.

Esperado:

- el flow no se guarda o el scheduler no crea jobs invalidos,
- el error queda expuesto de forma clara.

### Caso 4

Worker toma un job ya vencido.

Esperado:

- el job pasa a `processing`,
- si el envio sale bien pasa a `sent`,
- si falla incrementa `attempts`.

### Caso 5

Dos ejecuciones paralelas intentan tomar el mismo job.

Esperado:

- solo una ejecucion logra bloquearlo,
- no existe doble envio.

## Infraestructura recomendada

- usar PHPUnit como base,
- separar `Unit` e `Integration`,
- helpers para construir payloads y rows de flow,
- factories o builders de test para templates, queue y flows,
- mocks para mail provider y para repositorios donde convenga aislar comportamiento.

## Criterios de aceptacion

- Existe una suite automatizada ejecutable por CLI.
- Scheduler, queue y worker tienen cobertura minima de casos felices y negativos.
- Los errores criticos de compatibilidad y locking quedan cubiertos por pruebas.
- La suite protege regresiones de Fase 01 y Fase 02.
