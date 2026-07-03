<?php

namespace Velox\MailSendVx\Service\Event;

use Velox\MailSendVx\ModuleConstants;
use Velox\MailSendVx\Service\ContextBuilder\CartTemplateContextBuilder;
use Velox\MailSendVx\Service\ContextBuilder\CustomerTemplateContextBuilder;
use Velox\MailSendVx\Service\ContextBuilder\NewsletterTemplateContextBuilder;
use Velox\MailSendVx\Service\ContextBuilder\OrderTemplateContextBuilder;

class EventTemplateContextService
{
    /**
     * @var OrderTemplateContextBuilder
     */
    private $orderBuilder;

    /**
     * @var CustomerTemplateContextBuilder
     */
    private $customerBuilder;

    /**
     * @var NewsletterTemplateContextBuilder
     */
    private $newsletterBuilder;

    /**
     * @var CartTemplateContextBuilder
     */
    private $cartBuilder;

    public function __construct(
        OrderTemplateContextBuilder $orderBuilder,
        CustomerTemplateContextBuilder $customerBuilder,
        NewsletterTemplateContextBuilder $newsletterBuilder,
        CartTemplateContextBuilder $cartBuilder
    ) {
        $this->orderBuilder = $orderBuilder;
        $this->customerBuilder = $customerBuilder;
        $this->newsletterBuilder = $newsletterBuilder;
        $this->cartBuilder = $cartBuilder;
    }

    /**
     * @param array<string, mixed> $params
     *
     * @return array<string, mixed>
     */
    public function buildOrderStatusContext(array $params): array
    {
        return $this->orderBuilder->buildHookContext(ModuleConstants::EVENT_ORDER_STATUS_CHANGED, $params);
    }

    /**
     * @param array<string, mixed> $params
     *
     * @return array<string, mixed>
     */
    public function buildOrderCreatedContext(array $params): array
    {
        return $this->orderBuilder->buildHookContext(ModuleConstants::EVENT_ORDER_CREATED, $params);
    }

    /**
     * @param array<string, mixed> $params
     *
     * @return array<string, mixed>
     */
    public function buildCustomerRegisteredContext(array $params): array
    {
        return $this->customerBuilder->buildHookContext(ModuleConstants::EVENT_CUSTOMER_REGISTERED, $params);
    }

    /**
     * @param array<string, mixed> $params
     *
     * @return array<string, mixed>
     */
    public function buildNewsletterRegisteredContext(array $params): array
    {
        return $this->newsletterBuilder->buildHookContext(ModuleConstants::EVENT_NEWSLETTER_REGISTERED, $params);
    }

    /**
     * @param array<string, mixed> $params
     *
     * @return array<string, mixed>
     */
    public function buildCartAbandonedContext(array $params): array
    {
        return $this->cartBuilder->buildHookContext(ModuleConstants::EVENT_CART_ABANDONED, $params);
    }

    /**
     * @return array<string, mixed>
     */
    public function getSampleContext(string $eventName): array
    {
        if ($this->orderBuilder->supportsEvent($eventName)) {
            return $this->orderBuilder->buildSampleContext($eventName);
        }

        if ($this->customerBuilder->supportsEvent($eventName)) {
            return $this->customerBuilder->buildSampleContext($eventName);
        }

        if ($this->newsletterBuilder->supportsEvent($eventName)) {
            return $this->newsletterBuilder->buildSampleContext($eventName);
        }

        if ($this->cartBuilder->supportsEvent($eventName)) {
            return $this->cartBuilder->buildSampleContext($eventName);
        }

        return [];
    }

    /**
     * @return array<string, mixed>
     */
    public function getSampleContextForContextType(string $contextType, ?string $eventName = null): array
    {
        $resolvedEventName = $eventName ?: ModuleConstants::getDefaultEventForContext($contextType);
        if (!$resolvedEventName) {
            return [];
        }

        switch ($contextType) {
            case ModuleConstants::CONTEXT_ORDER:
                return $this->orderBuilder->buildSampleContext($resolvedEventName);
            case ModuleConstants::CONTEXT_CART:
                return $this->cartBuilder->buildSampleContext($resolvedEventName);
            case ModuleConstants::CONTEXT_CUSTOMER:
                return $this->customerBuilder->buildSampleContext($resolvedEventName);
            case ModuleConstants::CONTEXT_NEWSLETTER:
                return $this->newsletterBuilder->buildSampleContext($resolvedEventName);
            default:
                return [];
        }
    }
}
