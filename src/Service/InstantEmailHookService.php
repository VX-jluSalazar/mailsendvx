<?php

namespace Velox\MailSendVx\Service;

use Configuration;
use Context;
use Currency;
use Module;
use PrestaShopLogger;
use Validate;
use Velox\MailSendVx\ModuleConstants;
use Velox\MailSendVx\Repository\MailSendVxEventRepository;
use Velox\MailSendVx\Repository\MailSendVxLogRepository;
use Velox\MailSendVx\Repository\MailSendVxTemplateRepository;

class InstantEmailHookService
{
    /**
     * @var Context
     */
    private $context;

    /**
     * @var EventTemplateContextService
     */
    private $eventContextService;

    /**
     * @var OrderStateEventService
     */
    private $orderStateEventService;

    /**
     * @var MailSendVxTemplateRepository
     */
    private $templateRepository;

    /**
     * @var MailSendVxEventRepository
     */
    private $eventRepository;

    /**
     * @var MailSendVxLogRepository
     */
    private $logRepository;

    /**
     * @var MailSendVxMailer
     */
    private $mailer;

    public function __construct(
        Context $context,
        EventTemplateContextService $eventContextService,
        OrderStateEventService $orderStateEventService,
        MailSendVxTemplateRepository $templateRepository,
        MailSendVxEventRepository $eventRepository,
        MailSendVxLogRepository $logRepository,
        MailSendVxMailer $mailer
    )
    {
        $this->context = $context;
        $this->eventContextService = $eventContextService;
        $this->orderStateEventService = $orderStateEventService;
        $this->templateRepository = $templateRepository;
        $this->eventRepository = $eventRepository;
        $this->logRepository = $logRepository;
        $this->mailer = $mailer;
    }

    public function handleOrderStatusPostUpdate(array $params, Module $module): void
    {
        $variables = $this->eventContextService->buildOrderStatusContext($params);
        $this->dispatchOrderStatusEmails($variables, $module);
    }

    public function handleValidateOrder(array $params, Module $module): void
    {
        $variables = $this->eventContextService->buildOrderCreatedContext($params);
        $this->sendInstantEmail(
            ModuleConstants::EVENT_ORDER_CREATED,
            $variables,
            $variables['customer_email'] ?? null,
            $variables['customer_name'] ?? null,
            $variables['id_lang'] ?? null,
            $variables['id_shop'] ?? null,
            'order',
            $variables['order_id'] ?? null,
            $module
        );
    }

    public function handleCustomerAccountAdd(array $params, Module $module): void
    {
        $variables = $this->eventContextService->buildCustomerRegisteredContext($params);
        $this->sendInstantEmail(
            ModuleConstants::EVENT_CUSTOMER_REGISTERED,
            $variables,
            $variables['customer_email'] ?? null,
            $variables['customer_name'] ?? null,
            $variables['id_lang'] ?? null,
            $variables['id_shop'] ?? null,
            'customer',
            $variables['customer_id'] ?? null,
            $module
        );
    }

    public function handleNewsletterRegistrationAfter(array $params, Module $module): void
    {
        $variables = $this->eventContextService->buildNewsletterRegisteredContext($params);
        $this->sendInstantEmail(
            ModuleConstants::EVENT_NEWSLETTER_REGISTERED,
            $variables,
            $variables['customer_email'] ?? null,
            $variables['customer_name'] ?? null,
            $variables['id_lang'] ?? null,
            $variables['id_shop'] ?? null,
            'newsletter',
            $variables['customer_email'] ?? null,
            $module
        );
    }

    /**
     * @param array<string, mixed> $variables
     */
    private function dispatchOrderStatusEmails(array $variables, Module $module): void
    {
        $eventNames = $this->orderStateEventService->buildDispatchEventNames(
            $variables,
            $this->templateRepository,
            ModuleConstants::EVENT_ORDER_STATUS_CHANGED,
            ModuleConstants::EVENT_ORDER_STATUS_LEGACY
        );

        foreach (array_unique($eventNames) as $eventName) {
            $eventVariables = $variables;
            $eventVariables['event_name'] = $eventName;
            $this->sendInstantEmail(
                $eventName,
                $eventVariables,
                $eventVariables['customer_email'] ?? null,
                $eventVariables['customer_name'] ?? null,
                $eventVariables['id_lang'] ?? null,
                $eventVariables['id_shop'] ?? null,
                'order',
                $eventVariables['order_id'] ?? null,
                $module
            );
        }
    }

    /**
     * @param array<string, mixed> $variables
     * @param int|string|null $objectId
     */
    private function sendInstantEmail(
        string $eventName,
        array $variables,
        ?string $recipient,
        ?string $recipientName,
        $idLang,
        $idShop,
        ?string $objectType,
        $objectId,
        Module $module
    ): void {
        if (!(bool) Configuration::get(ModuleConstants::CONFIG_ENABLED)) {
            return;
        }

        $idLang = (int) ($idLang ?: $this->context->language->id);
        $idShop = (int) ($idShop ?: $this->context->shop->id);

        try {
            $this->eventRepository->add(
                $eventName,
                $variables,
                $objectType,
                $objectId !== null ? (string) $objectId : null,
                'captured',
                $idShop
            );

            if (!$recipient || !Validate::isEmail($recipient)) {
                $this->logRepository->add(
                    $eventName,
                    'skipped',
                    $recipient,
                    null,
                    null,
                    $variables,
                    'No valid recipient found.',
                    $idShop
                );

                return;
            }

            $this->mailer->sendEvent(
                $eventName,
                $recipient,
                $recipientName,
                $variables,
                $idLang,
                $idShop
            );
        } catch (\Throwable $exception) {
            PrestaShopLogger::addLog(
                sprintf('Mail Send VX instant email failed: %s', $exception->getMessage()),
                3,
                null,
                'Module',
                (int) $module->id,
                true
            );
        }
    }

}
