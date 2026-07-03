<?php

namespace Velox\MailSendVx;

final class ModuleConstants
{
    public const EVENT_ORDER_CREATED = 'order_created';
    public const EVENT_ORDER_STATUS_CHANGED = 'order_status_changed';
    public const EVENT_ORDER_STATUS_LEGACY = 'order_status_updated';
    public const EVENT_CUSTOMER_REGISTERED = 'customer_registered';
    public const EVENT_NEWSLETTER_REGISTERED = 'newsletter_registered';
    public const EVENT_CART_ABANDONED = 'cart_abandoned';

    public const CONTEXT_ORDER = 'order';
    public const CONTEXT_CART = 'cart';
    public const CONTEXT_CUSTOMER = 'customer';
    public const CONTEXT_NEWSLETTER = 'newsletter';

    public const CONFIG_ENABLED = 'MAILSENDVX_ENABLED';
    public const CONFIG_PROVIDER = 'MAILSENDVX_PROVIDER';
    public const CONFIG_DEBUG = 'MAILSENDVX_DEBUG';
    public const CONFIG_CRON_TOKEN = 'MAILSENDVX_CRON_TOKEN';
    public const CONFIG_ABANDONED_CART_ENABLED = 'MAILSENDVX_ABANDONED_CART_ENABLED';
    public const CONFIG_ABANDONED_CART_DELAY_VALUE = 'MAILSENDVX_ABANDONED_CART_DELAY_VALUE';
    public const CONFIG_ABANDONED_CART_DELAY_UNIT = 'MAILSENDVX_ABANDONED_CART_DELAY_UNIT';
    public const CONFIG_ABANDONED_CART_REQUIRE_CUSTOMER = 'MAILSENDVX_ABANDONED_CART_REQUIRE_CUSTOMER';
    public const CONFIG_ABANDONED_CART_REQUIRE_PRODUCTS = 'MAILSENDVX_ABANDONED_CART_REQUIRE_PRODUCTS';
    public const CONFIG_ABANDONED_CART_CRON_BATCH_SIZE = 'MAILSENDVX_ABANDONED_CART_CRON_BATCH_SIZE';

    public const ADMIN_PARENT_TAB_CLASS = 'AdminMailsendvx';
    public const ADMIN_CONFIGURE_TAB_CLASS = 'AdminMailsendvxConfigure';
    public const ADMIN_TEMPLATES_TAB_CLASS = 'AdminMailsendvxTemplates';
    public const ADMIN_WRAPPERS_TAB_CLASS = 'AdminMailsendvxWrappers';
    public const ADMIN_DASHBOARD_TAB_CLASS = 'AdminMailsendvxDashboard';
    public const ADMIN_DOCUMENTATION_TAB_CLASS = 'AdminMailsendvxDocumentation';
    public const ADMIN_CONFIGURE_SECTION_CLASS = 'CONFIGURE';

    /**
     * @return string[]
     */
    public static function getContextTypes(): array
    {
        return [
            self::CONTEXT_ORDER,
            self::CONTEXT_CART,
            self::CONTEXT_CUSTOMER,
            self::CONTEXT_NEWSLETTER,
        ];
    }

    /**
     * @return array<string, string[]>
     */
    public static function getSupportedEventsByContext(): array
    {
        return [
            self::CONTEXT_ORDER => [
                self::EVENT_ORDER_CREATED,
                self::EVENT_ORDER_STATUS_CHANGED,
                self::EVENT_ORDER_STATUS_LEGACY,
            ],
            self::CONTEXT_CART => [
                self::EVENT_CART_ABANDONED,
            ],
            self::CONTEXT_CUSTOMER => [
                self::EVENT_CUSTOMER_REGISTERED,
            ],
            self::CONTEXT_NEWSLETTER => [
                self::EVENT_NEWSLETTER_REGISTERED,
            ],
        ];
    }

    public static function isSupportedContextType(string $contextType): bool
    {
        return in_array($contextType, self::getContextTypes(), true);
    }

    public static function getEventContextType(?string $eventName): ?string
    {
        $eventName = (string) $eventName;
        if ($eventName === '') {
            return null;
        }

        if ($eventName === self::EVENT_ORDER_STATUS_CHANGED
            || $eventName === self::EVENT_ORDER_STATUS_LEGACY
            || strpos($eventName, self::EVENT_ORDER_STATUS_CHANGED . '_') === 0
        ) {
            return self::CONTEXT_ORDER;
        }

        foreach (self::getSupportedEventsByContext() as $contextType => $events) {
            if (in_array($eventName, $events, true)) {
                return $contextType;
            }
        }

        return null;
    }

    public static function getDefaultEventForContext(string $contextType): ?string
    {
        switch ($contextType) {
            case self::CONTEXT_ORDER:
                return self::EVENT_ORDER_CREATED;
            case self::CONTEXT_CART:
                return self::EVENT_CART_ABANDONED;
            case self::CONTEXT_CUSTOMER:
                return self::EVENT_CUSTOMER_REGISTERED;
            case self::CONTEXT_NEWSLETTER:
                return self::EVENT_NEWSLETTER_REGISTERED;
            default:
                return null;
        }
    }
}
