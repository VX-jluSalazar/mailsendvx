# Fase 02A: modelo base de flows

## Estado

Implementada.

### Alcance ya cubierto

- tabla `mailsendvx_flow` ampliada con `trigger_event`, `context_type`, `description`, `priority`, `version`, `conditions_json` y `steps_json`,
- validación backend de compatibilidad entre flow, steps y templates,
- normalización de `steps_json` desde repositorio.

### Pendientes relacionados

- falta UI para crear y editar flows desde Back Office,
- falta suite automática real para validar esta fase por CLI.

## Objetivo

Definir el contrato estructural de los flows para que un flujo represente:

- un `trigger_event`,
- un `context_type`,
- una secuencia de `steps`,
- condiciones de entrada,
- versionado basico.

Ya no debe modelarse como "un evento = un template".

## Reglas base

- `trigger_event` define cuando inicia el flow.
- `context_type` define con que familia de payload trabaja el flow.
- cada step referencia un `template_id`.
- un flow puede tener dos o mas templates a traves de sus steps.
- todos los templates de ese flow deben ser compatibles con su `context_type`.

## Tipos de contexto iniciales recomendados

- `order`
- `cart`
- `customer`
- `newsletter`

## Campos recomendados para `mailsendvx_flow`

- `id_mailsendvx_flow`
- `id_shop`
- `name`
- `trigger_event`
- `context_type`
- `description`
- `active`
- `priority`
- `conditions_json`
- `steps_json`
- `version`
- `date_add`
- `date_upd`

## Contrato recomendado de `steps_json`

Cada step debe contemplar:

- `id`
- `type`
- `template_id`
- `delay`
- `conditions`
- `cancel_rules`
- `active`

Ejemplo:

```json
[
  {
    "id": "welcome_now",
    "type": "email",
    "template_id": 12,
    "delay": {
      "value": 0,
      "unit": "hour",
      "mode": "after_trigger"
    },
    "conditions": [],
    "cancel_rules": [],
    "active": true
  },
  {
    "id": "review_day_10",
    "type": "email",
    "template_id": 24,
    "delay": {
      "value": 10,
      "unit": "day",
      "mode": "after_previous_step"
    },
    "conditions": [],
    "cancel_rules": [],
    "active": true
  }
]
```

## Compatibilidad entre flow y template

La validacion recomendada es:

- un flow con `context_type = order` solo debe permitir templates `order`,
- un flow con `context_type = cart` solo debe permitir templates `cart`,
- la UI debe bloquear combinaciones invalidas,
- el backend tambien debe validarlas para no depender solo del formulario.

## Ejemplos de triggers compatibles

Para `context_type = order`:

- `order_created`
- `order_status_changed`
- `order_status_changed_shipped`
- `order_status_changed_delivered`

Para `context_type = cart`:

- `cart_abandoned`

Para `context_type = customer`:

- `customer_registered`

Para `context_type = newsletter`:

- `newsletter_registered`

## Criterios de aceptacion

- El flow puede declarar `trigger_event` y `context_type`.
- El flow puede persistir una lista de steps.
- Un mismo flow puede usar multiples templates.
- La compatibilidad flow/template depende de `context_type`, no de `event_name`.
