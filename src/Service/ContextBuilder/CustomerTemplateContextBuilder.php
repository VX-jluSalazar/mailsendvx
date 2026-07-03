<?php

namespace Velox\MailSendVx\Service\ContextBuilder;

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

    /**
     * @var EventContextSegmentBuilder
     */
    private $eventSegmentBuilder;

    /**
     * @var ShopContextSegmentBuilder
     */
    private $shopSegmentBuilder;

    /**
     * @var CustomerContextSegmentBuilder
     */
    private $customerSegmentBuilder;

    public function __construct(
        Context $context,
        EventContextSegmentBuilder $eventSegmentBuilder,
        ShopContextSegmentBuilder $shopSegmentBuilder,
        CustomerContextSegmentBuilder $customerSegmentBuilder
    ) {
        $this->context = $context;
        $this->eventSegmentBuilder = $eventSegmentBuilder;
        $this->shopSegmentBuilder = $shopSegmentBuilder;
        $this->customerSegmentBuilder = $customerSegmentBuilder;
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

        return (new TemplateContextPayloadBuilder())
            ->withEvent($this->eventSegmentBuilder->build(ModuleConstants::EVENT_CUSTOMER_REGISTERED))
            ->withShop($this->shopSegmentBuilder->build($idShop, $idLang))
            ->withCustomer($this->customerSegmentBuilder->build($customer instanceof Customer ? $customer : null))
            ->build();
    }

    public function buildSampleContext(string $eventName): array
    {
        return (new TemplateContextPayloadBuilder())
            ->withEvent($this->eventSegmentBuilder->build($eventName))
            ->withShop($this->shopSegmentBuilder->build((int) $this->context->shop->id, (int) $this->context->language->id))
            ->withCustomer($this->customerSegmentBuilder->build(null, [
                'id' => 123,
                'name' => 'Cliente de prueba',
                'firstname' => 'Cliente',
                'lastname' => 'Prueba',
                'email' => 'cliente@example.com',
            ]))
            ->build();
    }
}
