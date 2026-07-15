<?php

namespace Velox\MailSendVx\Service\Event;

use Velox\MailSendVx\ModuleConstants;
use Velox\MailSendVx\Service\ContextBuilder\CartTemplateContextBuilder;
use Velox\MailSendVx\Service\ContextBuilder\CustomerTemplateContextBuilder;
use Velox\MailSendVx\Service\ContextBuilder\NewsletterTemplateContextBuilder;
use Velox\MailSendVx\Service\ContextBuilder\OrderTemplateContextBuilder;
use Velox\MailSendVx\Service\Theme\ColorPaletteProvider;

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

    /**
     * @var ColorPaletteProvider
     */
    private $colorPaletteProvider;

    public function __construct(
        OrderTemplateContextBuilder $orderBuilder,
        CustomerTemplateContextBuilder $customerBuilder,
        NewsletterTemplateContextBuilder $newsletterBuilder,
        CartTemplateContextBuilder $cartBuilder,
        ColorPaletteProvider $colorPaletteProvider
    ) {
        $this->orderBuilder = $orderBuilder;
        $this->customerBuilder = $customerBuilder;
        $this->newsletterBuilder = $newsletterBuilder;
        $this->cartBuilder = $cartBuilder;
        $this->colorPaletteProvider = $colorPaletteProvider;
    }

    /**
     * @param array<string, mixed> $params
     *
     * @return array<string, mixed>
     */
    public function buildOrderStatusContext(array $params): array
    {
        return $this->withGlobalContext($this->orderBuilder->buildHookContext(ModuleConstants::EVENT_ORDER_STATUS_CHANGED, $params));
    }

    /**
     * @param array<string, mixed> $params
     *
     * @return array<string, mixed>
     */
    public function buildOrderCreatedContext(array $params): array
    {
        return $this->withGlobalContext($this->orderBuilder->buildHookContext(ModuleConstants::EVENT_ORDER_CREATED, $params));
    }

    /**
     * @param array<string, mixed> $params
     *
     * @return array<string, mixed>
     */
    public function buildCustomerRegisteredContext(array $params): array
    {
        return $this->withGlobalContext($this->customerBuilder->buildHookContext(ModuleConstants::EVENT_CUSTOMER_REGISTERED, $params));
    }

    /**
     * @param array<string, mixed> $params
     *
     * @return array<string, mixed>
     */
    public function buildNewsletterRegisteredContext(array $params): array
    {
        return $this->withGlobalContext($this->newsletterBuilder->buildHookContext(ModuleConstants::EVENT_NEWSLETTER_REGISTERED, $params));
    }

    /**
     * @param array<string, mixed> $params
     *
     * @return array<string, mixed>
     */
    public function buildCartAbandonedContext(array $params): array
    {
        return $this->withGlobalContext($this->cartBuilder->buildHookContext(ModuleConstants::EVENT_CART_ABANDONED, $params));
    }

    /**
     * @return array<string, mixed>
     */
    public function getSampleContext(string $eventName): array
    {
        if ($this->orderBuilder->supportsEvent($eventName)) {
            return $this->withGlobalContext($this->orderBuilder->buildSampleContext($eventName));
        }

        if ($this->customerBuilder->supportsEvent($eventName)) {
            return $this->withGlobalContext($this->customerBuilder->buildSampleContext($eventName));
        }

        if ($this->newsletterBuilder->supportsEvent($eventName)) {
            return $this->withGlobalContext($this->newsletterBuilder->buildSampleContext($eventName));
        }

        if ($this->cartBuilder->supportsEvent($eventName)) {
            return $this->withGlobalContext($this->cartBuilder->buildSampleContext($eventName));
        }

        return $this->withGlobalContext([]);
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
                return $this->withGlobalContext($this->orderBuilder->buildSampleContext($resolvedEventName));
            case ModuleConstants::CONTEXT_CART:
                return $this->withGlobalContext($this->cartBuilder->buildSampleContext($resolvedEventName));
            case ModuleConstants::CONTEXT_CUSTOMER:
                return $this->withGlobalContext($this->customerBuilder->buildSampleContext($resolvedEventName));
            case ModuleConstants::CONTEXT_NEWSLETTER:
                return $this->withGlobalContext($this->newsletterBuilder->buildSampleContext($resolvedEventName));
            default:
                return $this->withGlobalContext([]);
        }
    }

    /**
     * @param array<string, mixed> $context
     *
     * @return array<string, mixed>
     */
    private function withGlobalContext(array $context): array
    {
        return array_merge($context, $this->colorPaletteProvider->getTemplateContext());
    }
}
