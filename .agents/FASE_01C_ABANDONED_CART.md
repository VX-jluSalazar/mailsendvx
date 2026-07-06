# Fase 01C: abandoned cart

## Estado

Implementada en primera iteracion funcional.

La documentacion anterior marcaba esta fase como pendiente, pero el modulo ya incluye las piezas base necesarias para detectar abandono, deduplicar ciclos y disparar `cart_abandoned` como evento utilizable por templates instantaneas.

## Estado real de la implementacion

### Ya implementado

- Evento canonico `cart_abandoned` agregado a `ModuleConstants` y expuesto en el Back Office de templates.
- Configuracion de abandono con:
  - `MAILSENDVX_ABANDONED_CART_ENABLED`
  - `MAILSENDVX_ABANDONED_CART_DELAY_VALUE`
  - `MAILSENDVX_ABANDONED_CART_DELAY_UNIT`
  - `MAILSENDVX_ABANDONED_CART_REQUIRE_CUSTOMER`
  - `MAILSENDVX_ABANDONED_CART_REQUIRE_PRODUCTS`
  - `MAILSENDVX_ABANDONED_CART_CRON_BATCH_SIZE`
- Persistencia dedicada en `PREFIX_mailsendvx_abandoned_cart`.
- Repositorio `MailSendVxAbandonedCartRepository` para:
  - localizar carritos elegibles,
  - detectar carritos recuperables,
  - guardar estado por carrito.
- Servicio `AbandonedCartService` para:
  - calcular la ventana de abandono,
  - procesar lotes elegibles,
  - evitar duplicados por `last_event_hash`,
  - registrar eventos internos,
  - disparar envio instantaneo si existe template activa.
- Endpoint cron `controllers/front/abandonedcartcron.php` protegido por token.
- Deteccion de recuperacion cuando el carrito vuelve a moverse o se convierte en pedido.
- Contexto enriquecido para plantillas de carrito abandonado mediante `CartTemplateContextBuilder`.
- Productos del carrito normalizados a la misma forma base usada por `order.products`, mantenidos dentro de `cart.items` por compatibilidad.
- Integracion completa con logs y eventos historicos del modulo.

### Pendiente recomendado

- endurecer mas la nocion de "actividad real" si el negocio quiere distinguir cambios menores de cambios relevantes,
- agregar metricas operativas mas visibles en dashboard para volumen de carritos capturados, recuperados y omitidos,
- incorporar fixtures historicos seleccionables para previews de `cart_abandoned`,
- evaluar reglas mas finas para guest carts y escenarios multishop avanzados.

## Objetivo de la fase

Modelar `cart_abandoned` como evento interno reutilizable antes de Fase 02, con criterio uniforme, dedupe fuerte y compatibilidad directa con el sistema de templates y el mailer ya implementados.

## Definicion funcional aplicada

Un carrito se considera elegible cuando:

- no tiene pedido asociado,
- supera el umbral de inactividad configurado,
- cumple las reglas de cliente y productos activas en configuracion,
- no fue ya capturado para el mismo ciclo de abandono.

## Arquitectura implementada

### Persistencia de estado

La tabla `PREFIX_mailsendvx_abandoned_cart` guarda el estado del ciclo del carrito con campos equivalentes a:

- `id_cart`
- `id_customer`
- `email`
- `id_shop`
- `id_lang`
- `status`
- `cart_snapshot`
- `last_activity_at`
- `abandoned_at`
- `recovered_at`
- `last_event_hash`
- `date_add`
- `date_upd`

Estados operativos usados:

- `abandoned`
- `recovered`

El flujo actual no necesita persistir activamente un estado `active` en cada movimiento para que el dedupe funcione, porque usa la fecha del carrito y el cambio de hash como referencia de un nuevo ciclo.

### Regla de dedupe actual

La captura usa un hash derivado de:

- `id_cart`
- `cart.date_upd`
- `cart_abandoned`

Conceptualmente:

`sha1(id_cart | date_upd | cart_abandoned)`

Mientras el carrito siga abandonado con la misma actividad base, una nueva corrida de cron no genera un segundo evento.

Si el carrito cambia despues del abandono y vuelve a pasar el umbral, el sistema permite un nuevo ciclo.

### Deteccion y recuperacion

Flujo efectivo:

```txt
Cron protegido por token
|
Calcular cutoff segun delay configurado
|
Buscar carritos elegibles sin pedido
|
Validar dedupe contra estado persistido
|
Construir contexto canonico del carrito
|
Guardar estado abandoned + snapshot + hash
|
Registrar evento cart_abandoned
|
Enviar template instantanea si existe
|
Marcar recovered cuando el carrito cambia o se convierte en pedido
```

## Relacion con Fase 01 y Fase 01B

Esta fase ya reutiliza correctamente:

- templates activas por evento,
- render Twig para `subject`, `html_content` y `text_content`,
- preview con payload historico cuando existe evento previo,
- logs `sent`, `failed` y `skipped`,
- contexto enriquecido con productos y `recovery_url`.

## Archivos principales

- `src/Service/Cart/AbandonedCartService.php`
- `src/Repository/MailSendVxAbandonedCartRepository.php`
- `src/Service/ContextBuilder/CartTemplateContextBuilder.php`
- `src/Service/ContextBuilder/CartContextSegmentBuilder.php`
- `src/Service/ContextBuilder/ProductsContextBuilder.php`
- `src/Service/ContextBuilder/TemplateContextPayloadBuilder.php`
- `controllers/front/abandonedcartcron.php`
- `src/Install/DatabaseInstaller.php`
- `src/Install/ConfigurationInstaller.php`
- `src/Form/ConfigurationDataConfiguration.php`
- `src/Form/Type/ConfigurationFormType.php`

## Como probar la funcionalidad

### Prueba 1: captura unica por ciclo

1. Activar abandoned cart y configurar `1 hour`.
2. Crear un carrito con productos y email valido.
3. Forzar inactividad mayor al umbral.
4. Ejecutar el cron del modulo.
5. Confirmar que aparece un solo evento `cart_abandoned`.
6. Ejecutar el cron otra vez sin tocar el carrito.
7. Confirmar que no se agrega un duplicado.

### Prueba 2: recuperacion y nuevo abandono

1. Partir de un carrito ya capturado como `abandoned`.
2. Modificar el carrito o convertirlo en pedido.
3. Confirmar que el estado pasa a `recovered`.
4. Reactivar el carrito y dejarlo inactivo otra vez.
5. Ejecutar el cron.
6. Confirmar que se crea un nuevo evento para el nuevo ciclo.

### Prueba 3: envio instantaneo

1. Crear una plantilla activa para `cart_abandoned`.
2. Ejecutar el cron sobre un carrito elegible.
3. Confirmar que se registra el evento.
4. Confirmar que el log marque `sent` o `skipped` de forma consistente.

## Consultas utiles de validacion

```sql
SELECT event_name, payload, status, date_add
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

## Criterios de aceptacion cubiertos

- El sistema detecta cuando un carrito entra en abandono.
- El umbral de abandono es configurable.
- `cart_abandoned` no se duplica en cada corrida del cron para el mismo ciclo.
- El ciclo puede cerrarse al recuperarse o convertirse en pedido.
- El evento ya puede consumirse desde templates y envio instantaneo.

## Riesgos abiertos

- `cart.date_upd` sigue siendo la señal principal de actividad, por lo que conviene validar si basta para todos los flujos comerciales.
- Guest carts y escenarios multishop complejos pueden requerir mas reglas de identidad.
- El valor comercial del email depende de la calidad de `recovery_url` y del contexto del carrito.

## Siguiente paso sugerido

La siguiente fase natural es `modules/mailsendvx/.agents/FASE_01D_WRAPPERS_Y_TEXTO_AUTOMATICO.md`, para consolidar la experiencia editorial del Back Office antes de profundizar en Fase 02.
