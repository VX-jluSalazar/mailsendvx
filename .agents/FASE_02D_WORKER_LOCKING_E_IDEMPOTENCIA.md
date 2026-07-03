# Fase 02D: worker, locking e idempotencia

## Objetivo

Procesar jobs vencidos de forma segura, sin doble ejecucion accidental y con trazabilidad completa.

## Responsabilidad del worker

- tomar jobs vencidos,
- bloquearlos antes de procesarlos,
- reevaluar condiciones y cancelaciones,
- renderizar el template con el payload del job,
- enviar el email,
- actualizar estado final y logs.

## Reglas de locking recomendadas

- un job debe pasar por `processing` antes del envio,
- debe registrarse `locked_at`,
- debe existir `lock_token` o mecanismo equivalente,
- si el worker cae, debe haber estrategia de recuperacion de locks viejos.

## Reglas de idempotencia

- el mismo job no debe enviarse dos veces,
- una ejecucion paralela no debe tomar el mismo job ya bloqueado,
- un error de cron repetido no debe duplicar jobs ya procesados.

## Reintentos

- incrementar `attempts` por fallo real,
- persistir `last_error`,
- si `attempts < max_attempts`, reprogramar,
- si supera el maximo, marcar `failed`.

## Flujo tecnico

```txt
Worker busca jobs vencidos
|
Intenta bloquear job
|
Revalida condiciones
|
Renderiza template
|
Envia email
|
Actualiza queue y log
```

## Riesgos a cubrir

- dobles envios por concurrencia,
- jobs huérfanos en `processing`,
- reintentos infinitos,
- diferencias entre payload programado y payload renderizado.

## Criterios de aceptacion

- El worker procesa solo jobs disponibles.
- Un job no puede ser tomado dos veces al mismo tiempo.
- Los fallos generan reintentos controlados.
- El resultado queda reflejado en `queue` y `log`.
