# Fase 02F: UI operativa y casos comerciales

## Objetivo

Cerrar la Fase 02 con una UI usable desde Back Office y con los primeros flujos comerciales reales.

## Alcance de UI

- crear flows,
- definir `trigger_event`,
- definir `context_type`,
- agregar y ordenar steps,
- asociar templates compatibles,
- configurar delays,
- ver estado de la queue,
- cancelar jobs desde el panel cuando aplique.

## Validaciones de UI

- no permitir asociar templates incompatibles con el `context_type` del flow,
- permitir templates con `event_name = NULL`,
- diferenciar templates instantaneos de templates reutilizables,
- mostrar estados de cola y errores operativos de forma clara.

## Casos comerciales iniciales

### Carrito abandonado

- Email 1 con delay configurable.
- Email 2 con delay configurable.
- Email 3 con delay configurable.
- cancelacion automatica si el carrito se convierte en pedido.

### Postcompra

- confirmacion inmediata al `order_created`,
- agradecimiento despues de `order_status_changed_payment_accepted`,
- seguimiento en `order_status_changed_shipped`,
- review en `order_status_changed_delivered`.

### Suscriptores

- bienvenida inmediata o diferida,
- email educativo,
- incentivo opcional.

## Monitoreo minimo esperado

- jobs pendientes,
- jobs programados,
- jobs procesados,
- fallidos,
- cancelados,
- logs recientes asociados a flow y template.

## Criterios de aceptacion

- La UI permite crear y editar flows.
- La UI permite asociar varios templates a traves de steps.
- La UI valida compatibilidad por `context_type`.
- Existen flujos iniciales usables para carrito, postcompra y suscriptores.
