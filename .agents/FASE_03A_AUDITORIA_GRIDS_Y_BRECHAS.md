# Fase 03A: Auditoria de grids y brechas

## Estado

Ejecutada sobre la base actual del modulo.

## Objetivo

Analizar la arquitectura actual del Dashboard para identificar que tablas usan PrestaShop Grid nativa, cuales son personalizadas y que brechas existen para soportar comportamiento AJAX comun.

## Alcance

- inventariar tablas y vistas del Dashboard,
- identificar `GridDefinitionFactory`, `QueryBuilder`, controladores Symfony y templates Twig existentes,
- detectar tablas renderizadas fuera del sistema Grid,
- revisar como se ejecutan hoy las acciones de fila, bulk actions, filtros y paginacion,
- documentar endpoints ya disponibles y endpoints faltantes para refresh parcial y detalle.

## Entregables

- mapa de tablas del Dashboard con tipo de implementacion,
- lista de acciones actuales por tabla,
- brechas tecnicas por tabla para migrar a comportamiento AJAX,
- propuesta de orden de migracion empezando por una grid piloto.

## Preguntas que esta subfase debe responder

- que grids ya soportan busqueda, orden y paginacion nativa,
- cual tabla es la mejor candidata para piloto,
- que partes del Dashboard estan acopladas a redirecciones legacy,
- donde conviene introducir una capa comun sin romper controladores actuales.

## Criterios de aceptacion

- existe un inventario completo de tablas y grids afectadas,
- cada tabla queda clasificada como `Grid nativa`, `mixta` o `personalizada`,
- quedan listadas las rutas y acciones que requieren refactor,
- la siguiente subfase puede arrancar sin redescubrir arquitectura.

## Resultado de la auditoria

### Inventario observado

| Pantalla | Implementacion | Estado observado | Comentario |
| --- | --- | --- | --- |
| `Dashboard > Eventos` | `Grid nativa` | Activa | Usa `MailSendVxEventGridDefinitionFactory`, `MailSendVxEventQueryBuilder` y render Twig con `grid_panel`. |
| `Dashboard > Queue` | `Grid nativa` | Activa | Usa `MailSendVxQueueGridDefinitionFactory`, bulk actions y cancelacion por fila, pero la accion no estaba preparada para flujo AJAX parcial. |
| `Dashboard > Logs` | `Grid nativa` | Activa | Usa `MailSendVxLogGridDefinitionFactory` y query builder propio. |
| `Templates` | `Grid nativa` | Activa | Usa `MailSendVxTemplateGridDefinitionFactory`, preview modal por AJAX y acciones POST clasicas. |
| `Flows` | `Personalizada` | Activa | Lista Twig manual, sin `GridFactory`, sin filtros/paginacion nativa ni capa AJAX de grid. |
| `Configuracion Dashboard` | `Personalizada` | Activa | Form Symfony, no aplica como grid. |

### Servicios y piezas detectadas

- factories de grid ya registradas en `config/components/grid/grids.yml`,
- controladores Symfony para `Dashboard` y `Templates` ya usan `GridFactoryInterface`,
- `DashboardController` manejaba tabs AJAX, pero no refresh parcial por grid,
- `TemplatesController` manejaba preview AJAX, pero no refresh parcial de grid ni acciones CRUD por JSON,
- `FlowsController` conserva acciones de queue con redirect y flash messages.

### Brechas tecnicas identificadas

#### 1. Busqueda y ordenamiento

- las grids ya existen, pero el flujo depende del comportamiento clasico de `ResponseBuilder`,
- eso mantiene compatibilidad, pero produce navegacion completa al filtrar, ordenar o paginar.

#### 2. Refresh parcial

- no existia endpoint dedicado para renderizar una sola grid,
- el JS comun inicializaba extensiones de PrestaShop Grid, pero no interceptaba cambios de estado ni reemplazo parcial de HTML.

#### 3. Acciones POST

- `Templates` y `Queue` usan acciones por fila y masivas,
- esas acciones terminaban en redirects con flash messages,
- no habia contrato JSON comun para reutilizar acciones AJAX.

#### 4. Cobertura desigual

- `Dashboard` y `Templates` ya estaban sobre Grid nativa y son aptos para la Fase 03B,
- `Flows` no comparte esa base todavia y queda fuera de la infraestructura de grid AJAX hasta una migracion propia.

### Decision de implementacion derivada

- tomar `Dashboard` y `Templates` como primera base comun,
- introducir wrappers Twig con `data-*` por grid,
- exponer endpoints parciales por grid para `Dashboard` y `Templates`,
- dejar `Flows` documentada como lista personalizada fuera del alcance inmediato de 03B,
- reservar la adaptacion completa de acciones operativas de `Queue` para `FASE_03C_QUEUE_OPERATIVA_Y_ACCIONES_AJAX.md`.
