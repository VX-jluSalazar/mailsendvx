# Fase 02F: UI operativa y casos comerciales

## Estado

Implementada en primera iteración funcional.

## Alcance implementado

- nueva pestaña Back Office para `Flows`,
- editor visual de flows con `trigger_event`, `context_type`, prioridad, activación y descripción,
- builder de steps con orden manual, delays, `conditions` y `cancel_rules`,
- filtro de plantillas compatibles por `context_type`,
- distinción visual entre plantillas `Reusable en flows` e `Instantánea`,
- panel de casos comerciales con presets importables,
- tabla operativa de queue con cancelación manual de jobs `pending` o `scheduled`,
- bloque de monitoreo con métricas por estado y logs recientes.

## Archivos principales

- `src/Service/Admin/FlowAdminService.php`
- `src/Controller/Admin/FlowsController.php`
- `views/templates/admin/flows.html.twig`
- `views/css/admin.css`
- `config/routes.yml`
- `config/components/controller/controllers.yml`
- `config/components/service/services.yml`
- `src/Install/TabInstaller.php`
- `src/ModuleConstants.php`

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

## Validaciones de UI implementadas

- no permitir asociar templates incompatibles con el `context_type` del flow en el selector del builder,
- permitir templates con `event_name = NULL` y priorizarlos como reutilizables,
- diferenciar templates instantáneos de templates reutilizables en labels y ayuda contextual,
- filtrar `trigger_event` por `context_type`,
- mostrar estados de cola y errores operativos de forma clara en badges y tablas.

## Casos comerciales iniciales

### Carrito abandonado

- preset `Carrito abandonado · Recuperación en 3 tiempos`,
- 3 steps encadenados,
- cancelación automática si el carrito se convierte en pedido o se recupera antes.

### Postcompra

- preset `Postcompra · Confirmación inmediata`,
- preset `Postcompra · Pago aceptado`,
- preset `Postcompra · Seguimiento de envío`,
- preset `Postcompra · Reseña tras entrega`.

### Suscriptores

- preset `Newsletter · Bienvenida y nurture`,
- bienvenida inmediata,
- email educativo,
- incentivo opcional en tercer contacto.

## Monitoreo minimo esperado

- jobs pendientes,
- jobs programados,
- jobs procesados,
- fallidos,
- cancelados,
- logs recientes asociados a flow y template.

## Navegación operativa implementada

- nueva ruta `mailsendvx_flows`,
- nueva pestaña `AdminMailsendvxFlows`,
- edición de flow existente mediante `?edit={id}`,
- cancelación manual por job vía `mailsendvx_queue_cancel`.

## Diseño aplicado

- misma familia visual del módulo para no romper continuidad,
- superficie de “mesa de ruteo” con hero operativo y control strip,
- layout dividido entre editor, recetas comerciales y monitoreo,
- animación ligera y opt-in vía `prefers-reduced-motion`,
- responsive para escritorio y móvil.

## Limitaciones actuales

- el builder usa `JSON` libre para `conditions` y `cancel_rules`; todavía no existe constructor visual de reglas,
- la UI permite crear y editar flows, pero no eliminar flows desde Back Office,
- los presets reutilizan plantillas compatibles existentes; si no hay suficientes, el preset falla con mensaje,
- la cola mostrada es reciente y operativa, no una grilla paginada completa,
- la cancelación manual cubre jobs `pending` y `scheduled`, no `processing`.

## Criterios de aceptacion

- La UI permite crear y editar flows.
- La UI permite asociar varios templates a traves de steps.
- La UI valida compatibilidad por `context_type`.
- Existen flujos iniciales usables para carrito, postcompra y suscriptores.

## Validacion realizada

- `php -l src/Service/Admin/FlowAdminService.php`
- `php -l src/Controller/Admin/FlowsController.php`
- `php -l src/Install/TabInstaller.php`
- `php -l src/ModuleConstants.php`
- `php -l mailsendvx.php`
