# Fase 01B: migracion del motor de templates a Twig

## Estado

Implementada en primera iteracion funcional.

Fase 00 y Fase 01 ya estan implementadas. Fase 02 y Fase 03 aun no se implementan.

Por esta razon, la migracion a Twig debe ejecutarse como una fase intermedia antes de construir flujos automatizados y antes del maquetador visual.

## Estado real de la implementacion

### Ya implementado

- Motor dual de render con compatibilidad entre placeholders legacy y Twig.
- Render Twig para `subject`, `html_content` y `text_content`.
- Compatibilidad temporal con templates legacy existentes sin migracion forzada.
- Preview del Back Office usando el nuevo renderer.
- Validacion controlada de errores Twig en preview y envio de prueba.
- Contexto de ejemplo estructurado con `products`, `related_products`, `reviews`, `billing_address`, `shipping_address`, `shipping` y `order_totals`.
- Contexto real enriquecido para eventos de pedido con listas de productos, direcciones y totales.
- Builders de contexto separados por dominio para pedido, cliente y newsletter.
- Preview con payload historico cuando existe un evento capturado del mismo tipo.
- Gestion editable de wrappers desde Back Office usando archivos fisicos del modulo.
- Guia dinamica de atributos por evento dentro de la pantalla de creacion/edicion de templates.

### Pendiente recomendado

- endurecer aun mas el entorno Twig si en una iteracion futura se quiere reducir tags, filtros o expresiones permitidas,
- evolucionar wrappers a gestion multilenguaje diferenciada si el negocio lo necesita,
- ampliar previews con selector explicito de eventos historicos en lugar de usar solo el ultimo payload disponible,
- separar recomendaciones y reviews reales por provider o fuente de datos cuando esas fuentes existan en Fase 02 o Fase 03.

## Objetivo

Sustituir el renderer MVP basado en placeholders simples por un motor de templates Twig controlado, reutilizable y compatible con la arquitectura moderna del modulo.

La meta no es aun crear un editor visual, sino dejar una capa de render suficientemente potente para:

- listas y bloques dinamicos,
- condiciones simples en templates,
- wrappers reutilizables,
- previews mas fieles,
- futura compilacion desde un maquetador visual.

## Motivacion arquitectonica

El motor actual solo resuelve reemplazos directos tipo `{variable}`.

Ese enfoque fue suficiente para la Fase 01, pero empieza a limitar:

- render de listas de productos,
- recomendaciones,
- reviews,
- bloques condicionales,
- composicion entre contenido y wrapper,
- evolucion posterior hacia builder visual.

Twig permite introducir estructuras como `for`, `if`, filtros y parciales sin obligar todavia a construir un maquetador completo.

## Alcance funcional

| Subfase | Objetivo | Complejidad |
| --- | --- | --- |
| 1B.1 Contrato del contexto | Definir payloads canonicos por evento y distinguir variables simples de estructuras complejas. | Media |
| 1B.2 Motor de render | Introducir `TemplateEngineInterface` y una implementacion Twig. | Media |
| 1B.3 Compatibilidad temporal | Mantener soporte de templates legacy de placeholders durante la transicion. | Media |
| 1B.4 Render de subject/html/text | Renderizar asunto, HTML y texto usando Twig controlado. | Media |
| 1B.5 Bloques dinamicos iniciales | Habilitar listas de `products`, `related_products` y otros bloques renderizables. | Alta |
| 1B.6 Preview mejorado | Mostrar errores de sintaxis y preview con datos de ejemplo estructurados. | Media |
| 1B.7 Test send | Ejecutar envio de prueba usando el nuevo motor. | Media |
| 1B.8 Wrappers modernos | Separar contenido interno del wrapper y preparar wrappers editables o seleccionables. | Media-alta |
| 1B.9 Documentacion de variables | Publicar contrato de contexto por evento y ejemplos Twig. | Media |

## Alcance tecnico

### Cambio principal

Reemplazar la dependencia directa de `MailSendVxVariableRenderer` por una abstraccion de motor de templates.

Propuesta:

- `TemplateEngineInterface`
- `LegacyPlaceholderTemplateEngine`
- `TwigTemplateEngine`
- `TemplateContextBuilderInterface`
- builders por dominio, por ejemplo:
  - `OrderTemplateContextBuilder`
  - `CustomerTemplateContextBuilder`
  - `NewsletterTemplateContextBuilder`

### Reglas de migracion

1. No romper templates ya guardados en Fase 01.
2. Permitir una estrategia dual temporal:
   - modo legacy para plantillas existentes,
   - modo Twig para plantillas nuevas o migradas.
3. Mantener `Mail::Send()` como transporte mientras cambia solo la capa de render.
4. Evitar meter logica de negocio en Twig; Twig debe consumir contexto ya normalizado.

## Flujo tecnico objetivo

```txt
Hook de PrestaShop
|
Context Builder por evento
|
Contexto canonico estructurado
|
Template Engine (Twig o legacy)
|
Wrapper + subject + html + text renderizados
|
Provider de envio
|
Log y persistencia
```

## Variables y estructuras objetivo

### Variables simples

- `customer_name`
- `customer_email`
- `order_reference`
- `order_total`
- `shop_name`
- `shop_url`

### Estructuras complejas

- `order`
- `billing_address`
- `shipping_address`
- `products`
- `related_products`
- `reviews`

Ejemplo esperado en Twig:

```twig
<h2>Pedido {{ order_reference }}</h2>

{% if products is not empty %}
  <ul>
    {% for product in products %}
      <li>{{ product.name }} x{{ product.quantity }} - {{ product.price }}</li>
    {% endfor %}
  </ul>
{% endif %}
```

## Patrones recomendados

- Strategy para intercambiar motores de render.
- Facade para mantener `sendEvent()` como punto unico de entrada.
- Builder para construir contexto de templates.
- Adapter para compatibilidad temporal entre renderer legacy y Twig.
- Decorator para wrappers, bloques comunes y helpers de email.

## Dependencias

- Fase 00 implementada.
- Fase 01 implementada.

Esta fase debe completarse antes de Fase 02 y antes de Fase 03.

## Archivos y zonas a intervenir

- `src/Service/MailSendVxMailer.php`
- `src/Service/MailSendVxVariableRenderer.php` o su reemplazo
- `src/Service/InstantEmailHookService.php`
- `src/Service/TemplateAdminService.php`
- `src/Form/Type/TemplateFormType.php`
- `views/templates/admin/templates.html.twig`
- `mails/*` para wrappers
- fixtures y documentacion de payloads por evento

## Como probar la funcionalidad

### Prueba 1: template Twig simple

1. Crear una plantilla nueva en modo Twig.
2. Usar `{{ customer_name }}` y `{{ order_reference }}`.
3. Previsualizar.
4. Confirmar que se renderiza sin errores.

### Prueba 2: loop de productos

1. Crear una plantilla de pedido.
2. Agregar un `for` sobre `products`.
3. Previsualizar con datos de ejemplo estructurados.
4. Confirmar que la lista se renderiza correctamente.

### Prueba 3: compatibilidad legacy

1. Mantener una plantilla vieja basada en `{order_reference}`.
2. Disparar un evento real.
3. Confirmar que sigue enviando mientras dure la migracion.

### Prueba 4: errores de sintaxis

1. Guardar un template Twig con error.
2. Confirmar que el Back Office informa el error de forma controlada.
3. Confirmar que no se rompe toda la pantalla.

## Criterios de aceptacion

- El modulo puede renderizar templates con Twig.
- El asunto, HTML y texto admiten Twig.
- Es posible iterar listas y usar condicionales simples.
- La compatibilidad temporal con templates legacy sigue disponible.
- El preview del admin detecta errores Twig.
- Fase 02 puede consumir ya el motor Twig.
- Fase 03 puede construirse encima de Twig sin rediseñar otra vez la capa de render.

## Riesgos

- Exponer Twig sin restricciones puede abrir superficie innecesaria.
- Una migracion brusca puede romper templates existentes.
- Mezclar demasiada logica en el template puede volver dificil el mantenimiento.
- Si el contrato del contexto no queda estable, Fase 02 y Fase 03 heredaran deuda.

## Siguiente paso sugerido

- completar esta fase antes de arrancar desarrollo funcional de `modules/mailsendvx/.agents/FASE_02_FLUJOS_AUTOMATIZADOS.md`
- usar Twig como base obligatoria para `modules/mailsendvx/.agents/FASE_03_MAQUETADOR_VISUAL.md`
