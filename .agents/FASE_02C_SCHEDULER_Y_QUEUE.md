# Fase 02C: scheduler y queue

## Objetivo

Transformar los steps de un flow en jobs programados y persistidos.

## Responsabilidad del scheduler

Cuando llega un evento:

1. buscar flows activos por `trigger_event`,
2. validar condiciones de entrada,
3. validar `context_type`,
4. recorrer `steps_json`,
5. calcular `scheduled_at` para cada step,
6. crear uno o varios registros en cola.

## Reglas para delays

- `value = 0` debe ser valido.
- `unit` puede ser `minute`, `hour`, `day` o `week`.
- `mode` puede ser `immediate`, `after_trigger` o `after_previous_step`.
- `scheduled_at` debe persistirse al crear el job.
- editar un flow no debe mutar jobs ya calendarizados salvo reprogramacion explicita.

## Campos recomendados para `mailsendvx_queue`

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

## Estados iniciales recomendados

- `pending`
- `scheduled`
- `processing`
- `sent`
- `failed`
- `cancelled`
- `skipped`

## Flujo tecnico

```txt
Evento detectado
|
Buscar flows por trigger
|
Validar conditions y context_type
|
Recorrer steps
|
Calcular scheduled_at
|
Persistir jobs en queue
```

## Pruebas recomendadas

### Prueba 1

1. Crear un flow `customer_registered`.
2. Definir `context_type = customer`.
3. Agregar un step con delay `5 minutes`.
4. Disparar el evento.
5. Confirmar que se crea un job `scheduled`.

### Prueba 2

1. Crear un step con `delay.value = 0`.
2. Disparar el evento.
3. Confirmar que el job queda vencido de inmediato.

## Criterios de aceptacion

- El scheduler crea jobs desde steps.
- `scheduled_at` queda persistido correctamente.
- El job mantiene referencia a flow, step y template.
- El scheduler no depende de buscar templates por `event_name`.
