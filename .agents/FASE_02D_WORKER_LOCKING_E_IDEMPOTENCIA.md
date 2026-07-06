# Fase 02D: worker, locking e idempotencia

## Estado

Implementada en primera iteración funcional.

## Alcance implementado

- servicio `FlowWorkerService` para consumir jobs vencidos,
- front controller `queuecron` para ejecutar el worker por cron seguro con token,
- lock atómico previo al procesamiento vía `status = processing`, `locked_at` y `lock_token`,
- recuperación de locks viejos con `releaseExpiredLocks()`,
- relectura del job ya bloqueado antes de operar,
- envío mediante template referenciado por el job,
- persistencia de estados finales en `mailsendvx_queue`,
- trazabilidad en `mailsendvx_log` enlazada con `id_queue`,
- guardia de idempotencia en el scheduler para no crear jobs equivalentes repetidos.

## Archivos principales

- `src/Service/Flow/FlowWorkerService.php`
- `src/Repository/MailSendVxQueueRepository.php`
- `src/Service/Flow/FlowSchedulerService.php`
- `src/Service/Mail/MailSendVxMailer.php`
- `controllers/front/queuecron.php`
- `config/components/service/services.yml`

## Objetivo

Procesar jobs vencidos de forma segura, sin doble ejecucion accidental y con trazabilidad completa.

## Responsabilidad del worker

- tomar jobs vencidos,
- bloquearlos antes de procesarlos,
- reevaluar condiciones y cancelaciones cuando el `flow_version` del job coincide con la version actual del flow,
- renderizar el template con el payload del job,
- enviar el email,
- actualizar estado final y logs.

## Reglas de locking implementadas

- un job debe pasar por `processing` antes del envio,
- se registra `locked_at`,
- se persiste `lock_token`,
- el lock se toma con update condicionado para evitar doble toma concurrente,
- si el worker cae, los locks viejos vuelven a `pending` mediante `releaseExpiredLocks()`.

## Reglas de idempotencia implementadas

- una ejecucion paralela no puede tomar el mismo job ya bloqueado,
- el scheduler evita insertar jobs equivalentes para mismo `event_name`, `recipient`, `payload_json`, `scheduled_at`, `id_template`, `id_flow`, `flow_version` y `step_id`,
- un job ya enviado o terminal no vuelve a entrar en la seleccion de vencidos,
- los logs del mailer y del worker quedan asociados al `id_queue` para auditar duplicados.

## Reintentos implementados

- incrementar `attempts` por fallo real,
- persistir `last_error`,
- si `attempts < max_attempts`, reprogramar con backoff simple en minutos,
- si supera el maximo, marcar `failed`.

## Flujo tecnico

```txt
Worker busca jobs vencidos
|
Libera locks vencidos
|
Intenta bloquear job
|
Recarga job ya bloqueado
|
Revalida condiciones y cancel_rules
|
Resuelve template del job
|
Envia email
|
Actualiza queue y log
```

## Estados usados en queue

- `scheduled`
- `pending`
- `processing`
- `sent`
- `failed`
- `cancelled`
- `skipped`

## Endpoint operativo

```txt
/module/mailsendvx/queuecron?token={MAILSENDVX_CRON_TOKEN}&limit=50
```

- `token` es obligatorio,
- `limit` es opcional y se limita entre `1` y `500`.

## Riesgos cubiertos

- dobles envios por concurrencia,
- jobs huérfanos en `processing`,
- reintentos infinitos por ausencia de tope,
- falta de trazabilidad entre queue y log.

## Riesgos pendientes o parciales

- la idempotencia del scheduler es aplicativa y no se apoya todavia en un indice unico de base de datos,
- la reevaluacion usa el payload persistido del job; no reconstruye contexto fresco desde entidades vivas,
- el retry usa backoff simple y no tiene politica configurable desde UI,
- aun no existe suite automatizada dedicada al worker.

## Criterios de aceptacion

- El worker procesa solo jobs `pending` o `scheduled` vencidos.
- Un job no puede ser tomado dos veces al mismo tiempo por el lock condicionado.
- Los fallos generan reintentos controlados hasta `max_attempts`.
- El resultado queda reflejado en `queue` y `log`.
- Los jobs cancelados o descartados quedan auditables con mensaje.

## Validacion realizada

- `php -l src/Service/Flow/FlowWorkerService.php`
- `php -l src/Repository/MailSendVxQueueRepository.php`
- `php -l src/Service/Flow/FlowSchedulerService.php`
- `php -l src/Service/Mail/MailSendVxMailer.php`
- `php -l controllers/front/queuecron.php`
