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
            'logo_url' => $this->getShopLogoUrl($idShop),
            'contact_email' => $this->getShopContactEmail($idShop),
            'unsubscribe_url' => $unsubscribeUrl,
        ], $extra);
    }

    private function getShopName(int $idShop): string
    {
        $shop = new Shop($idShop);

        return Validate::isLoadedObject($shop) ? (string) $shop->name : (string) Configuration::get('PS_SHOP_NAME');
    }

    private function getShopContactEmail(int $idShop): string
    {
        return (string) Configuration::get('PS_SHOP_EMAIL', null, null, $idShop);
    }

    private function getShopLogoUrl(int $idShop): string
    {
        $logo = (string) Configuration::get('PS_LOGO_MAIL', null, null, $idShop);
        if ($logo === '') {
            $logo = (string) Configuration::get('PS_LOGO', null, null, $idShop);
        }

        if ($logo === '') {
            return '';
        }

        return rtrim($this->context->link->getBaseLink($idShop, true), '/') . '/' . ltrim(_PS_IMG_, '/') . ltrim($logo, '/');
    }
}
