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
