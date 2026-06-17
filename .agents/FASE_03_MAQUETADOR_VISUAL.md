# Fase 03: maquetador visual de emails

## Objetivo

Construir un editor visual para crear emails profesionales sin editar HTML manualmente, manteniendo compatibilidad con clientes de correo y con el motor de variables/flujos de Mail Send VX.

Esta fase debe iniciarse cuando el motor de envio, cola, logs y plantillas ya sea estable.

## Alcance funcional

| Subfase | Objetivo | Complejidad |
| --- | --- | --- |
| 3.1 Editor HTML avanzado | Mejorar el editor simple con herramientas basicas de contenido. | Media |
| 3.2 Sistema de bloques | Crear bloques de texto, imagen, boton, separador, footer, producto y resumen de pedido. | Alta |
| 3.3 JSON estructurado | Guardar el diseno como JSON ademas del HTML final. | Alta |
| 3.4 Renderizador de bloques | Convertir JSON del builder en HTML compatible con email. | Alta |
| 3.5 Variables drag and drop | Insertar variables visualmente desde un panel. | Alta |
| 3.6 Bloques dinamicos | Renderizar productos de pedido, carrito abandonado y recomendados. | Alta |
| 3.7 Integracion externa | Evaluar GrapesJS, Unlayer o builder propio. | Alta |
| 3.8 Responsive email | Asegurar compatibilidad movil y clientes de correo. | Alta |
| 3.9 Plantillas predisenadas | Crear bases para carrito abandonado, postcompra, newsletter y estados de pedido. | Media-alta |
| 3.10 Preview con datos reales | Previsualizar con pedido, carrito o cliente real. | Alta |

## Modelo de diseno recomendado

Guardar cada plantilla en dos formatos:

- `json_design`: estructura editable del builder.
- `html_content`: HTML final generado para envio.
- `text_content`: version texto plano.
- `mail_template`: wrapper compatible con `Mail::Send()` o provider futuro.

## Bloques iniciales

| Bloque | Uso |
| --- | --- |
| Texto | Titulos, parrafos y mensajes cortos. |
| Imagen | Banners, logos y productos. |
| Boton | CTA con URL dinamica o fija. |
| Separador | Division visual entre secciones. |
| Footer | Datos de tienda, contacto y baja. |
| Producto | Producto individual con imagen, nombre, precio y enlace. |
| Resumen de pedido | Total, envio, descuentos y referencia. |
| Lista dinamica | Loop de productos de carrito o pedido. |

## Variables dinamicas

El editor debe permitir variables simples:

```txt
{customer_name}
{customer_email}
{order_reference}
{order_total}
{shop_name}
{shop_url}
```

Y estructuras dinamicas para listas:

```txt
{% for item in order.items %}
  {{ item.name }}
  {{ item.quantity }}
  {{ item.price }}
{% endfor %}
```

## Patrones recomendados

- Builder para construir el email.
- Composite para componer bloques anidados.
- Decorator para footer, tracking, unsubscribe y wrappers.
- Strategy para cambiar renderers.
- Adapter si se integra GrapesJS, Unlayer u otra herramienta externa.
- Factory Method para crear bloques por tipo.

## Dependencias

- Fase 0 completa.
- Fase 1 funcional para plantillas, variables, envio y taxonomia de eventos por estado.
- Fase 2 recomendada para probar bloques dinamicos con cola y flujos reales.
- Definicion final de variables disponibles por evento, incluyendo variables de estado de pedido.

## Impacto de eventos por estado en el maquetador

Las plantillas visuales de pedido no deben diseñarse solo para `order_status_updated`.

El editor y las plantillas predisenadas deben contemplar:

- una plantilla generica para `order_status_changed`;
- plantillas especificas para `order_status_payment_accepted`, `order_status_shipped`, `order_status_delivered`, `order_status_canceled` y estados equivalentes;
- previews que usen el `state_key` y el nombre visible del estado.

## Como probar la funcionalidad

### Prueba 1: crear plantilla visual

1. Crear una plantilla nueva desde el editor.
2. Agregar bloques de texto, imagen, boton y footer.
3. Guardar la plantilla.
4. Confirmar que se guarda `json_design`.
5. Confirmar que se genera `html_content`.

### Prueba 2: render responsive

1. Abrir preview desktop.
2. Abrir preview movil.
3. Confirmar que imagenes, botones y textos no se rompen.
4. Enviar email de prueba a al menos dos clientes de correo.
5. Validar que el HTML se vea correctamente.

### Prueba 3: variables visuales

1. Insertar variables desde el panel del editor.
2. Previsualizar con datos de prueba.
3. Confirmar que las variables se reemplazan.
4. Enviar email de prueba y validar asunto/contenido.

### Prueba 4: bloques dinamicos

1. Crear una plantilla para `order_status_changed`, `order_status_shipped` o un flujo postcompra.
2. Agregar un bloque de productos del pedido.
3. Previsualizar usando un pedido real.
4. Confirmar que cada producto muestra nombre, cantidad, precio, imagen y URL si estan disponibles.

### Prueba 5: compatibilidad con envio real

1. Asociar la plantilla visual a un evento real.
2. Disparar el evento desde PrestaShop.
3. Confirmar que se envia el HTML generado.
4. Confirmar log `sent`.
5. Confirmar que la version texto plano existe o se genera.

## Criterios de aceptacion

- El usuario puede crear una plantilla sin escribir HTML.
- El diseno se guarda como JSON editable.
- El sistema genera HTML final estable para email.
- Las variables simples y dinamicas se renderizan correctamente.
- La preview funciona con datos de prueba y datos reales.
- Los emails enviados desde eventos o cola usan el HTML generado.
- El editor no rompe la compatibilidad con `Mail::Send()` ni con providers futuros.

## Riesgos

- El HTML para email requiere reglas mas estrictas que HTML web normal.
- Algunos clientes de correo no soportan CSS moderno.
- Un builder externo puede introducir dependencias, licencias o HTML dificil de controlar.
- Los bloques dinamicos necesitan payloads consistentes por evento.
