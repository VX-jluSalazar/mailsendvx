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
        $idLang = (int) $this->context->language->id;
        $idShop = (int) $this->context->shop->id;
        $email = isset($params['email']) ? (string) $params['email'] : '';
        $action = isset($params['action']) ? (string) $params['action'] : '';

        return [
            'event' => [
                'name' => ModuleConstants::EVENT_NEWSLETTER_REGISTERED,
                'newsletter_action' => $action,
            ],
            'shop' => [
                'id' => $idShop,
                'id_lang' => $idLang,
                'name' => (string) $this->context->shop->name,
                'url' => $this->context->link->getBaseLink($idShop, true),
            ],
            'customer' => [
                'id' => 0,
                'name' => '',
                'firstname' => '',
                'lastname' => '',
                'email' => $email,
            ],
        ];
    }

    public function buildSampleContext(string $eventName): array
    {
        $fixturePath = dirname(__DIR__, 2) . '/.agents/fixtures/suscriber.json';
        $context = [];
        if (is_file($fixturePath)) {
            $decoded = json_decode((string) file_get_contents($fixturePath), true);
            if (is_array($decoded)) {
                $context = $decoded;
            }
        }

        if (empty($context)) {
            $context = [
                'event' => [
                    'name' => $eventName,
                    'newsletter_action' => 'subscribe',
                ],
                'shop' => [
                    'id' => (int) $this->context->shop->id,
                    'id_lang' => (int) $this->context->language->id,
                    'name' => (string) $this->context->shop->name,
                    'url' => $this->context->link->getBaseLink((int) $this->context->shop->id, true),
                ],
                'customer' => [
                    'id' => 10,
                    'name' => 'Jonathan Salazar',
                    'firstname' => 'Jonathan',
                    'lastname' => 'Salazar',
                    'email' => 'jonathan@velox.ec',
                ],
            ];
        }

        $context['event']['name'] = $eventName;
        if (!isset($context['shop']['id'])) {
            $context['shop']['id'] = (int) $this->context->shop->id;
        }
        if (!isset($context['shop']['id_lang'])) {
            $context['shop']['id_lang'] = (int) $this->context->language->id;
        }
        if (!isset($context['shop']['name'])) {
            $context['shop']['name'] = (string) $this->context->shop->name;
        }
        if (!isset($context['shop']['url'])) {
            $context['shop']['url'] = $this->context->link->getBaseLink((int) $this->context->shop->id, true);
        }
        if (!isset($context['event']['newsletter_action'])) {
            $context['event']['newsletter_action'] = 'subscribe';
        }

        return $context;
    }
}
