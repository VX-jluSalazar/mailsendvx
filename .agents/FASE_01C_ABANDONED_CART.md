# Fase 01C: abandoned cart

## Estado

Pendiente de implementacion.

Fase 00, Fase 01 y Fase 01B ya dejaron lista la base tecnica necesaria para agregar este evento como una capacidad intermedia antes de Fase 02.

Esta fase debe completarse antes de implementar flujos automatizados basados en `cart_abandoned`.

## Objetivo

Detectar de forma confiable cuando un carrito pasa a considerarse abandonado, registrar el evento interno `cart_abandoned` con dedupe fuerte y dejarlo disponible para dos usos:

- crear templates y enviar emails instantaneos si el negocio lo desea,
- disparar flujos automatizados en la futura Fase 02.

La meta no es todavia construir la secuencia completa de abandoned cart, sino resolver primero el evento, su persistencia, su elegibilidad y su no duplicacion.

## Motivacion

El trigger `cart_abandoned` no debe nacer dentro de Fase 02 como una regla acoplada al scheduler.

Si el concepto de carrito abandonado no queda modelado antes:

- no habra un criterio uniforme de abandono,
- el cron podria registrar el mismo carrito una y otra vez,
- los flows y los emails instantaneos consumiran versiones distintas del mismo evento,
- sera mas dificil cancelar o deduplicar jobs en Fase 02.

Por eso conviene introducir `cart_abandoned` como fase intermedia entre el motor Twig y los flujos.

## Resultado esperado

Al cerrar esta fase, el modulo debe poder:

- identificar carritos elegibles para abandono,
- aplicar una ventana configurable de abandono,
- registrar el evento `cart_abandoned` una sola vez por carrito y contexto valido,
- exponer el evento en la UI de templates,
- enviar un email instantaneo usando las plantillas ya soportadas en Fase 01 y el render Twig de Fase 01B,
- dejar trazabilidad en eventos y logs para futuros flows.

## Definicion funcional de carrito abandonado

Un carrito puede considerarse abandonado cuando cumple simultaneamente:

- tiene email o cliente resoluble,
- tiene al menos un producto,
- no se ha convertido en pedido,
- no ha sido marcado ya como abandonado para la misma ventana o version de criterio,
- supera un umbral minimo de inactividad configurable.

## Configuracion recomendada

La fase debe introducir al menos estos ajustes:

- `MAILSENDVX_ABANDONED_CART_ENABLED`
- `MAILSENDVX_ABANDONED_CART_DELAY_VALUE`
- `MAILSENDVX_ABANDONED_CART_DELAY_UNIT`
- `MAILSENDVX_ABANDONED_CART_REQUIRE_CUSTOMER`
- `MAILSENDVX_ABANDONED_CART_REQUIRE_PRODUCTS`
- `MAILSENDVX_ABANDONED_CART_CRON_BATCH_SIZE`

Ejemplo:

- `delay_value = 1`
- `delay_unit = hour`

Eso significa que un carrito se considera abandonado luego de 1 hora de inactividad bajo las reglas activas.

## Origen del evento

El evento `cart_abandoned` no depende de un hook inmediato unico. Debe resolverse con una estrategia mixta.

### Captura de actividad de carrito

Se recomienda observar y persistir la ultima actividad relevante del carrito cuando ocurran acciones como:

- creacion del carrito,
- adicion o actualizacion de productos,
- asociacion de cliente o email,
- cambio de direccion o transportista,
- cualquier operacion que actualice el valor operativo del carrito.

### Cron detector

Luego, un cron o comando debe escanear carritos elegibles y disparar `cart_abandoned` solo cuando:

- el carrito supera el umbral de abandono,
- no existe pedido asociado,
- no se registro antes el mismo abandono.

## Dedupe obligatorio

Este punto es critico.

Si el cron corre cada 2 horas, no debe registrar el mismo carrito como abandonado en cada ejecucion.

La regla base debe ser:

- un carrito elegible genera como maximo un evento `cart_abandoned` por ciclo de vida de abandono, salvo que vuelva a activarse y luego vuelva a abandonarse.

## Estrategia recomendada de dedupe

### Opcion preferida: estado persistido por carrito

Crear una tabla o estado persistente que guarde al menos:

- `id_cart`
- `id_customer`
- `email`
- `status`
- `last_activity_at`
- `abandoned_at`
- `recovered_at`
- `last_event_hash`
- `date_add`
- `date_upd`

Estados sugeridos:

- `active`
- `abandoned`
- `recovered`

### Regla operativa

1. Mientras el carrito esta `active`, el cron solo evalua elegibilidad.
2. Cuando supera el umbral, pasa a `abandoned` y se registra el evento.
3. Si el cliente reactiva el carrito, se actualiza `last_activity_at` y vuelve a `active`.
4. Solo si vuelve a quedar inactivo y supera otra vez el umbral, puede generarse un nuevo `cart_abandoned`.

### Opcion complementaria: llave de unicidad de evento

Ademas del estado por carrito, conviene persistir una llave unica derivada de:

- `id_cart`
- `abandoned_at` normalizado
- version de criterio

Ejemplo conceptual:

`cart_abandoned:{id_cart}:{abandon_cycle}`

Esto permite reforzar la idempotencia aunque el cron se ejecute en paralelo o repita una corrida.

## Modelo recomendado

### Tabla o agregado de estado

Se recomienda crear una tabla dedicada, por ejemplo:

- `mailsendvx_abandoned_cart`

Campos sugeridos:

- `id_mailsendvx_abandoned_cart`
- `id_cart`
- `id_customer`
- `email`
- `id_shop`
- `id_lang`
- `status`
- `cart_snapshot_json`
- `last_activity_at`
- `abandoned_at`
- `recovered_at`
- `last_event_hash`
- `date_add`
- `date_upd`

### Evento interno

El payload de `cart_abandoned` debe incluir al menos:

- `cart_id`
- `customer_id`
- `customer_email`
- `customer_name`
- `abandoned_at`
- `abandoned_minutes`
- `cart_total`
- `cart_currency`
- `cart_products_count`
- `products`
- `shop_name`
- `shop_url`
- `recovery_url` si esta disponible

## Relacion con Fase 01 y Fase 01B

Una vez exista el evento, debe poder reutilizar inmediatamente las capacidades ya implementadas:

- templates activas por evento,
- envio instantaneo desde el mailer actual,
- logs `sent`, `failed` y `skipped`,
- render Twig para asunto, HTML y texto,
- preview usando fixtures o payload historico.

Eso significa que `cart_abandoned` debe agregarse como evento disponible en:

- selector de evento de templates,
- preview/test send cuando aplique,
- guias de variables disponibles por evento.

## Uso inmediato en emails instantaneos

Aunque la secuencia comercial completa quedara para Fase 02, esta fase ya puede habilitar:

- una plantilla instantanea para `cart_abandoned`,
- un solo envio inmediato al registrarse el abandono,
- validacion de que el renderer Twig reciba productos, totales y recovery URL.

Esto permite probar el evento antes de depender de la cola de Fase 02.

## Alcance funcional

| Subfase | Objetivo | Complejidad | Estado esperado |
| --- | --- | --- | --- |
| 1C.1 Deteccion de actividad | Identificar y persistir actividad relevante del carrito. | Media | `last_activity_at` confiable. |
| 1C.2 Criterio de abandono | Definir elegibilidad por tiempo, productos, email y ausencia de pedido. | Media-alta | Regla uniforme de abandono. |
| 1C.3 Detector por cron | Escanear carritos y marcar abandonados. | Alta | Evento disparable sin hooks directos. |
| 1C.4 Dedupe e idempotencia | Evitar registrar el mismo abandono varias veces. | Alta | Un evento por ciclo de abandono. |
| 1C.5 Evento interno `cart_abandoned` | Registrar evento y payload canonico. | Media | Trigger reusable por Fase 01 y Fase 02. |
| 1C.6 Templates instantaneas | Permitir crear plantilla y enviar al detectar abandono. | Media | Validacion funcional del evento. |
| 1C.7 Recuperacion del carrito | Detectar reactivacion o conversion a pedido. | Media-alta | Reset del estado y cierre del ciclo. |

## Flujo tecnico objetivo

```txt
Actividad de carrito
|
Actualizar last_activity_at y estado operativo
|
Cron detector escanea carritos elegibles
|
Validar umbral, productos, email y ausencia de pedido
|
Validar dedupe del ciclo de abandono
|
Persistir estado abandoned
|
Registrar evento interno cart_abandoned
|
Opcionalmente enviar email instantaneo con template activa
|
Guardar log y snapshot
```

## Reglas recomendadas de elegibilidad

- excluir carritos ya convertidos en pedido,
- excluir carritos vacios,
- excluir carritos sin email resoluble si el negocio exige destinatario,
- excluir carritos ya marcados como `abandoned` y no reactivados,
- evaluar por tienda y contexto si el multishop lo requiere.

## Reglas recomendadas de recuperacion

Un carrito abandonado debe poder salir de ese estado cuando:

- el cliente vuelve a interactuar con el carrito,
- cambia su contenido de forma relevante,
- se convierte en pedido.

La recuperacion debe:

- marcar `recovered_at` cuando aplique,
- evitar nuevos envios del mismo ciclo,
- permitir un nuevo ciclo futuro solo si existe nueva actividad y nuevo abandono.

## Patrones recomendados

- Observer para capturar actividad de carrito.
- Scheduler/Cron Scanner para detectar abandono por tiempo.
- State para `active`, `abandoned` y `recovered`.
- Repository para estado persistido y consultas elegibles.
- Facade para disparar `cart_abandoned` como evento interno reutilizable.
- Idempotency Key para blindar dedupe de cron.

## Dependencias

- Fase 00 implementada.
- Fase 01 implementada para templates y envios instantaneos.
- Fase 01B implementada para render Twig y contextos enriquecidos.

Esta fase debe completarse antes de la parte comercial de `modules/mailsendvx/.agents/FASE_02_FLUJOS_AUTOMATIZADOS.md`.

## Archivos y zonas a intervenir

- `src/Service/` para detector, facade del evento y logica de elegibilidad.
- `src/Repository/` para consultas de carritos elegibles y persistencia de estado.
- `src/Controller/Admin/` o configuracion Symfony para ajustes de abandoned cart.
- tablas nuevas o evolucionadas para persistir estado de abandono.
- integracion con el mailer y logger ya existentes.

## Como probar la funcionalidad

### Prueba 1: detectar abandono una sola vez

1. Configurar abandono a `1 hour`.
2. Crear un carrito con productos y email.
3. Simular inactividad mayor al umbral.
4. Ejecutar cron detector.
5. Confirmar que se registra un solo evento `cart_abandoned`.
6. Ejecutar el cron otra vez sin reactivar el carrito.
7. Confirmar que no se registra un segundo evento para el mismo ciclo.

### Prueba 2: reactivacion y nuevo ciclo

1. Partir de un carrito ya marcado como `abandoned`.
2. Simular nueva actividad del cliente.
3. Confirmar que el carrito vuelve a `active` o equivalente.
4. Esperar otra vez el umbral de abandono.
5. Ejecutar cron detector.
6. Confirmar que ahora si puede registrarse un nuevo evento de un nuevo ciclo.

### Prueba 3: carrito convertido a pedido

1. Crear un carrito elegible.
2. Convertirlo a pedido antes del cron.
3. Ejecutar detector.
4. Confirmar que no se registra `cart_abandoned`.

### Prueba 4: email instantaneo

1. Crear una plantilla activa para `cart_abandoned`.
2. Preparar una plantilla Twig con lista de productos y recovery URL.
3. Ejecutar el detector sobre un carrito elegible.
4. Confirmar que se registra el evento.
5. Confirmar que el email instantaneo se envia o queda log `skipped` si no hay plantilla.

## Consultas utiles de validacion

```sql
SELECT event_name, payload_json, status, date_add
FROM PREFIX_mailsendvx_event
WHERE event_name = 'cart_abandoned'
ORDER BY id_mailsendvx_event DESC
LIMIT 20;

SELECT id_mailsendvx_log, event_name, recipient, status, message, date_add
FROM PREFIX_mailsendvx_log
WHERE event_name = 'cart_abandoned'
ORDER BY id_mailsendvx_log DESC
LIMIT 20;

SELECT *
FROM PREFIX_mailsendvx_abandoned_cart
ORDER BY id_mailsendvx_abandoned_cart DESC
LIMIT 20;
```

## Criterios de aceptacion

- El sistema puede determinar cuando un carrito pasa a estado abandonado.
- El umbral de abandono es configurable.
- `cart_abandoned` se registra una sola vez por ciclo de abandono.
- Una nueva ejecucion de cron no duplica el mismo abandono si el carrito no fue reactivado.
- El estado puede resetearse si el carrito vuelve a estar activo.
- El evento puede usarse para templates y emails instantaneos.
- Fase 02 puede consumir `cart_abandoned` como trigger ya estabilizado.

## Riesgos

- Basarse solo en `date_upd` de carrito puede ser insuficiente si no se define bien que cuenta como actividad real.
- Sin tabla o estado dedicado, el dedupe sera fragil.
- Carritos guest y clientes logueados pueden requerir reglas de identidad distintas.
- Un recovery URL mal resuelto puede limitar el valor comercial del email.

## Nota de implementacion

La prioridad de esta fase debe ser:

1. definir modelo de estado del carrito abandonado,
2. resolver dedupe fuerte por ciclo de abandono,
3. exponer `cart_abandoned` como evento canonico,
4. habilitar template instantanea para validarlo,
5. recien despues usarlo como trigger principal en Fase 02.
