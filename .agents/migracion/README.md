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
