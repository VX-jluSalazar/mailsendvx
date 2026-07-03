<?php

namespace Velox\MailSendVx\Service\ContextBuilder;

use Configuration;
use Context;
use Shop;
use Validate;

class ShopContextSegmentBuilder
{
    /**
     * @var Context
     */
    private $context;

    public function __construct(Context $context)
    {
        $this->context = $context;
    }

    /**
     * @param array<string, mixed> $extra
     *
     * @return array<string, mixed>
     */
    public function build(int $idShop, int $idLang, array $extra = []): array
    {
        return array_merge([
            'id' => $idShop,
            'id_lang' => $idLang,
            'name' => $this->getShopName($idShop),
            'url' => $this->context->link->getBaseLink($idShop, true),
        ], $extra);
    }

    private function getShopName(int $idShop): string
    {
        $shop = new Shop($idShop);

        return Validate::isLoadedObject($shop) ? (string) $shop->name : (string) Configuration::get('PS_SHOP_NAME');
    }
}
