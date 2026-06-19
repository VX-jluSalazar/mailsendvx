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
- `FASE_05_LIMPIEZA_FINAL_Y_VALIDACION.md`

## Estado actual resumido

- Fase 01 de migracion completada: base moderna creada y aterrizada en codigo.
- Fase 02 de migracion completada en primera iteracion funcional.
- Fase 03 de migracion completada en primera iteracion funcional: repositorios y servicios principales ya viven en `src/` y ya no dependen de carga manual desde `classes/`.
- Fase 04 de migracion completada en primera iteracion funcional: `mailsendvx.php` ya opera como fachada, y los controllers legacy de admin quedaron reducidos a puentes minimos de compatibilidad.
- Fase 05 de migracion completada en primera iteracion funcional: los bridges legacy de admin fueron retirados, las tabs ya navegan por `route_name`, y la persistencia de repositorios se migro a DBAL.
- Las pantallas `Configuracion`, `Templates` y `Dashboard` ya tienen rutas, controllers y vistas Symfony.
- Los controladores legacy de admin ya no son necesarios y fueron retirados.
- Las vistas Smarty legacy de esas pantallas ya fueron retiradas.
- El autoload del modulo ya es generado por Composer y los hooks instantaneos principales ya delegan en servicios.

## Conclusiones

- La migracion estructural principal puede darse por cerrada.
- La limpieza final de compatibilidad legacy del Back Office tambien puede darse por cerrada.
- No hay un bloqueador de arquitectura que impida avanzar a funcionalidades nuevas.
- La deuda restante ya no esta en la base administrativa ni en la persistencia actual del modulo.

## Siguiente fase sugerida

- Continuar con `modules/mailsendvx/.agents/FASE_02_FLUJOS_AUTOMATIZADOS.md`.
