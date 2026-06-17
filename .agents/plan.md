# Plan tecnico de Mail Send VX

Esta carpeta contiene el plan de trabajo dividido por fases para el modulo `mailsendvx`.

El objetivo del modulo es construir un motor de envio y automatizacion de emails para PrestaShop 8.x. El sistema debe soportar emails instantaneos por eventos de la tienda, flujos automatizados con cola y cron, plantillas con variables dinamicas y un futuro maquetador visual de emails.

## Archivos por fase

| Archivo | Fase | Proposito |
| --- | --- | --- |
| `FASE_00_BASE_MODULO.md` | Fase 0 | Diseno tecnico, instalacion, hooks, tablas, servicios base, configuracion y logs. |
| `FASE_01_EMAILS_INSTANTANEOS.md` | Fase 1 | Correos disparados por eventos inmediatos como cambio de estado, registro de cliente y newsletter. |
| `FASE_02_FLUJOS_AUTOMATIZADOS.md` | Fase 2 | Motor de eventos, flujos, cola, cron, carrito abandonado, postcompra, condiciones y reintentos. |
| `FASE_03_MAQUETADOR_VISUAL.md` | Fase 3 | Editor visual, bloques, JSON de diseno, renderizado responsive y previews con datos reales. |

## Roadmap recomendado

| Orden | Entregable | Resultado esperado |
| --- | --- | --- |
| 1 | Fase 0 completa | Modulo instalable, configurable y con base tecnica lista. |
| 2 | Fase 1.1 a 1.3 | Primeros eventos capturados y emails instantaneos funcionales. |
| 3 | Fase 1.4 a 1.8 | Plantillas simples, variables, preview, prueba de envio y logs. |
| 4 | Fase 2.1 a 2.4 | Cola y cron operativos para emails diferidos. |
| 5 | Fase 2.5 a 2.8 | Flujos comerciales: carrito abandonado, postcompra y suscriptores. |
| 6 | Fase 2.9 a 2.12 | Condiciones, cancelaciones, reintentos y monitoreo. |
| 7 | Fase 3.1 a 3.4 | Editor avanzado y renderizador por bloques. |
| 8 | Fase 3.5 a 3.10 | Variables visuales, bloques dinamicos, templates predisenados y preview real. |

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

El modulo ya incluye una base inicial:

- Instalacion y desinstalacion.
- Configuracion general en Back Office.
- Tab de administracion `AdminMailsendvxDashboard`.
- Hooks `actionOrderStatusPostUpdate`, `actionCustomerAccountAdd` y `actionNewsletterRegistrationAfter`.
- Tablas base para templates, eventos, flujos, cola y logs.
- Repositorios, logger, renderer simple de variables y provider inicial con `Mail::Send()`.

Las fases documentadas deben usarse como guia para completar, validar y ampliar esa base.

## Ajuste de arquitectura pendiente

### Separacion de eventos por estado de pedido

Revision del modulo en junio de 2026:

- El hook `actionOrderStatusPostUpdate` existe y funciona.
- La implementacion actual dispara siempre el mismo evento interno: `order_status_updated`.
- Eso obliga a que todos los cambios de estado de pedido compartan la misma logica, la misma resolucion de plantilla y el mismo punto de entrada funcional.
- El resultado es una granularidad insuficiente para casos como pago aceptado, pedido enviado, entregado, cancelado o estados personalizados.

### Decision recomendada

Separar el hook tecnico del evento funcional:

- Hook tecnico unico: `actionOrderStatusPostUpdate`.
- Evento generico interno: `order_status_changed`.
- Evento especifico interno por estado destino: `order_status_{state_key}`.

Ejemplos:

- `order_status_changed`
- `order_status_payment_accepted`
- `order_status_shipped`
- `order_status_delivered`
- `order_status_canceled`

### Reglas de implementacion recomendadas

1. Resolver un `state_key` estable desde `OrderState`.
2. No usar directamente el nombre traducido del estado como identificador funcional.
3. Guardar en variables tanto el ID del estado como su clave normalizada y su nombre visible.
4. Mantener compatibilidad temporal con `order_status_updated` mientras se migran plantillas existentes.
5. Permitir que un mismo cambio de estado pueda activar una plantilla generica y otra especifica del estado final.

### Variables minimas nuevas para eventos de estado

- `order_state_id`
- `order_state_key`
- `order_state_name`
- `old_order_state_id`
- `old_order_state_key`
- `old_order_state_name`

### Impacto en el roadmap

- Fase 1 debe refactorizar los emails instantaneos de estado para soportar eventos especificos.
- Fase 2 debe construir sus flujos postcompra sobre esta taxonomia de eventos, no sobre un unico `order_status_updated`.
- Fase 3 debe usar esta misma taxonomia para plantillas predisenadas y previews reales por estado.
