# Fase 04: cierre y remocion legacy

## Objetivo

Completar la migracion y retirar las superficies legacy que ya no aportan valor.

## Estado

Pendiente.

## Trabajo recomendado

- adelgazar mas `mailsendvx.php`
- mover hooks a handlers o servicios dedicados
- evaluar migracion de persistencia a DBAL o Doctrine segun alcance real del modulo
- ocultar o retirar controllers legacy cuando la compatibilidad ya no sea necesaria
- convertir tabs y navegacion a convencion final de Back Office Symfony
- ejecutar pruebas manuales y tecnicas de instalacion, guardado y envio

## Criterio de salida

- `mailsendvx.php` queda como fachada minima
- toda la UI admin principal vive en Symfony
- la carga manual de clases legacy deja de ser necesaria
