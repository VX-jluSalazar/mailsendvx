<?php

namespace Velox\MailSendVx\Service;

use Context;
use Customer;
use Validate;
use Velox\MailSendVx\ModuleConstants;

class CustomerTemplateContextBuilder implements DomainTemplateContextBuilderInterface
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
        return $eventName === ModuleConstants::EVENT_CUSTOMER_REGISTERED;
    }

    public function buildHookContext(string $eventName, array $params): array
    {
        $customer = $params['newCustomer'] ?? null;
        $idLang = $customer instanceof Customer && Validate::isLoadedObject($customer) && $customer->id_lang
            ? (int) $customer->id_lang
            : (int) $this->context->language->id;
        $idShop = (int) $this->context->shop->id;

        return [
            'event' => [
                'name' => ModuleConstants::EVENT_CUSTOMER_REGISTERED,
            ],
            'shop' => [
                'id' => $idShop,
                'id_lang' => $idLang,
                'name' => (string) $this->context->shop->name,
                'url' => $this->context->link->getBaseLink((int) $this->context->shop->id, true),
            ],
            'customer' => [
                'id' => $customer instanceof Customer && Validate::isLoadedObject($customer) ? (int) $customer->id : 0,
                'name' => $customer instanceof Customer && Validate::isLoadedObject($customer) ? trim($customer->firstname . ' ' . $customer->lastname) : '',
                'firstname' => $customer instanceof Customer && Validate::isLoadedObject($customer) ? (string) $customer->firstname : '',
                'lastname' => $customer instanceof Customer && Validate::isLoadedObject($customer) ? (string) $customer->lastname : '',
                'email' => $customer instanceof Customer && Validate::isLoadedObject($customer) ? (string) $customer->email : '',
            ],
        ];
    }

    public function buildSampleContext(string $eventName): array
    {
        return [
            'event' => [
                'name' => $eventName,
            ],
            'shop' => [
                'id' => (int) $this->context->shop->id,
                'id_lang' => (int) $this->context->language->id,
                'name' => (string) $this->context->shop->name,
                'url' => $this->context->link->getBaseLink((int) $this->context->shop->id, true),
            ],
            'customer' => [
                'id' => 123,
                'name' => 'Cliente de prueba',
                'firstname' => 'Cliente',
                'lastname' => 'Prueba',
                'email' => 'cliente@example.com',
            ],
        ];
    }
}
