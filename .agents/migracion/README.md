# Migracion a arquitectura moderna

Esta carpeta documenta la migracion progresiva del modulo `mailsendvx` desde una base legacy de PrestaShop hacia una arquitectura moderna alineada con PrestaShop 8.

## Objetivo

- Reducir la clase principal del modulo.
- Mover logica tecnica y de dominio a `src/`.
- Preparar Back Office Symfony con rutas, controllers, forms y servicios.
- Mantener compatibilidad operativa mientras se completa la migracion.

## Fases documentadas

- `FASE_01_BASE_MODERNA.md`
- `FASE_02_BACKOFFICE_SYMFONY.md`
- `FASE_03_DOMINIO_Y_REPOSITORIOS.md`
- `FASE_04_CIERRE_Y_REMOCION_LEGACY.md`

## Estado actual resumido

- Fase 01 de migracion completada: base moderna creada y aterrizada en codigo.
- Fase 02 de migracion completada en primera iteracion funcional.
- Fase 03 de migracion completada en primera iteracion funcional: repositorios y servicios principales ya viven en `src/` y ya no dependen de carga manual desde `classes/`.
- Las pantallas `Configuracion`, `Templates` y `Dashboard` ya tienen rutas, controllers y vistas Symfony.
- Los controladores legacy de admin se conservan solo como puente de navegacion hacia Symfony.
- Las vistas Smarty legacy de esas pantallas ya fueron retiradas.
- El autoload del modulo ya es generado por Composer y los hooks instantaneos principales ya delegan en servicios.
- La siguiente fase real de trabajo es `FASE_04_CIERRE_Y_REMOCION_LEGACY.md`.
