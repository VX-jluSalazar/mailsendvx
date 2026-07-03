# Fase 02B: templates reutilizables

## Objetivo

Desacoplar los templates del evento obligatorio para que puedan reutilizarse dentro de flows.

## Decision aplicada

- `event_name` define disparo automatico directo.
- `context_type` define compatibilidad de payload.

Por lo tanto:

- un template puede tener `event_name` informado,
- un template puede tener `event_name = NULL`,
- el `context_type` debe seguir siendo conocido.

## Regla de uso

Templates instantaneos:

- se usan para Fase 01,
- pueden tener `event_name`,
- el sistema los busca por evento activo.

Templates reutilizables:

- se usan para flows,
- pueden no tener `event_name`,
- el flow los resuelve por `template_id`,
- la compatibilidad se valida por `context_type`.

## Campos recomendados para `mailsendvx_template`

- `id_mailsendvx_template`
- `event_name` nullable
- `context_type`
- `name`
- `subject`
- `html_content`
- `text_content`
- `mail_template`
- `provider`
- `active`
- `date_add`
- `date_upd`

## Reglas funcionales

- `event_name` deja de ser obligatorio.
- `context_type` debe ser obligatorio.
- los flows no deben buscar templates por `event_name`.
- los envios instantaneos pueden seguir usando `event_name`.
- un mismo template puede reutilizarse en varios flows compatibles.

## Ejemplos

Template reutilizable:

```txt
name: Gracias por tu compra
event_name: NULL
context_type: order
```

Template instantaneo:

```txt
name: Tu pedido fue enviado
event_name: order_status_changed_shipped
context_type: order
```

Template para carrito:

```txt
name: Recupera tu carrito
event_name: NULL
context_type: cart
```

## Riesgos a cubrir

- Si `context_type` queda vacio, el template no puede validarse correctamente.
- Si se sigue usando `event_name` como filtro principal en flows, se rompe la reutilizacion.
- Si no se diferencia bien el uso instantaneo del reutilizable, la UI puede resultar confusa.

## Criterios de aceptacion

- Se puede guardar un template sin `event_name`.
- Se puede guardar un template con `context_type`.
- El template sin `event_name` puede ser asociado a un step de flow.
- El render de flow usa `template_id` y `context_type`, no `event_name`.
