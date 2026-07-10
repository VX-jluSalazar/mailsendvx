# Plan tecnico de Mail Send VX

Esta carpeta contiene el plan de trabajo dividido por fases para el modulo `mailsendvx`.

El objetivo del modulo es construir un motor de envio y automatizacion de emails para PrestaShop 8.x. El sistema debe soportar emails instantaneos por eventos de la tienda, flujos automatizados con cola y cron, plantillas con variables dinamicas y un futuro maquetador visual de emails.

## Archivos por fase

| Archivo | Fase | Proposito |
| --- | --- | --- |
| `FASE_00_BASE_MODULO.md` | Fase 0 | Diseno tecnico, instalacion, hooks, tablas, servicios base, configuracion y logs. |
| `FASE_01_EMAILS_INSTANTANEOS.md` | Fase 1 | Correos disparados por eventos inmediatos como cambio de estado, registro de cliente y newsletter. |
| `FASE_01C_ABANDONED_CART.md` | Fase 1C | Deteccion de carrito abandonado, dedupe por ciclo, evento `cart_abandoned` y uso inmediato en templates. |
| `FASE_01E_RECUPERACION_INMEDIATA_CARRITO_POR_HOOK.md` | Fase 1E | Recuperacion inmediata de carritos abandonados por hook, con cancelacion temprana de jobs pendientes. |
| `FASE_02_FLUJOS_AUTOMATIZADOS.md` | Fase 2 | Vision general de flows, templates reutilizables, cola, worker, condiciones y UI operativa. |
| `FASE_02A_MODELO_BASE_DE_FLOWS.md` | Fase 2A | Modelo base de flow, steps, trigger y `context_type`. |
| `FASE_02B_TEMPLATES_REUTILIZABLES.md` | Fase 2B | Templates desacoplados de `event_name` y compatibles por `context_type`. |
| `FASE_02C_SCHEDULER_Y_QUEUE.md` | Fase 2C | Scheduler, delays y persistencia de jobs en cola. |
| `FASE_02D_WORKER_LOCKING_E_IDEMPOTENCIA.md` | Fase 2D | Worker, locking, idempotencia y reintentos. |
| `FASE_02E_CONDICIONES_Y_CANCELACIONES.md` | Fase 2E | Condiciones de flow y step, cancelaciones y reevaluaciones. |
| `FASE_02F_UI_OPERATIVA_Y_CASOS_COMERCIALES.md` | Fase 2F | UI operativa y primeros flujos comerciales. |
| `FASE_03_AJUSTES_FRONT.md` | Fase 3 | Ajustes front del Dashboard con Grid AJAX reutilizable, operativa de queue, modales de detalle y navegacion parcial. |
| `FASE_03A_AUDITORIA_GRIDS_Y_BRECHAS.md` | Fase 3A | Auditoria de tablas/grids actuales y brechas tecnicas para migracion AJAX. |
| `FASE_03B_INFRAESTRUCTURA_AJAX_COMUN.md` | Fase 3B | Base comun JS y de endpoints para refresh parcial, mensajes y acciones AJAX. |
| `FASE_03C_QUEUE_OPERATIVA_Y_ACCIONES_AJAX.md` | Fase 3C | Correcciones y mejoras operativas de queue, incluyendo cancelacion y limpieza por AJAX. |
| `FASE_03D_MODAL_DETALLE_Y_ACCIONES_MASIVAS.md` | Fase 3D | Modal reutilizable por fila y acciones agrupadas para grids del Dashboard. |
| `FASE_03E_NAVEGACION_AJAX_Y_CIERRE_OPERATIVO.md` | Fase 3E | Busqueda, orden, paginacion y refresh por AJAX con cierre operativo de la fase. |
| `FASE_03_MAQUETADOR_VISUAL.md` | Fase 4 | Editor visual, bloques, JSON de diseno, renderizado responsive y previews con datos reales. |

## Roadmap recomendado

| Orden | Entregable | Resultado esperado |
| --- | --- | --- |
| 1 | Fase 0 completa | Modulo instalable, configurable y con base tecnica lista. |
| 2 | Fase 1.1 a 1.3 | Primeros eventos capturados y emails instantaneos funcionales. |
| 3 | Fase 1.4 a 1.8 | Plantillas simples, variables, preview, prueba de envio y logs. |
| 4 | Fase 01C | Evento `cart_abandoned`, criterio de abandono y dedupe por cron. |
| 5 | Fase 01E | Recuperacion inmediata por hook y cancelacion temprana de jobs de carrito abandonado. |
| 6 | Fase 2.1 a 2.4 | Cola y cron operativos para emails diferidos. |
| 7 | Fase 2.5 a 2.8 | Flujos comerciales: carrito abandonado, postcompra y suscriptores. |
| 8 | Fase 2.9 a 2.12 | Condiciones, cancelaciones, reintentos y monitoreo. |
| 9 | Fase 3A a 3E | Ajuste operativo del Dashboard: auditoria, infraestructura AJAX comun y cierre de grids. |
| 10 | Fase 4.1 a 4.4 | Editor avanzado y renderizador por bloques. |
| 11 | Fase 4.5 a 4.10 | Variables visuales, bloques dinamicos, templates predisenados y preview real. |

## Arquitectura objetivo

La arquitectura recomendada combina:

- Event Driven Architecture para convertir hooks de PrestaShop en eventos internos.
- Observer para escuchar hooks de PrestaShop.
- Command y Command Handler para ejecutar acciones internas.
- Queue/Scheduler para emails programados.
- Strategy para proveedores de envio y motores de renderizado.
- Factory Method para crear handlers, providers y renderers.
- Chain of Responsibility para validar condiciones antes de enviar.
- Repository para acceso ordenado a base de datos.
- Facade para simplificar el uso desde hooks, controllers y crons.
- Builder y Composite para el futuro maquetador visual.
- Decorator para footer, tracking, unsubscribe, UTM y wrappers.
- State para controlar estados de cola y emails.

## Estado actual observado

El modulo ya incluye la base de la Fase 0 y una implementacion funcional avanzada de la Fase 1:

- Instalacion y desinstalacion.
- Configuracion general en Back Office.
- Tabs de administracion `AdminMailsendvxConfigure`, `AdminMailsendvxTemplates` y `AdminMailsendvxDashboard`.
- Hooks `actionOrderStatusPostUpdate`, `actionCustomerAccountAdd` y `actionNewsletterRegistrationAfter`.
- Tablas base para templates, eventos, flujos, cola y logs.
- Repositorios, logger, renderer simple de variables y provider inicial con `Mail::Send()`.
- Pantalla de templates con alta, edicion, preview, borrado y envio de prueba.
- Envio instantaneo por eventos de pedido, registro de cliente y newsletter.
- Logs funcionales con estados `sent`, `failed` y `skipped`.

Adicionalmente, desde la migracion iniciada en junio de 2026:

- El modulo ya cuenta con una base moderna inicial en `composer.json`, `src/` y `config/`.
- El autoload del modulo ya se genera con Composer y ya no depende de un `vendor/autoload.php` artesanal.
- La instalacion, configuracion base, tablas y tabs ya delegan en instaladores dentro de `src/Install/`.
- Los hooks instantaneos principales ya delegan en `src/Service/Event/InstantEmailHookService.php`.
- `Configuracion`, `Templates` y `Dashboard` ya tienen una primera version basada en Symfony con rutas, controllers, forms y vistas Twig.
- Los controladores legacy de admin se mantienen solo como puente de compatibilidad hacia las rutas Symfony.
- Las vistas Smarty legacy de esas pantallas fueron retiradas.

Las fases documentadas deben usarse como guia para validar, cerrar brechas y ampliar la capacidad existente.

## Seguimiento de migracion arquitectonica

La migracion a arquitectura moderna se documenta en:

- `modules/mailsendvx/.agents/migracion/README.md`
- `modules/mailsendvx/.agents/migracion/FASE_01_BASE_MODERNA.md`
- `modules/mailsendvx/.agents/migracion/FASE_02_BACKOFFICE_SYMFONY.md`
- `modules/mailsendvx/.agents/migracion/FASE_03_DOMINIO_Y_REPOSITORIOS.md`
- `modules/mailsendvx/.agents/migracion/FASE_04_CIERRE_Y_REMOCION_LEGACY.md`

## Ajuste de arquitectura ya aplicado

### Separacion de eventos por estado de pedido

Revision del modulo en junio de 2026:

- El hook `actionOrderStatusPostUpdate` existe y funciona.
- La implementacion ya no depende solo de `order_status_updated`.
- Hoy el modulo dispara un evento generico `order_status_changed`.
- Tambien dispara un evento especifico por estado destino usando la forma `order_status_changed_{state_key}`.
- El evento legado `order_status_updated` se conserva como compatibilidad temporal cuando existe una plantilla activa asociada.

Esto deja una base suficiente para distinguir logica generica y logica especifica por estado sin bloquear la migracion de plantillas antiguas.

### Decision aplicada

Separar el hook tecnico del evento funcional:

- Hook tecnico unico: `actionOrderStatusPostUpdate`.
- Evento generico interno: `order_status_changed`.
- Evento especifico interno por estado destino: `order_status_changed_{state_key}`.

Ejemplos:

- `order_status_changed`
- `order_status_changed_payment_accepted`
- `order_status_changed_shipped`
- `order_status_changed_delivered`
- `order_status_changed_canceled`

### Reglas de implementacion aplicadas

1. Resolver un `state_key` estable desde `OrderState`, priorizando `template` y usando nombre normalizado o fallback por ID cuando aplica.
2. No usar directamente el nombre traducido del estado como identificador funcional principal cuando hay una clave mas estable disponible.
3. Guardar en variables tanto el ID del estado como su clave normalizada y su nombre visible.
4. Mantener compatibilidad temporal con `order_status_updated` mientras se migran plantillas existentes.
5. Permitir que un mismo cambio de estado active una plantilla generica y otra especifica del estado final.

### Variables minimas nuevas para eventos de estado

- `order_state_id`
- `order_state_key`
- `order_state_name`
- `old_order_state_id`
- `old_order_state_key`
- `old_order_state_name`

### Construccion moderna del payload

La construccion de contexto ya usa `TemplateContextPayloadBuilder` como orquestador y builders de segmentos para:

- `event`
- `shop`
- `customer`
- `cart`
- `order`
- `products`
- `related_products`
- `reviews`

Esto alinea envio real, previews, fixtures y documentacion con una sola estructura de payload compuesta.

### Impacto en el roadmap

- Fase 1 ya incorpora el refactor principal de eventos instantaneos de estado.
- Fase 1 todavia requiere validacion funcional real, limpieza documental y pequenos ajustes de consistencia.
- Fase 01C debe introducir `cart_abandoned` como evento estable y deduplicado antes de usarlo en automatizaciones.
- Fase 01E debe reducir la dependencia exclusiva del cron para marcar recuperacion de carrito y cancelar jobs pendientes en tiempo oportuno.
- Fase 2 debe construir sus flujos postcompra sobre `order_status_changed` y `order_status_changed_{state_key}`, y debe consumir `cart_abandoned` desde Fase 01C en lugar de redefinirlo.
- Fase 3 debe usar esta misma taxonomia para plantillas predisenadas y previews reales por estado.
