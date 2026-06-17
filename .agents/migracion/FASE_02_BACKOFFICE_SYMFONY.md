# Fase 02: Back Office Symfony

## Objetivo

Migrar `Configuracion`, `Templates` y `Dashboard` desde controladores legacy a:

- rutas Symfony
- controllers `FrameworkBundleAdminController`
- formularios Symfony
- servicios definidos en `config/admin/services.yml`
- vistas Twig

## Estado

Implementacion inicial completada.

## Entregables

- `config/routes.yml`
- controllers Symfony en `src/Controller/Admin/`
- formularios en `src/Form/`
- servicios de pagina en `src/Service/`
- vistas Twig:
  - `views/templates/admin/configuration.html.twig`
  - `views/templates/admin/templates.html.twig`
  - `views/templates/admin/dashboard.html.twig`
- legacy controllers convertidos en redirecciones hacia rutas Symfony

## Notas

- `Configuracion` ya usa un patron real de formulario Symfony con `ConfigurationDataConfiguration` y `ConfigurationFormDataProvider`.
- `Templates` ya vive en controller Symfony y usa `TemplateFormType` + `TemplateAdminService`.
- `Dashboard` ya vive en controller Symfony y usa `DashboardViewService`.

## Pendientes

- Refinar layout y UX de las paginas Twig para igualar o superar la vista legacy.
- Añadir CSRF y confirmaciones UX mas robustas en acciones secundarias.
- Convertir mas acciones de templates a formularios y comandos dedicados.
