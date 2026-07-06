<?php

namespace Velox\MailSendVx\Service\ContextBuilder;

use Configuration;
use Context;
use Shop;
use Validate;
use Velox\MailSendVx\Service\Marketing\UnsubscribeUrlService;

class ShopContextSegmentBuilder
{
    /**
     * @var Context
     */
    private $context;

    /**
     * @var UnsubscribeUrlService
     */
    private $unsubscribeUrlService;

    public function __construct(Context $context, UnsubscribeUrlService $unsubscribeUrlService)
    {
        $this->context = $context;
        $this->unsubscribeUrlService = $unsubscribeUrlService;
    }

    /**
     * @param array<string, mixed> $extra
     *
     * @return array<string, mixed>
     */
    public function build(int $idShop, int $idLang, array $extra = []): array
    {
        $unsubscribeEmail = trim((string) ($extra['unsubscribe_email'] ?? ''));
        unset($extra['unsubscribe_email']);

        $unsubscribeUrl = isset($extra['unsubscribe_url']) ? (string) $extra['unsubscribe_url'] : '';
        if ($unsubscribeUrl === '' && $unsubscribeEmail !== '') {
            $unsubscribeUrl = $this->unsubscribeUrlService->generateUrl($unsubscribeEmail, $idShop);
        }

        return array_merge([
            'id' => $idShop,
            'id_lang' => $idLang,
            'name' => $this->getShopName($idShop),
            'url' => $this->context->link->getBaseLink($idShop, true),
            'unsubscribe_url' => $unsubscribeUrl,
        ], $extra);
    }

    private function getShopName(int $idShop): string
    {
        $shop = new Shop($idShop);

        return Validate::isLoadedObject($shop) ? (string) $shop->name : (string) Configuration::get('PS_SHOP_NAME');
    }
}
