<?php

namespace Velox\MailSendVx\Service\ContextBuilder;

use Context;
use Velox\MailSendVx\ModuleConstants;

class NewsletterTemplateContextBuilder implements DomainTemplateContextBuilderInterface
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
        return $eventName === ModuleConstants::EVENT_NEWSLETTER_REGISTERED;
    }

    public function buildHookContext(string $eventName, array $params): array
    {
        $idLang = (int) $this->context->language->id;
        $idShop = (int) $this->context->shop->id;
        $email = isset($params['email']) ? (string) $params['email'] : '';
        $action = isset($params['action']) ? (string) $params['action'] : '';

        return (new TemplateContextPayloadBuilder())
            ->withEvent($this->eventSegmentBuilder->build(ModuleConstants::EVENT_NEWSLETTER_REGISTERED, [
                'newsletter_action' => $action,
            ]))
            ->withShop($this->shopSegmentBuilder->build($idShop, $idLang))
            ->withCustomer($this->customerSegmentBuilder->build(null, [
                'email' => $email,
            ]))
            ->build();
    }

    public function buildSampleContext(string $eventName): array
    {
        $fixturePaths = [
            dirname(__DIR__, 2) . '/.agents/fixtures/subscriber.json',
            dirname(__DIR__, 2) . '/.agents/fixtures/suscriber.json',
        ];

        foreach ($fixturePaths as $fixturePath) {
            if (!is_file($fixturePath)) {
                continue;
            }

            $decoded = json_decode((string) file_get_contents($fixturePath), true);
            if (is_array($decoded)) {
                $decoded['event']['name'] = $eventName;
                $decoded['event']['newsletter_action'] = (string) ($decoded['event']['newsletter_action'] ?? 'subscribe');
                $decoded['shop']['id'] = (int) ($decoded['shop']['id'] ?? $this->context->shop->id);
                $decoded['shop']['id_lang'] = (int) ($decoded['shop']['id_lang'] ?? $this->context->language->id);
                $decoded['shop']['name'] = (string) ($decoded['shop']['name'] ?? $this->context->shop->name);
                $decoded['shop']['url'] = (string) ($decoded['shop']['url'] ?? $this->context->link->getBaseLink((int) $decoded['shop']['id'], true));

                return $decoded;
            }
        }

        return (new TemplateContextPayloadBuilder())
            ->withEvent($this->eventSegmentBuilder->build($eventName, [
                'newsletter_action' => 'subscribe',
            ]))
            ->withShop($this->shopSegmentBuilder->build((int) $this->context->shop->id, (int) $this->context->language->id))
            ->withCustomer($this->customerSegmentBuilder->build(null, [
                'id' => 10,
                'name' => 'Jonathan Salazar',
                'firstname' => 'Jonathan',
                'lastname' => 'Salazar',
                'email' => 'jonathan@velox.ec',
            ]))
            ->build();
    }
}
