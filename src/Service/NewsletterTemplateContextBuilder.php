<?php

namespace Velox\MailSendVx\Service;

use Context;
use Velox\MailSendVx\ModuleConstants;

class NewsletterTemplateContextBuilder implements DomainTemplateContextBuilderInterface
{
    /**
     * @var Context
     */
    private $context;

    public function __construct(Context $context)
    {
        $this->context = $context;
    }

    public function supportsEvent(string $eventName): bool
    {
        return $eventName === ModuleConstants::EVENT_NEWSLETTER_REGISTERED;
    }

    public function buildHookContext(string $eventName, array $params): array
    {
        return [
            'event_name' => ModuleConstants::EVENT_NEWSLETTER_REGISTERED,
            'id_lang' => (int) $this->context->language->id,
            'id_shop' => (int) $this->context->shop->id,
            'customer_name' => '',
            'customer_email' => isset($params['email']) ? (string) $params['email'] : '',
            'newsletter_action' => isset($params['action']) ? (string) $params['action'] : '',
            'shop_name' => (string) $this->context->shop->name,
            'shop_url' => $this->context->link->getBaseLink((int) $this->context->shop->id, true),
        ];
    }

    public function buildSampleContext(string $eventName): array
    {
        return [
            'event_name' => $eventName,
            'id_lang' => (int) $this->context->language->id,
            'id_shop' => (int) $this->context->shop->id,
            'customer_name' => '',
            'customer_email' => 'cliente@example.com',
            'newsletter_action' => 'subscribe',
            'shop_name' => (string) $this->context->shop->name,
            'shop_url' => $this->context->link->getBaseLink((int) $this->context->shop->id, true),
        ];
    }
}
