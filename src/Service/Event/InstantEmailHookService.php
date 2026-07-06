<?php

namespace Velox\MailSendVx\Service\Event;

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
use Velox\MailSendVx\Service\Flow\FlowSchedulerService;
use Velox\MailSendVx\Service\Flow\FlowWorkerService;
use Velox\MailSendVx\Service\Mail\MailSendVxMailer;

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

    /**
     * @var FlowSchedulerService
     */
    private $flowScheduler;

    /**
     * @var FlowWorkerService
     */
    private $flowWorker;

    public function __construct(
        Context $context,
        EventTemplateContextService $eventContextService,
        OrderStateEventService $orderStateEventService,
        MailSendVxTemplateRepository $templateRepository,
        MailSendVxEventRepository $eventRepository,
        MailSendVxLogRepository $logRepository,
        MailSendVxMailer $mailer,
        FlowSchedulerService $flowScheduler,
        FlowWorkerService $flowWorker
    )
    {
        $this->context = $context;
        $this->eventContextService = $eventContextService;
        $this->orderStateEventService = $orderStateEventService;
        $this->templateRepository = $templateRepository;
        $this->eventRepository = $eventRepository;
        $this->logRepository = $logRepository;
        $this->mailer = $mailer;
        $this->flowScheduler = $flowScheduler;
        $this->flowWorker = $flowWorker;
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
            $this->getCustomerEmail($variables),
            $this->getCustomerName($variables),
            $this->getShopLanguageId($variables),
            $this->getShopId($variables),
            'order',
            $variables['order']['id'] ?? null,
            $module
        );
    }

    public function handleCustomerAccountAdd(array $params, Module $module): void
    {
        $variables = $this->eventContextService->buildCustomerRegisteredContext($params);
        $this->sendInstantEmail(
            ModuleConstants::EVENT_CUSTOMER_REGISTERED,
            $variables,
            $this->getCustomerEmail($variables),
            $this->getCustomerName($variables),
            $this->getShopLanguageId($variables),
            $this->getShopId($variables),
            'customer',
            $variables['customer']['id'] ?? null,
            $module
        );
    }

    public function handleNewsletterRegistrationAfter(array $params, Module $module): void
    {
        $variables = $this->eventContextService->buildNewsletterRegisteredContext($params);
        $this->sendInstantEmail(
            ModuleConstants::EVENT_NEWSLETTER_REGISTERED,
            $variables,
            $this->getCustomerEmail($variables),
            $this->getCustomerName($variables),
            $this->getShopLanguageId($variables),
            $this->getShopId($variables),
            'newsletter',
            $this->getCustomerEmail($variables),
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
            if (isset($eventVariables['event']) && is_array($eventVariables['event'])) {
                $eventVariables['event']['name'] = $eventName;
            }
            $this->sendInstantEmail(
                $eventName,
                $eventVariables,
                $this->getCustomerEmail($eventVariables),
                $this->getCustomerName($eventVariables),
                $this->getShopLanguageId($eventVariables),
                $this->getShopId($eventVariables),
                'order',
                $eventVariables['order']['id'] ?? null,
                $module
            );
        }
    }

    /**
     * @param array<string, mixed> $variables
     */
    private function getCustomerEmail(array $variables): ?string
    {
        $email = $variables['customer']['email'] ?? null;

        return is_string($email) && $email !== '' ? $email : null;
    }

    /**
     * @param array<string, mixed> $variables
     */
    private function getCustomerName(array $variables): ?string
    {
        $name = $variables['customer']['name'] ?? null;

        return is_string($name) && $name !== '' ? $name : null;
    }

    /**
     * @param array<string, mixed> $variables
     */
    private function getShopId(array $variables): ?int
    {
        $id = $variables['shop']['id'] ?? null;

        return is_numeric($id) ? (int) $id : null;
    }

    /**
     * @param array<string, mixed> $variables
     */
    private function getShopLanguageId(array $variables): ?int
    {
        $idLang = $variables['shop']['id_lang'] ?? null;

        return is_numeric($idLang) ? (int) $idLang : null;
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

            $scheduleResult = $this->flowScheduler->scheduleEvent($eventName, $variables, $idShop);
            $hasInstantTemplate = $this->templateRepository->hasActiveByEvent($eventName, $idLang, $idShop);

            if (!$recipient || !Validate::isEmail($recipient)) {
                $this->triggerFlowWorkerIfNeeded($scheduleResult, $module);
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

            if ($hasInstantTemplate) {
                $this->mailer->sendEvent(
                    $eventName,
                    $recipient,
                    $recipientName,
                    $variables,
                    $idLang,
                    $idShop
                );
            } elseif (($scheduleResult['scheduled'] ?? 0) <= 0) {
                $this->logRepository->add(
                    $eventName,
                    'skipped',
                    $recipient,
                    null,
                    null,
                    $variables,
                    'No active instant template or flow matched this event.',
                    $idShop
                );
            }

            $this->triggerFlowWorkerIfNeeded($scheduleResult, $module);
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

    /**
     * @param array<string, int> $scheduleResult
     */
    private function triggerFlowWorkerIfNeeded(array $scheduleResult, Module $module): void
    {
        if ((int) ($scheduleResult['scheduled'] ?? 0) <= 0) {
            return;
        }

        try {
            $this->flowWorker->processDueJobs(20);
        } catch (\Throwable $exception) {
            PrestaShopLogger::addLog(
                sprintf('Mail Send VX flow worker trigger failed: %s', $exception->getMessage()),
                2,
                null,
                'Module',
                (int) $module->id,
                true
            );
        }
    }

}
