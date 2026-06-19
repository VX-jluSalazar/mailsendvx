# Fase 01: emails instantaneos

## Estado

Implementado en `modules/mailsendvx/mailsendvx.php`, `src/Service/InstantEmailHookService.php`, `classes/Service/MailSendVxMailer.php`, `classes/Repository/MailSendVxTemplateRepository.php`, `src/Controller/Admin/TemplatesController.php` y `views/templates/admin/templates.html.twig`.

La pantalla `Mail Send VELOX > Templates` ya funciona sobre Symfony y Twig, y permite crear plantillas por evento, editar asunto/HTML/texto, activar/desactivar, previsualizar con datos de prueba y enviar un email de prueba.

La pantalla `Mail Send VELOX > Configuracion` ya funciona sobre Symfony y Twig, y muestra ajustes generales. El dashboard Symfony concentra el resumen operativo y logs recientes.

El estado actual observado del codigo indica que la Fase 01 ya esta implementada en su base funcional y que los principales pendientes estan en validacion manual, consistencia de naming y documentacion.

## Estado real de la implementacion

### Funcionalidad ya presente

- Pedido creado con evento `order_created`.
- Cambio de estado de pedido con evento generico `order_status_changed`.
- Cambio de estado de pedido con evento especifico `order_status_changed_{state_key}`.
- Compatibilidad interna temporal con `order_status_updated` si existe una plantilla legacy activa.
- Emails instantaneos por `customer_registered`.
- Emails instantaneos por `newsletter_registered`.
- Editor simple de plantillas.
- Variables simples en asunto, HTML y texto.
- Vista previa con datos de prueba.
- Envio de prueba desde Back Office.
- Logs de resultado con `sent`, `failed` y `skipped`.

### Pendientes observados

- Ejecutar pruebas manuales reales en entorno con SMTP o proveedor configurado.
- Ajustar algunos datos de ejemplo para que reflejen exactamente el mapeo canonico actual de estados.
- Migrar repositorios y servicios legacy de `classes/` a `src/` en la fase arquitectonica siguiente.

## Objetivo

Enviar emails automaticamente cuando ocurren eventos inmediatos en PrestaShop, usando plantillas activas, variables simples y logs de resultado.

Esta fase convierte la captura de eventos de la Fase 0 en acciones reales de envio.

## Eventos cubiertos

### Eventos actuales

- Pedido creado.
- Cambio de estado de pedido.
- Registro de nuevo cliente.
- Suscripcion a newsletter.

### Eventos objetivo para pedido

- `order_status_changed` como evento generico.
- `order_status_changed_{state_key}` como evento especifico por estado destino.

Ejemplos sugeridos:

- `order_status_changed_payment_accepted`
- `order_status_changed_shipped`
- `order_status_changed_delivered`
- `order_status_changed_canceled`
- `order_status_changed_refunded`

## Alcance funcional

| Subfase | Objetivo | Complejidad |
| --- | --- | --- |
| 1.0 Email por pedido creado | Enviar un email cuando PrestaShop confirma la creacion del pedido. | Implementado |
| 1.1 Emails por cambio de estado | Enviar un email cuando un pedido cambie de estado. | Implementado |
| 1.1.1 Refactor de evento generico | Sustituir el uso exclusivo de `order_status_updated` por `order_status_changed`. | Implementado |
| 1.1.2 Eventos especificos por estado | Disparar un evento derivado segun el estado destino del pedido. | Implementado |
| 1.1.3 Compatibilidad temporal | Mantener compatibilidad con plantillas viejas basadas en `order_status_updated`. | Implementado |
| 1.2 Emails por registro de cliente | Enviar email de bienvenida al crear cuenta. | Implementado |
| 1.3 Emails por suscripcion newsletter | Enviar confirmacion o bienvenida al suscriptor. | Implementado |
| 1.4 Editor simple de plantilla | Permitir crear asunto, template, HTML/texto y evento asociado. | Implementado |
| 1.5 Variables simples | Soportar variables como `{customer_name}`, `{order_reference}`, `{shop_name}` y `{order_total}`. | Implementado |
| 1.6 Vista previa | Previsualizar el email con datos de prueba. | Implementado |
| 1.7 Envio de prueba | Enviar un email de prueba desde Back Office. | Implementado |
| 1.8 Logs por email enviado | Guardar resultado, destinatario, plantilla, error y fecha. | Implementado |

## Flujo tecnico

```txt
Hook de PrestaShop
|
Resolver pedido, cliente y estado disponible
|
Generar evento canonico
|
Si aplica, derivar evento especifico por estado
|
Resolver destinatario y variables
|
Buscar plantilla activa por evento, tienda e idioma
|
Renderizar asunto y variables
|
Enviar usando provider
|
Guardar log de resultado
```

## Variables iniciales recomendadas

| Evento | Variables |
| --- | --- |
| `order_created` | `customer_name`, `customer_firstname`, `customer_lastname`, `customer_email`, `order_id`, `order_reference`, `order_total`, `order_status`, `order_state_id`, `order_state_key`, `order_state_name`, `shop_name`, `shop_url` |
| `order_status_changed` | `customer_name`, `customer_email`, `order_reference`, `order_total`, `order_status`, `old_order_status`, `order_state_id`, `order_state_key`, `order_state_name`, `old_order_state_id`, `old_order_state_key`, `old_order_state_name`, `shop_name`, `shop_url` |
| `order_status_changed_{state_key}` | Las mismas variables del evento generico, orientadas al estado destino. |
| `customer_registered` | `customer_name`, `customer_email`, `shop_name`, `shop_url` |
| `newsletter_registered` | `customer_email`, `newsletter_action`, `shop_name`, `shop_url` |

## Estrategia tecnica recomendada para estados de pedido

### Taxonomia de eventos

- Hook origen de pedido creado: `actionValidateOrder`
- Evento canonico de pedido creado: `order_created`
- Hook origen: `actionOrderStatusPostUpdate`
- Evento generico: `order_status_changed`
- Evento especifico: `order_status_changed_{state_key}`
- Evento legado temporal: `order_status_updated`

### Regla para `state_key`

El identificador del estado no debe depender del nombre traducido mostrado al usuario.

Se recomienda:

1. Resolver el `id_order_state`.
2. Intentar mapear estados nativos conocidos a claves canonicas.
3. Para estados personalizados, generar una version slug estable.
4. Guardar tanto clave como nombre visible en las variables.

### Estados canonicos sugeridos

| Caso | Event key sugerido |
| --- | --- |
| Pago aceptado | `payment_accepted` |
| Preparacion en curso | `preparation_in_progress` |
| Enviado | `shipped` |
| Entregado | `delivered` |
| Cancelado | `canceled` |
| Reembolsado | `refunded` |
| Error de pago | `payment_error` |

### Orden de despacho sugerido

1. Registrar captura del evento canonico.
2. Disparar `order_created` cuando el pedido se valida.
3. Disparar `order_status_changed` cuando el pedido cambia de estado.
4. Disparar `order_status_changed_{state_key}` si existe estado destino resoluble.
5. Disparar `order_status_updated` solo como compatibilidad temporal interna si existe plantilla asociada o mientras dure la migracion.

## Patrones recomendados

- Observer para escuchar hooks.
- Command para encapsular cada envio.
- Strategy para proveedores de email.
- Template Method para normalizar renderizado y envio.
- Facade para ejecutar `sendEvent()` desde hooks sin duplicar logica.

## Dependencias

- Fase 0 instalada y operativa.
- Tablas base creadas.
- Provider `prestashop_mail` funcionando.
- Configuracion nativa de email de PrestaShop probada.
- Template fisico `mailsendvx_default` disponible en `modules/mailsendvx/mails/en` y `modules/mailsendvx/mails/es`.

## Como probar la funcionalidad

### Prueba 1: cambio de estado de pedido

1. Crear o seleccionar un pedido de prueba.
2. Ir a `Mail Send VELOX > Templates`.
3. Crear una plantilla activa para el evento `order_status_changed`.
4. Crear otra plantilla activa para un estado especifico, por ejemplo `order_status_changed_shipped`.
5. Usar un asunto con variables, por ejemplo: `Pedido {order_reference}: {order_status}`.
5. Cambiar el estado del pedido desde Back Office.
6. Confirmar que se ejecuta la plantilla generica.
7. Confirmar que se ejecuta la plantilla especifica si el estado coincide.
8. Revisar `PREFIX_mailsendvx_log` y confirmar estado `sent`.

### Prueba 1.1: pedido creado

1. Ir a `Mail Send VELOX > Templates`.
2. Crear una plantilla activa para `order_created`.
3. Usar un asunto como `Pedido {order_reference} creado`.
4. Crear un pedido nuevo desde Front Office o desde un checkout real.
5. Confirmar que se registra el evento `order_created`.
6. Confirmar que se envia el correo al cliente.
7. Revisar `PREFIX_mailsendvx_log` y confirmar estado `sent`.

### Prueba 2: registro de cliente

1. Ir a `Mail Send VELOX > Templates`.
2. Crear una plantilla activa para `customer_registered`.
3. Registrar un cliente nuevo desde Front Office.
4. Confirmar que se envia el email de bienvenida.
5. Revisar el log del evento y validar destinatario, estado y mensaje.

### Prueba 3: newsletter

1. Ir a `Mail Send VELOX > Templates`.
2. Crear una plantilla activa para `newsletter_registered`.
3. Registrar un email nuevo en el formulario de newsletter.
4. Confirmar que se genera el evento interno.
5. Confirmar que se envia el email si hay destinatario valido.

### Prueba 4: preview y envio de prueba

1. Crear o editar una plantilla.
2. Click en `Preview`.
3. Confirmar que el asunto, HTML y texto reemplazan variables con datos de prueba.
4. En la tabla de plantillas, escribir un email valido en `Test email`.
5. Click en `Send test`.
6. Confirmar que llega el correo y que el log queda como `sent`.

### Prueba 5: plantilla inexistente

1. Desactivar la plantilla de un evento.
2. Ejecutar el evento correspondiente.
3. Confirmar que no se envia email.
4. Confirmar que el log queda como `skipped` con mensaje similar a `No active template found.`

### Prueba 6: compatibilidad interna con evento legado

1. Mantener una plantilla activa solo para `order_status_updated`.
2. Cambiar el estado de un pedido.
3. Confirmar que el sistema sigue pudiendo resolver el envio legado mientras se completa la migracion.
4. Confirmar que la documentacion lo marque como compatibilidad temporal y que la UI moderna no lo exponga como opcion nueva.

## Consultas utiles de validacion

```sql
SELECT id_mailsendvx_template, event_name, subject, active, id_shop, id_lang
FROM PREFIX_mailsendvx_template
ORDER BY id_mailsendvx_template DESC;

SELECT event_name, recipient, status, id_template, message, date_add
FROM PREFIX_mailsendvx_log
ORDER BY id_mailsendvx_log DESC
LIMIT 30;
```

## Criterios de aceptacion

- Cada evento configurado puede enviar un email real.
- `order_created` puede disparar una plantilla inmediata al validar el pedido.
- Un cambio de estado de pedido puede activar un evento generico y otro especifico.
- Las plantillas pueden distinguir estados concretos sin mezclar toda la logica en `order_status_updated`.
- Si no existe plantilla activa, el sistema registra `skipped`.
- Las variables simples se reemplazan en el asunto y en las variables enviadas al template.
- La pantalla admin permite crear, editar, previsualizar, eliminar y enviar prueba de plantillas.
- El resultado del envio queda registrado en logs.
- Los errores del provider quedan registrados sin romper el hook de PrestaShop.

## Nota de cierre de estado

La Fase 01 debe considerarse implementada a nivel de codigo base.

Los siguientes pasos recomendados ya no son construir la funcionalidad principal, sino:

- validar el comportamiento en entorno real,
- ajustar documentacion y ejemplos,
- completar la migracion intermedia a Twig en `modules/mailsendvx/.agents/FASE_01B_MOTOR_TWIG.md`,
- preparar el puente hacia Fase 02 sobre `order_created`, `order_status_changed` y `order_status_changed_{state_key}` usando ya el nuevo motor.

## Riesgos

- Los templates fisicos de PrestaShop deben existir en la ruta esperada por `Mail::Send()`.
- La informacion disponible en cada hook puede variar segun el contexto.
- Si el `state_key` depende de texto traducido, se rompera la estabilidad entre idiomas o tiendas.
- Enviar correos desde hooks puede afectar la experiencia si el provider tarda demasiado; para volumen alto conviene mover el envio a cola en Fase 2.
