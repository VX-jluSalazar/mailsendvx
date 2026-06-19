<?php

namespace Velox\MailSendVx\Service;

class TemplateContentService
{
    public function getDefaultHtmlContent(string $eventName, string $orderCreatedEvent, string $customerRegisteredEvent, string $newsletterRegisteredEvent): string
    {
        if ($eventName === $orderCreatedEvent) {
            return '<p>Hola {customer_name},</p><p>Hemos creado tu pedido <strong>{order_reference}</strong> en {shop_name}.</p><p>Total: {order_total}</p><p><a href="{shop_url}">Visitar la tienda</a></p>';
        }

        if ($eventName === $customerRegisteredEvent) {
            return '<p>Hola {customer_name},</p><p>Bienvenido a {shop_name}. Gracias por crear tu cuenta.</p><p><a href="{shop_url}">Visitar la tienda</a></p>';
        }

        if ($eventName === $newsletterRegisteredEvent) {
            return '<p>Hola,</p><p>Gracias por suscribirte al newsletter de {shop_name}.</p><p><a href="{shop_url}">Visitar la tienda</a></p>';
        }

        return '<p>Hola {customer_name},</p><p>Tu pedido {order_reference} cambio al estado: <strong>{order_status}</strong>.</p><p>Total: {order_total}</p><p><a href="{shop_url}">Visitar la tienda</a></p>';
    }

    public function getDefaultTextContent(string $eventName, string $orderCreatedEvent, string $customerRegisteredEvent, string $newsletterRegisteredEvent): string
    {
        if ($eventName === $orderCreatedEvent) {
            return "Hola {customer_name},\n\nHemos creado tu pedido {order_reference} en {shop_name}.\nTotal: {order_total}\n\n{shop_url}";
        }

        if ($eventName === $customerRegisteredEvent) {
            return "Hola {customer_name},\n\nBienvenido a {shop_name}. Gracias por crear tu cuenta.\n\n{shop_url}";
        }

        if ($eventName === $newsletterRegisteredEvent) {
            return "Hola,\n\nGracias por suscribirte al newsletter de {shop_name}.\n\n{shop_url}";
        }

        return "Hola {customer_name},\n\nTu pedido {order_reference} cambio al estado: {order_status}.\nTotal: {order_total}\n\n{shop_url}";
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
