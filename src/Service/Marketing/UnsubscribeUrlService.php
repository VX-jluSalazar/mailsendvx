<?php

namespace Velox\MailSendVx\Service\Marketing;

use Configuration;
use Context;
use Db;
use Tools;
use Validate;

class UnsubscribeUrlService
{
    /**
     * @var Context
     */
    private $context;

    public function __construct(Context $context)
    {
        $this->context = $context;
    }

    public function generateUrl(string $email, int $idShop): string
    {
        $email = trim($email);
        if ($email === '' || !Validate::isEmail($email)) {
            return '';
        }

        return $this->context->link->getModuleLink('mailsendvx', 'unsubscribe', [
            'email' => $email,
            'token' => $this->generateToken($email, $idShop),
            'id_shop' => $idShop,
        ], true);
    }

    /**
     * @return array{success: bool, message: string}
     */
    public function unsubscribeByToken(string $email, string $token, int $idShop): array
    {
        $email = trim($email);
        if ($email === '' || !Validate::isEmail($email) || !$this->isValidToken($email, $token, $idShop)) {
            return [
                'success' => false,
                'message' => 'El enlace de desuscripcion no es valido o ya expiro.',
            ];
        }

        $db = Db::getInstance();
        $guestResult = $db->delete('emailsubscription', 'email = \'' . pSQL($email) . '\' AND id_shop = ' . (int) $idShop);
        $customerResult = $db->update('customer', [
            'newsletter' => 0,
        ], 'email = \'' . pSQL($email) . '\' AND id_shop = ' . (int) $idShop);

        if (!$guestResult || !$customerResult) {
            return [
                'success' => false,
                'message' => 'No fue posible procesar la desuscripcion en este momento.',
            ];
        }

        return [
            'success' => true,
            'message' => 'Tu suscripcion fue cancelada correctamente.',
        ];
    }

    public function generateToken(string $email, int $idShop): string
    {
        return hash_hmac('sha256', $this->normalizeEmail($email) . '|' . $idShop, $this->getSecret());
    }

    private function isValidToken(string $email, string $token, int $idShop): bool
    {
        $expectedToken = $this->generateToken($email, $idShop);

        return $token !== '' && hash_equals($expectedToken, $token);
    }

    private function normalizeEmail(string $email): string
    {
        return Tools::strtolower(trim($email));
    }

    private function getSecret(): string
    {
        $secret = (string) Configuration::get('NW_SALT');

        return $secret !== '' ? $secret : (string) _COOKIE_KEY_;
    }
}
