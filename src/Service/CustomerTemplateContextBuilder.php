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
            'event_name' => ModuleConstants::EVENT_CUSTOMER_REGISTERED,
            'id_lang' => $idLang,
            'id_shop' => $idShop,
            'customer_id' => $customer instanceof Customer && Validate::isLoadedObject($customer) ? (int) $customer->id : '',
            'customer_name' => $customer instanceof Customer && Validate::isLoadedObject($customer) ? trim($customer->firstname . ' ' . $customer->lastname) : '',
            'customer_firstname' => $customer instanceof Customer && Validate::isLoadedObject($customer) ? (string) $customer->firstname : '',
            'customer_lastname' => $customer instanceof Customer && Validate::isLoadedObject($customer) ? (string) $customer->lastname : '',
            'customer_email' => $customer instanceof Customer && Validate::isLoadedObject($customer) ? (string) $customer->email : '',
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
            'customer_id' => 123,
            'customer_name' => 'Cliente de prueba',
            'customer_firstname' => 'Cliente',
            'customer_lastname' => 'Prueba',
            'customer_email' => 'cliente@example.com',
            'shop_name' => (string) $this->context->shop->name,
            'shop_url' => $this->context->link->getBaseLink((int) $this->context->shop->id, true),
        ];
    }
}
