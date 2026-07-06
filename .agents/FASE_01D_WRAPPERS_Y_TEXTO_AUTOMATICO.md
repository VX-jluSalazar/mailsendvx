# Fase 01D: wrappers editables y texto automatico

## Estado

Implementada en primera iteracion funcional.

Esta fase documenta y consolida dos mejoras editoriales sobre la base de Fase 01B:

- gestion explicita de wrappers desde Back Office,
- generacion automatica del `text_content` del template a partir del HTML al guardar.

## Objetivo

Hacer que el Back Office trate el HTML como fuente de verdad del cuerpo del email y que los wrappers puedan editarse o crearse sin salir del modulo.

## Motivacion

Antes de esta fase, la capacidad tecnica ya existia parcialmente, pero quedaba mezclada dentro del formulario general:

- el wrapper podia editarse, pero no estaba presentado como una seccion clara de header y footer,
- el texto plano del template dependia de dejar el campo vacio para regenerarse,
- eso permitia desalineaciones entre `html_content` y `text_content`.

La meta de `01D` es reducir esa friccion editorial y dejar el comportamiento del guardado mucho mas predecible.

## Estado real de la implementacion

### Ya implementado

- Nueva seccion visible `Wrapper editor` en la pantalla de templates.
- Agrupacion del formulario en bloques:
  - datos generales del template,
  - contenido del template,
  - edicion del wrapper.
- El archivo base mas comun para wrappers sigue siendo:
  - `modules/mailsendvx/mails/es/mailsendvx_default.html`
  - `modules/mailsendvx/mails/en/mailsendvx_default.html`
- El guardado del template ahora regenera siempre `text_content` desde `html_content`.
- El campo `Text content` queda como referencia de salida y ya no como fuente editable primaria.
- Si se crea un wrapper nuevo sin texto plano manual, el sistema genera una version base desde el HTML y corrige el placeholder para usar `{mailsendvx_text_content}`.
- La persistencia de wrappers sigue escribiendo archivos fisicos por idioma en `mails/{iso}/`.

### Pendiente recomendado

- agregar preview en vivo del texto plano generado antes de guardar,
- ofrecer un selector visual mas guiado para wrappers existentes,
- permitir una estrategia avanzada de wrappers distintos por idioma cuando el negocio quiera divergencia real entre `es` y `en`,
- evaluar validaciones mas estrictas para asegurar que el wrapper HTML incluya `{mailsendvx_html_content}` y el wrapper texto incluya `{mailsendvx_text_content}`.

## Reglas funcionales

### Fuente de verdad del contenido

- `html_content` es la fuente principal del cuerpo del template.
- `text_content` se recalcula automaticamente cada vez que se guarda.
- el objetivo es mantener consistencia entre HTML y texto plano sin depender de disciplina manual.

### Reglas del wrapper

- `mail_template` identifica la llave del wrapper.
- al activar `Save wrapper changes`, el modulo crea o actualiza:
  - `mails/{iso}/{wrapper}.html`
  - `mails/{iso}/{wrapper}.txt`
- el wrapper HTML debe contener `{mailsendvx_html_content}`.
- el wrapper texto debe contener `{mailsendvx_text_content}`.

## Flujo tecnico

```txt
Edicion en Back Office
|
Usuario modifica HTML del template
|
Usuario puede modificar o crear wrapper
|
Guardar template
|
Regenerar text_content desde html_content
|
Si aplica, guardar wrapper html/txt en mails/{iso}/
|
Persistir template y dejarlo listo para preview, test send y eventos reales
```

## Archivos principales involucrados

- `src/Service/TemplateAdminService.php`
- `src/Service/TemplateContentService.php`
- `src/Form/Type/TemplateFormType.php`
- `views/templates/admin/templates.html.twig`
- `src/Service/MailTemplateWrapperService.php`
- `mails/es/mailsendvx_default.html`
- `mails/es/mailsendvx_default.txt`
- `mails/en/mailsendvx_default.html`
- `mails/en/mailsendvx_default.txt`

## Detalles de implementacion

### Guardado del template

`TemplateAdminService::saveTemplate()` ahora:

- toma `html_content`,
- genera `text_content` con `TemplateContentService::generateTextContentFromHtml()`,
- persiste ambos valores manteniendo el texto plano alineado con el HTML.

### Guardado del wrapper

`MailTemplateWrapperService` sigue siendo responsable de:

- detectar wrappers disponibles,
- cargar contenido por idioma,
- guardar `.html` y `.txt` del wrapper.

Cuando el texto del wrapper llega vacio y existe HTML:

- se genera una version de texto base desde el HTML,
- se reemplaza `{mailsendvx_html_content}` por `{mailsendvx_text_content}` para no romper el mail de texto.

## Como probar la funcionalidad

### Prueba 1: texto automatico del template

1. Abrir un template existente.
2. Cambiar `HTML content`.
3. Guardar.
4. Confirmar que `Text content` refleja la conversion del HTML guardado.

### Prueba 2: crear wrapper nuevo

1. Escribir una nueva llave en `Mail wrapper`.
2. Completar `Wrapper HTML`.
3. Activar `Save wrapper changes`.
4. Guardar.
5. Confirmar que se crean los archivos `.html` y `.txt` en `mails/es/` y `mails/en/`.

### Prueba 3: actualizar wrapper base

1. Cargar `mailsendvx_default`.
2. Cambiar header o footer desde `Wrapper editor`.
3. Guardar con `Save wrapper changes`.
4. Confirmar que el cambio se refleja en los archivos del modulo y en el preview.

## Criterios de aceptacion

- El Back Office muestra una seccion clara para wrappers.
- El usuario puede crear o modificar wrappers desde la UI.
- `text_content` del template queda sincronizado automaticamente con `html_content` al guardar.
- Los wrappers siguen siendo compatibles con el provider de correo de PrestaShop.

## Riesgos y notas

- La conversion HTML -> texto sigue siendo deliberadamente simple; no intenta reconstruir layout complejo.
- Si se necesitan emails de texto altamente curados, habra que definir una fase posterior con modo manual opcional.
- Los wrappers se guardan en todos los idiomas disponibles, por lo que conviene revisar si a futuro se quiere independencia por idioma.

## Siguiente paso sugerido

Con la experiencia editorial estabilizada, el siguiente paso funcional vuelve a ser `modules/mailsendvx/.agents/FASE_02_FLUJOS_AUTOMATIZADOS.md`.
