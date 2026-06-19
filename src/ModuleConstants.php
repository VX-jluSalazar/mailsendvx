<?php

namespace Velox\MailSendVx;

final class ModuleConstants
{
    public const EVENT_ORDER_CREATED = 'order_created';
    public const EVENT_ORDER_STATUS_CHANGED = 'order_status_changed';
    public const EVENT_ORDER_STATUS_LEGACY = 'order_status_updated';
    public const EVENT_CUSTOMER_REGISTERED = 'customer_registered';
    public const EVENT_NEWSLETTER_REGISTERED = 'newsletter_registered';

    public const CONFIG_ENABLED = 'MAILSENDVX_ENABLED';
    public const CONFIG_PROVIDER = 'MAILSENDVX_PROVIDER';
    public const CONFIG_DEBUG = 'MAILSENDVX_DEBUG';
    public const CONFIG_CRON_TOKEN = 'MAILSENDVX_CRON_TOKEN';

    public const ADMIN_PARENT_TAB_CLASS = 'AdminMailsendvx';
    public const ADMIN_CONFIGURE_TAB_CLASS = 'AdminMailsendvxConfigure';
    public const ADMIN_TEMPLATES_TAB_CLASS = 'AdminMailsendvxTemplates';
    public const ADMIN_DASHBOARD_TAB_CLASS = 'AdminMailsendvxDashboard';
    public const ADMIN_CONFIGURE_SECTION_CLASS = 'CONFIGURE';
}
