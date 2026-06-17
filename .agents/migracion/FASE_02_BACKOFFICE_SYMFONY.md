# Fase 02: Back Office Symfony

## Objetivo

Migrar `Configuracion`, `Templates` y `Dashboard` desde controladores legacy a:

- rutas Symfony
- controllers `FrameworkBundleAdminController`
- formularios Symfony
- servicios definidos en `config/admin/services.yml`
- vistas Twig

## Estado

Implementacion inicial completada y estabilizada en una primera pasada.

## Entregables

- `config/routes.yml`
- controllers Symfony en `src/Controller/Admin/`
- formularios en `src/Form/`
- servicios de pagina en `src/Service/`
- constantes compartidas desacopladas del modulo principal mediante `src/ModuleConstants.php`
- vistas Twig:
  - `views/templates/admin/configuration.html.twig`
  - `views/templates/admin/templates.html.twig`
  - `views/templates/admin/dashboard.html.twig`
- legacy controllers convertidos en redirecciones hacia rutas Symfony
- eliminacion de vistas Smarty legacy de `Configuracion`, `Templates` y `Dashboard`
- limpieza de metodos muertos de Back Office en `mailsendvx.php`

## Notas

- `Configuracion` ya usa un patron real de formulario Symfony con `ConfigurationDataConfiguration` y `ConfigurationFormDataProvider`.
- `Templates` ya vive en controller Symfony y usa `TemplateFormType` + `TemplateAdminService`.
- `Dashboard` ya vive en controller Symfony y usa `DashboardViewService`.
- Se corrigio un fallo de runtime causado por referencias a `Mailsendvx::...` desde clases bajo `src/`; ahora esas referencias viven en `ModuleConstants`.
- Los tabs legacy siguen existiendo porque PrestaShop aun necesita `_legacy_controller` y la superficie de navegacion admin para enlazar con Symfony.

## Cierre de fase

- Layout y UX de `Configuracion`, `Templates` y `Dashboard` refinados en Twig con una capa visual propia del modulo y mejor jerarquia operativa.
- Acciones secundarias de templates endurecidas con CSRF explicito y confirmaciones UX para `delete` y `send test`.
- Flujos secundarios de templates aterrizados en formularios POST dedicados dentro de la superficie Symfony actual.

## Validacion manual pendiente

- Validar en Back Office real todas las rutas y flujos CRUD despues de limpiar cache del contenedor si aplica.
