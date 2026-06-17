# Fase 03: dominio y repositorios modernos

## Objetivo

Reducir el acoplamiento con clases globales legacy:

- mover repositorios legacy de `classes/Repository` a `src/Repository`
- mover mailer, logger y renderer a `src/Service`
- registrar dependencias reales en el contenedor
- evitar `require_once` manuales

## Estado

Pendiente. Esta fase ya es la siguiente prioridad tecnica natural tras cerrar la base moderna y el primer Back Office Symfony.

## Trabajo recomendado

- Migrar `MailSendVxTemplateRepository`
- Migrar `MailSendVxLogRepository`
- Migrar `MailSendVxEventRepository`
- Migrar `MailSendVxQueueRepository`
- Migrar `MailSendVxMailer`
- Migrar `MailSendVxVariableRenderer`
- Inyectar estas dependencias via contenedor en lugar de crearlas con `new ...` en servicios modernos.
- Eliminar `LegacyClassLoader`
- Eliminar llamadas nuevas a clases globales `MailSendVx*` desde servicios Symfony

## Criterio de salida

El modulo Symfony y la clase principal ya no dependen de clases cargadas manualmente desde `classes/`.
