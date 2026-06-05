# Fase 01: emails instantaneos

## Objetivo

Enviar emails automaticamente cuando ocurren eventos inmediatos en PrestaShop, usando plantillas activas, variables simples y logs de resultado.

Esta fase convierte la captura de eventos de la Fase 0 en acciones reales de envio.

## Eventos cubiertos

- Cambio de estado de pedido.
- Registro de nuevo cliente.
- Suscripcion a newsletter.

## Alcance funcional

| Subfase | Objetivo | Complejidad |
| --- | --- | --- |
| 1.1 Emails por cambio de estado | Enviar un email cuando un pedido cambie de estado. | Media |
| 1.2 Emails por registro de cliente | Enviar email de bienvenida al crear cuenta. | Baja-media |
| 1.3 Emails por suscripcion newsletter | Enviar confirmacion o bienvenida al suscriptor. | Media |
| 1.4 Editor simple de plantilla | Permitir crear asunto, template, HTML/texto y evento asociado. | Media |
| 1.5 Variables simples | Soportar variables como `{customer_name}`, `{order_reference}`, `{shop_name}` y `{order_total}`. | Media |
| 1.6 Vista previa | Previsualizar el email con datos de prueba. | Media |
| 1.7 Envio de prueba | Enviar un email de prueba desde Back Office. | Baja-media |
| 1.8 Logs por email enviado | Guardar resultado, destinatario, plantilla, error y fecha. | Baja-media |

## Flujo tecnico

```txt
Hook de PrestaShop
|
Evento interno
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
| `order_status_updated` | `customer_name`, `customer_email`, `order_reference`, `order_total`, `order_status`, `shop_name`, `shop_url` |
| `customer_registered` | `customer_name`, `customer_email`, `shop_name`, `shop_url` |
| `newsletter_registered` | `customer_email`, `shop_name`, `shop_url` |

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

## Como probar la funcionalidad

### Prueba 1: cambio de estado de pedido

1. Crear o seleccionar un pedido de prueba.
2. Crear una plantilla activa para el evento `order_status_updated`.
3. Usar un asunto con variables, por ejemplo: `Pedido {order_reference}: {order_status}`.
4. Cambiar el estado del pedido desde Back Office.
5. Confirmar que el cliente recibe el email.
6. Revisar `PREFIX_mailsendvx_log` y confirmar estado `sent`.

### Prueba 2: registro de cliente

1. Crear una plantilla activa para `customer_registered`.
2. Registrar un cliente nuevo desde Front Office.
3. Confirmar que se envia el email de bienvenida.
4. Revisar el log del evento y validar destinatario, estado y mensaje.

### Prueba 3: newsletter

1. Crear una plantilla activa para `newsletter_registered`.
2. Registrar un email nuevo en el formulario de newsletter.
3. Confirmar que se genera el evento interno.
4. Confirmar que se envia el email si hay destinatario valido.

### Prueba 4: plantilla inexistente

1. Desactivar la plantilla de un evento.
2. Ejecutar el evento correspondiente.
3. Confirmar que no se envia email.
4. Confirmar que el log queda como `skipped` con mensaje similar a `No active template found.`

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
- Si no existe plantilla activa, el sistema registra `skipped`.
- Las variables simples se reemplazan en el asunto y en las variables enviadas al template.
- El resultado del envio queda registrado en logs.
- Los errores del provider quedan registrados sin romper el hook de PrestaShop.

## Riesgos

- Los templates fisicos de PrestaShop deben existir en la ruta esperada por `Mail::Send()`.
- La informacion disponible en cada hook puede variar segun el contexto.
- Enviar correos desde hooks puede afectar la experiencia si el provider tarda demasiado; para volumen alto conviene mover el envio a cola en Fase 2.
