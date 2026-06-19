# Fase 04: cierre y remocion legacy

## Objetivo

Completar la migracion y retirar las superficies legacy que ya no aportan valor.

## Estado

Completada en primera iteracion funcional.

## Trabajo realizado

- `mailsendvx.php` quedo reducido a una fachada mas pequena:
  - instala y desinstala
  - registra hooks
  - redirige al Back Office Symfony
  - resuelve servicios desde el contenedor
- se elimino el armado manual de dependencias para hooks desde la clase principal
- los hooks instantaneos siguen delegados a `InstantEmailHookService`
- la navegacion admin Symfony se centralizo en la clase del modulo
- los controllers legacy de admin quedaron como puente minimo de compatibilidad y ya no duplican resolucion de rutas

## Estado de cierre

- la UI administrativa principal vive en Symfony
- las vistas legacy de admin ya no forman parte del flujo principal
- los controllers legacy restantes existen solo para compatibilidad de tabs y `_legacy_link`
- la carga manual de clases legacy ya no es necesaria para repositorios, servicios ni controllers modernos

## Pendiente opcional, no bloqueante

- resuelto en `FASE_05_LIMPIEZA_FINAL_Y_VALIDACION.md`

## Criterio de salida

- `mailsendvx.php` queda como fachada minima: cumplido
- toda la UI admin principal vive en Symfony: cumplido
- la carga manual de clases legacy deja de ser necesaria: cumplido

## Proximos pasos recomendados

- dar por cerrada la migracion estructural del modulo
- continuar con `modules/mailsendvx/.agents/FASE_02_FLUJOS_AUTOMATIZADOS.md`
- antes de esa fase, aprovechar que ya existe `order_status_changed` y eventos especificos por estado para definir triggers de flows sin deuda de arquitectura
