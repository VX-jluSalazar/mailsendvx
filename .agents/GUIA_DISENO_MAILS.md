# Guía de diseño y maquetación de mails para Mail Send VX

## Objetivo del documento

Este documento está pensado para una persona de diseño con experiencia en HTML que va a transformar diseños de Figma a plantillas de email dentro del módulo `mailsendvx`.

La idea es dejar claro:

- cómo se divide un correo entre `wrapper` y `template`,
- qué eventos existen,
- qué variables se pueden usar,
- y cómo maquetar en HTML de forma segura para email.

## Fixtures de referencia

Si el diseñador quiere ver ejemplos completos del payload disponible por evento, puede revisar estos fixtures:

- `modules/mailsendvx/.agents/fixtures/order.json`
- `modules/mailsendvx/.agents/fixtures/cart.json`
- `modules/mailsendvx/.agents/fixtures/suscriber.json`

Esos archivos muestran ejemplos actuales del tipo de variables y arreglos que el módulo expone a Twig.

## Arquitectura actual del contexto

El payload final no se arma en un solo bloque rígido. El módulo compone el contexto con `TemplateContextPayloadBuilder` y piezas reutilizables:

- `EventContextSegmentBuilder`
- `ShopContextSegmentBuilder`
- `CustomerContextSegmentBuilder`
- `ProductsContextBuilder`
- `CartContextSegmentBuilder`
- `OrderContextSegmentBuilder`
- `RelatedProductsContextProvider`
- `ReviewsContextProvider`

La idea práctica es simple:

- `event`, `shop` y `customer` son segmentos base reutilizables,
- `order` y `cart` agregan la información principal del evento,
- `products`, `related_products` y `reviews` siguen contratos reutilizables entre payloads,
- los fixtures reflejan esa misma estructura y deben tomarse como referencia de diseño.

## Modelo mental correcto

En este módulo, un correo se compone de dos capas:

1. `Wrapper`
2. `Template`

La forma más simple de entenderlo es esta:

- `Wrapper` = estructura externa compartida
- `Template` = contenido específico del mensaje

## Diferencia entre wrapper y template

### Wrapper

El `wrapper` es el marco general del correo.

Aquí normalmente va:

- encabezado,
- logo,
- color de marca,
- fondo general,
- ancho del email,
- pie de página,
- datos de contacto,
- enlaces del footer.

Tu idea es correcta:

- el `header` y el `footer` deben vivir en el `wrapper`,
- y el contenido del mensaje debe vivir en el `template`.

Ejemplo conceptual:

```html
<!DOCTYPE html>
<html>
<body>
  <table>
    <tr>
      <td>
        <!-- Header -->
        <img src="logo.png" alt="Marca">
      </td>
    </tr>
    <tr>
      <td>
        {mailsendvx_html_content}
      </td>
    </tr>
    <tr>
      <td>
        <!-- Footer -->
        <p>Mi tienda</p>
      </td>
    </tr>
  </table>
</body>
</html>
```

Puntos obligatorios del wrapper:

- en HTML debe contener `{mailsendvx_html_content}`,
- en texto debe contener `{mailsendvx_text_content}`.

### Template

El `template` es el contenido interno del mensaje.

Aquí normalmente va:

- saludo,
- título del mensaje,
- texto principal,
- bloque de productos,
- bloque de totales,
- botón CTA,
- mensaje legal o contextual propio del evento.

Ejemplo conceptual:

```twig
<h1>Hola {{ customer.firstname }}</h1>
<p>Tu pedido <strong>{{ order.reference }}</strong> fue actualizado.</p>
<p>Estado actual: {{ order.status }}</p>
```

## Recomendación de trabajo Figma -> HTML

Cuando el diseñador entregue el layout:

1. Identificar qué parte se repite en todos los correos.
2. Mover esa parte al `wrapper`.
3. Identificar qué parte cambia por evento.
4. Mover esa parte al `template`.

### Qué suele ir al wrapper

- logo,
- franja superior,
- color de fondo,
- contenedor central,
- espaciados externos,
- footer global,
- redes o links corporativos.

### Qué suele ir al template

- asunto visual del mensaje,
- título,
- copy,
- productos,
- estados,
- CTA,
- bloques dinámicos.

## Cómo pensar el diseño

### Buen criterio

Si el bloque debe reutilizarse en:

- pedido creado,
- cambio de estado,
- newsletter,
- carrito abandonado,

entonces probablemente pertenece al `wrapper`.

Si el bloque cambia según el mensaje:

- “Tu pedido fue enviado”
- “Confirma tu suscripción”
- “Recupera tu carrito”

entonces pertenece al `template`.

## Eventos disponibles en el módulo

## Eventos base

Actualmente el módulo soporta estos eventos principales:

- `order_created`
- `order_status_changed`
- `customer_registered`
- `newsletter_registered`
- `cart_abandoned`

## Eventos de cambio de estado por estado específico

Además del evento genérico `order_status_changed`, el módulo genera eventos dinámicos por cada estado de pedido de PrestaShop.

Su forma es:

```txt
order_status_changed_{state_key}
```

Ejemplos comunes:

- `order_status_changed_payment_accepted`
- `order_status_changed_preparation_in_progress`
- `order_status_changed_shipped`
- `order_status_changed_delivered`
- `order_status_changed_canceled`
- `order_status_changed_refunded`
- `order_status_changed_payment_error`
- `order_status_changed_out_of_stock`
- `order_status_changed_awaiting_bank_wire_payment`
- `order_status_changed_awaiting_cheque_payment`

Importante:

- la lista exacta depende de los estados de pedido activos en PrestaShop,
- por eso el diseñador debe asumir que existe un evento genérico y varios eventos específicos por estado.

## Qué significa eso para diseño

### Si un diseño sirve para todos los estados

Usar:

```txt
order_status_changed
```

### Si un diseño cambia según el estado

Usar una plantilla distinta para:

- enviado,
- entregado,
- cancelado,
- pago aceptado,
- reembolsado,
- etc.

## Variables disponibles

En esta guía se documentan solo variables en Twig.

Formato correcto:

```twig
{{ variable }}
```

Para listas:

```twig
{% for item in order.products %}
  {{ item.name }}
{% endfor %}
```

Para condiciones:

```twig
{% if order.status %}
  {{ order.status }}
{% endif %}
```

## Variables de Pedido

Origen técnico:

- `TemplateContextPayloadBuilder`
- `EventContextSegmentBuilder`
- `ShopContextSegmentBuilder`
- `CustomerContextSegmentBuilder`
- `OrderContextSegmentBuilder`
- `ProductsContextBuilder`
- `RelatedProductsContextProvider`
- `ReviewsContextProvider`

Aplica a:

- `order_created`
- `order_status_changed`
- todos los `order_status_changed_*`

### Estructura principal del payload

```twig
{{ event.name }}
{{ shop.id }}
{{ shop.id_lang }}
{{ shop.name }}
{{ shop.url }}
{{ customer.id }}
{{ customer.name }}
{{ customer.firstname }}
{{ customer.lastname }}
{{ customer.email }}
{{ order.id }}
{{ order.reference }}
{{ order.total }}
{{ order.date }}
{{ order.formated_date }}
{{ order.status }}
{{ order.old_status }}
{{ order.payment_method }}
{{ order.shipping_method }}
```

### Estado del pedido

```twig
{{ order.state.id }}
{{ order.state.key }}
{{ order.state.name }}
{{ order.old_state.id }}
{{ order.old_state.key }}
{{ order.old_state.name }}
```

### Totales del pedido

```twig
{{ order.totals.paid }}
{{ order.totals.products }}
{{ order.totals.shipping }}
{{ order.totals.discounts }}
{{ order.totals.tax }}
```

Nota:

- dentro de `order.*` los montos están pensados como valores numéricos del payload,
- si necesitas mostrarlos con formato visual de moneda, conviene definir ese formato explícitamente en Twig o preparar el dato desde backend.

### Dirección de facturación

```twig
{{ order.billing_address.firstname }}
{{ order.billing_address.lastname }}
{{ order.billing_address.full_name }}
{{ order.billing_address.company }}
{{ order.billing_address.address1 }}
{{ order.billing_address.address2 }}
{{ order.billing_address.city }}
{{ order.billing_address.postcode }}
{{ order.billing_address.country }}
{{ order.billing_address.state }}
{{ order.billing_address.phone }}
{{ order.billing_address.phone_mobile }}
{{ order.billing_address.formatted }}
```

### Dirección de envío

```twig
{{ order.shipping_address.firstname }}
{{ order.shipping_address.lastname }}
{{ order.shipping_address.full_name }}
{{ order.shipping_address.company }}
{{ order.shipping_address.address1 }}
{{ order.shipping_address.address2 }}
{{ order.shipping_address.city }}
{{ order.shipping_address.postcode }}
{{ order.shipping_address.country }}
{{ order.shipping_address.state }}
{{ order.shipping_address.phone }}
{{ order.shipping_address.phone_mobile }}
{{ order.shipping_address.formatted }}
```

### Envío

```twig
{{ order.shipping.carrier_name }}
{{ order.shipping.cost }}
{{ order.shipping.tracking_url }}
```

### Productos del pedido

```twig
{% for item in order.products %}
  {{ item.id }}
  {{ item.attribute_id }}
  {{ item.name }}
  {{ item.reference }}
  {{ item.quantity }}
  {{ item.unit_price }}
  {{ item.total_price }}
  {{ item.unit_price_tax_excl }}
  {{ item.unit_price_tax_incl }}
  {{ item.total_price_tax_excl }}
  {{ item.total_price_tax_incl }}
  {{ item.url }}
  {{ item.image_url }}
{% endfor %}
```

### Atributos por producto

```twig
{% for item in order.products %}
  {% for attribute in item.attributes %}
    {{ attribute.label }}: {{ attribute.value }}
  {% endfor %}
{% endfor %}
```

### Productos relacionados

```twig
{% for item in related_products %}
  {{ item.id }}
  {{ item.name }}
  {{ item.price }}
  {{ item.url }}
  {{ item.image_url }}
{% endfor %}
```

### Reviews

```twig
{% for item in reviews %}
  {{ item.author }}
  {{ item.firstname }}
  {{ item.lastname }}
  {{ item.rating }}
  {{ item.title }}
  {{ item.content }}
{% endfor %}
```

Nota:

- en pedidos, el formato recomendado para plantillas nuevas es usar siempre `event.*`, `shop.*`, `customer.*` y `order.*`,
- ese mismo criterio conviene mantenerlo en todos los eventos para que las plantillas sean consistentes y fáciles de mantener.

## Variables de Cliente

Origen técnico:

- `CustomerTemplateContextBuilder`

Aplica a:

- `customer_registered`

### Variables principales

```twig
{{ event.name }}
{{ shop.id }}
{{ shop.id_lang }}
{{ shop.name }}
{{ shop.url }}
{{ customer.id }}
{{ customer.name }}
{{ customer.firstname }}
{{ customer.lastname }}
{{ customer.email }}
```

## Variables de Newsletter

Origen técnico:

- `NewsletterTemplateContextBuilder`

Aplica a:

- `newsletter_registered`

### Variables principales

```twig
{{ event.name }}
{{ event.newsletter_action }}
{{ shop.id }}
{{ shop.id_lang }}
{{ shop.name }}
{{ shop.url }}
{{ customer.id }}
{{ customer.name }}
{{ customer.firstname }}
{{ customer.lastname }}
{{ customer.email }}
```

Nota:

- `newsletter_registered` usa un payload corto y limpio,
- no expone productos, categorías, carrito ni reviews.

## Variables de Carrito

Origen técnico:

- `TemplateContextPayloadBuilder`
- `EventContextSegmentBuilder`
- `ShopContextSegmentBuilder`
- `CustomerContextSegmentBuilder`
- `CartContextSegmentBuilder`
- `ProductsContextBuilder`
- `RelatedProductsContextProvider`
- `ReviewsContextProvider`

Aplica a:

- `cart_abandoned`

### Variables principales

```twig
{{ event.name }}
{{ shop.id }}
{{ shop.id_lang }}
{{ shop.name }}
{{ shop.url }}
{{ shop.contact_url }}
{{ customer.id }}
{{ customer.name }}
{{ customer.firstname }}
{{ customer.lastname }}
{{ customer.email }}
{{ customer.is_customer }}
{{ cart.id }}
{{ cart.url }}
{{ cart.recovery_url }}
{{ cart.abandoned_at }}
{{ cart.updated_at }}
{{ cart.abandoned_minutes }}
{{ cart.products_count }}
{{ cart.total }}
```

### Productos del carrito

```twig
{% for item in cart.items %}
  {{ item.id }}
  {{ item.attribute_id }}
  {{ item.name }}
  {{ item.reference }}
  {{ item.quantity }}
  {{ item.total_price }}
  {{ item.unit_price }}
  {{ item.unit_price_tax_excl }}
  {{ item.unit_price_tax_incl }}
  {{ item.total_price_tax_excl }}
  {{ item.total_price_tax_incl }}
  {{ item.url }}
  {{ item.image_url }}
{% endfor %}
```

### Atributos por producto del carrito

```twig
{% for item in cart.items %}
  {% for attribute in item.attributes %}
    {{ attribute.label }}: {{ attribute.value }}
  {% endfor %}
{% endfor %}
```

### Objeto `cart`

```twig
{{ cart.id }}
{{ cart.abandoned_minutes }}
{{ cart.abandoned_at }}
{{ cart.updated_at }}
{{ cart.url }}
{{ cart.recovery_url }}
{{ cart.products_count }}
{{ cart.total }}
{{ cart.totals.products }}
{{ cart.totals.shipping }}
{{ cart.totals.discounts }}
{{ cart.totals.total }}
```

```twig
{% for item in cart.items %}
  {{ item.name }}
  {{ item.quantity }}
{% endfor %}
```

### Objeto `customer`

```twig
{{ customer.id }}
{{ customer.name }}
{{ customer.firstname }}
{{ customer.lastname }}
{{ customer.email }}
{{ customer.is_customer }}
```

### Objeto `shop`

```twig
{{ shop.id }}
{{ shop.id_lang }}
{{ shop.name }}
{{ shop.url }}
{{ shop.contact_url }}
```

### Productos relacionados

```twig
{% for item in related_products %}
  {{ item.id }}
  {{ item.name }}
  {{ item.price }}
  {{ item.url }}
  {{ item.image_url }}
{% endfor %}
```

### Reviews

```twig
{% for item in reviews %}
  {{ item.author }}
  {{ item.firstname }}
  {{ item.lastname }}
  {{ item.rating }}
  {{ item.title }}
  {{ item.content }}
{% endfor %}
```

Nota:

- `cart.items` mantiene ese nombre por compatibilidad,
- cada item ahora sigue la misma estructura normalizada de `order.products`.

## Recomendación de uso para plantillas nuevas

Las plantillas nuevas deben usar siempre variables agrupadas.

Ejemplo recomendado:

```twig
{{ order.reference }}
{{ order.total }}
{{ order.payment_method }}
{{ order.shipping_method }}
{{ customer.firstname }}
{{ shop.name }}
```

## Reglas importantes para maquetación

## 1. Pensar en email, no en web

No diseñar como una landing page.

Un email HTML tiene restricciones:

- menos soporte CSS,
- menos soporte de layouts modernos,
- comportamiento distinto en Outlook.

## 2. Preferir tablas para estructura

Para wrappers y templates de email, la base debe ser:

- `table`
- `tr`
- `td`

No confiar demasiado en:

- `flex`
- `grid`
- `position`

## 3. Usar estilos inline cuando sea necesario

Lo más seguro para email sigue siendo:

```html
<td style="padding:24px;font-size:15px;line-height:1.5;">
```

## 4. Mantener ancho contenido

Recomendación:

- ancho máximo entre `600px` y `640px`

## 5. No meter lógica de negocio compleja

Twig puede renderizar:

- variables,
- `if`,
- `for`

Pero no conviene meter lógica de negocio pesada en el HTML.

Bien:

```twig
{% if cart.items is not empty %}
```

Mal:

- lógica comercial compleja,
- cálculos grandes,
- decisiones que deberían existir ya en backend.

## 6. CTA claros

Si el diseño tiene botón:

- en pedido: ver pedido, seguir envío, visitar tienda
- en carrito abandonado: recuperar carrito
- en newsletter: confirmar o visitar tienda

## Ejemplos rápidos

## Ejemplo 1: header/footer en wrapper + contenido en template

### Wrapper

```html
<table role="presentation" width="100%" cellpadding="0" cellspacing="0">
  <tr>
    <td style="padding:24px;background:#0f172a;color:#ffffff;">
      <strong>Mi Marca</strong>
    </td>
  </tr>
  <tr>
    <td style="padding:24px;background:#ffffff;">
      {mailsendvx_html_content}
    </td>
  </tr>
  <tr>
    <td style="padding:20px;background:#f3f4f6;color:#475569;font-size:12px;">
      Mi footer global
    </td>
  </tr>
</table>
```

### Template

```twig
<h1 style="margin:0 0 16px;">Hola {{ customer.firstname }}</h1>
<p style="margin:0 0 12px;">Tu pedido <strong>{{ order.reference }}</strong> fue enviado.</p>
<p style="margin:0;">Estado actual: {{ order.status }}</p>
```

## Ejemplo 2: carrito abandonado

```twig
<h1>Hola {{ customer.firstname ?: customer.name }}</h1>
<p>Guardamos tu carrito en {{ shop.name }}.</p>

{% if cart.items is not empty %}
  <ul>
    {% for item in cart.items %}
      <li>{{ item.name }} x{{ item.quantity }}</li>
    {% endfor %}
  </ul>
{% endif %}

<p>
  <a href="{{ cart.recovery_url }}">Retomar mi compra</a>
</p>
```

## Flujo recomendado de trabajo

1. Diseñar en Figma una versión base.
2. Separar layout global vs contenido variable.
3. Pasar layout global al `wrapper`.
4. Pasar contenido variable al `template`.
5. Cambiar textos reales por variables Twig.
6. Probar preview.
7. Ajustar para mobile y clientes de correo.

## Entregable ideal del diseñador

El entregable más útil para desarrollo es:

1. Figma final aprobado.
2. Versión HTML del wrapper.
3. Versión HTML del template.
4. Lista de variables que espera usar.
5. Indicaciones de comportamiento responsive.

## Resumen ejecutivo

- `wrapper` = header + footer + marco global
- `template` = contenido del mensaje
- usar solo Twig para variables
- usar tablas e inline styles para máxima compatibilidad
- eventos de pedido comparten casi todas sus variables
- newsletter y carrito abandonado usan contextos distintos

Si el diseñador entiende esa separación, la transformación de Figma a email HTML en este módulo será mucho más rápida y consistente.
