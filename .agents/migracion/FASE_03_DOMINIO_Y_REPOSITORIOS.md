# Fase 03: dominio y repositorios modernos

## Objetivo

Reducir el acoplamiento con clases globales legacy:

- mover repositorios legacy de `classes/Repository` a `src/Repository`
- mover mailer, logger y renderer a `src/Service`
- registrar dependencias reales en el contenedor
- evitar `require_once` manuales

## Estado

Pendiente.

## Trabajo recomendado

- Migrar `MailSendVxTemplateRepository`
- Migrar `MailSendVxLogRepository`
- Migrar `MailSendVxEventRepository`
- Migrar `MailSendVxQueueRepository`
- Migrar `MailSendVxMailer`
- Migrar `MailSendVxVariableRenderer`
- Eliminar `LegacyClassLoader`

## Criterio de salida

El modulo Symfony y la clase principal ya no dependen de clases cargadas manualmente desde `classes/`.
