<?php

namespace Velox\MailSendVx\Service;

class TemplateContentService
{
    public function getDefaultHtmlContent(string $eventName, string $orderCreatedEvent, string $customerRegisteredEvent, string $newsletterRegisteredEvent, string $cartAbandonedEvent): string
    {
        if ($eventName === $orderCreatedEvent) {
            return '<p>Hola {{ customer.firstname ?: customer.name }},</p><p>Hemos creado tu pedido <strong>{{ order.reference }}</strong> en {{ shop.name }}.</p><p>Total: {{ order.totals.paid }}</p><p><a href="{{ shop.url }}">Visitar la tienda</a></p>';
        }

        if ($eventName === $customerRegisteredEvent) {
            return '<p>Hola {{ customer.firstname ?: customer.name }},</p><p>Bienvenido a {{ shop.name }}. Gracias por crear tu cuenta.</p><p><a href="{{ shop.url }}">Visitar la tienda</a></p>';
        }

        if ($eventName === $newsletterRegisteredEvent) {
            return '<p>Hola {{ customer.firstname ?: customer.name ?: "suscriptor" }},</p><p>Gracias por suscribirte al newsletter de {{ shop.name }}.</p><p><a href="{{ shop.url }}">Visitar la tienda</a></p>';
        }

        if ($eventName === $cartAbandonedEvent) {
            return '<p>Hola {{ customer.firstname ?: customer.name }},</p><p>Guardamos tu carrito en {{ shop.name }}.</p>{% if cart.items is not empty %}<ul>{% for product in cart.items %}<li>{{ product.name }} x{{ product.quantity }}</li>{% endfor %}</ul>{% endif %}<p><a href="{{ cart.recovery_url }}">Retomar mi compra</a></p>';
        }

        return '<p>Hola {{ customer.firstname ?: customer.name }},</p><p>Tu pedido {{ order.reference }} cambio al estado: <strong>{{ order.status }}</strong>.</p><p>Total: {{ order.totals.paid }}</p><p><a href="{{ shop.url }}">Visitar la tienda</a></p>';
    }

    public function getDefaultTextContent(string $eventName, string $orderCreatedEvent, string $customerRegisteredEvent, string $newsletterRegisteredEvent, string $cartAbandonedEvent): string
    {
        if ($eventName === $orderCreatedEvent) {
            return "Hola {{ customer.firstname ?: customer.name }},\n\nHemos creado tu pedido {{ order.reference }} en {{ shop.name }}.\nTotal: {{ order.totals.paid }}\n\n{{ shop.url }}";
        }

        if ($eventName === $customerRegisteredEvent) {
            return "Hola {{ customer.firstname ?: customer.name }},\n\nBienvenido a {{ shop.name }}. Gracias por crear tu cuenta.\n\n{{ shop.url }}";
        }

        if ($eventName === $newsletterRegisteredEvent) {
            return "Hola {{ customer.firstname ?: customer.name ?: 'suscriptor' }},\n\nGracias por suscribirte al newsletter de {{ shop.name }}.\n\n{{ shop.url }}";
        }

        if ($eventName === $cartAbandonedEvent) {
            return "Hola {{ customer.firstname ?: customer.name }},\n\nGuardamos tu carrito en {{ shop.name }}.\n{% for product in cart.items %}- {{ product.name }} x{{ product.quantity }}\n{% endfor %}\nRetoma tu compra aqui:\n{{ cart.recovery_url }}";
        }

        return "Hola {{ customer.firstname ?: customer.name }},\n\nTu pedido {{ order.reference }} cambio al estado: {{ order.status }}.\nTotal: {{ order.totals.paid }}\n\n{{ shop.url }}";
    }

    public function generateTextContentFromHtml(string $htmlContent): string
    {
        $text = preg_replace('/<\s*br\s*\/?>/i', "\n", $htmlContent) ?: $htmlContent;
        $text = preg_replace('/<\s*\/p\s*>/i', "\n\n", $text) ?: $text;
        $text = strip_tags($text);
        $text = html_entity_decode($text, ENT_QUOTES, 'UTF-8');
        $text = preg_replace("/\r\n|\r/", "\n", $text) ?: $text;
        $text = preg_replace("/\n{3,}/", "\n\n", $text) ?: $text;

        $lines = array_map('trim', explode("\n", $text));
        $lines = array_filter($lines, static function ($line) {
            return $line !== '';
        });

        return trim(implode("\n", $lines));
    }
}
