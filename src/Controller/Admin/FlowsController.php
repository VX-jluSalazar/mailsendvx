<?php

namespace Velox\MailSendVx\Controller\Admin;

use PrestaShop\PrestaShop\Core\Grid\Definition\Factory\GridDefinitionFactoryInterface;
use PrestaShop\PrestaShop\Core\Grid\GridFactoryInterface;
use PrestaShopBundle\Controller\Admin\FrameworkBundleAdminController;
use PrestaShopBundle\Service\Grid\ResponseBuilder;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Velox\MailSendVx\Grid\Definition\Factory\MailSendVxQueueGridDefinitionFactory;
use Velox\MailSendVx\Service\Admin\FlowAdminService;

class FlowsController extends FrameworkBundleAdminController
{
    /**
     * @var FlowAdminService
     */
    private $flowAdminService;

    /**
     * @var GridFactoryInterface
     */
    private $queueGridFactory;

    /**
     * @var GridDefinitionFactoryInterface
     */
    private $queueGridDefinitionFactory;

    /**
     * @var ResponseBuilder
     */
    private $responseBuilder;

    public function __construct(
        FlowAdminService $flowAdminService,
        GridFactoryInterface $queueGridFactory,
        GridDefinitionFactoryInterface $queueGridDefinitionFactory,
        ResponseBuilder $responseBuilder
    )
    {
        parent::__construct();
        $this->flowAdminService = $flowAdminService;
        $this->queueGridFactory = $queueGridFactory;
        $this->queueGridDefinitionFactory = $queueGridDefinitionFactory;
        $this->responseBuilder = $responseBuilder;
    }

    public function indexAction(Request $request): Response
    {
        return $this->render('@Modules/mailsendvx/views/templates/admin/flows_list.html.twig', array_merge(
            $this->flowAdminService->getOperationsViewData(),
            [
                'shopName' => (string) $this->getContext()->shop->name,
            ]
        ));
    }

    public function createAction(Request $request): Response
    {
        return $this->renderFlowForm($request, null);
    }

    public function editAction(Request $request, int $idFlow): Response
    {
        return $this->renderFlowForm($request, $idFlow);
    }

    public function runQueueNowAction(Request $request): Response
    {
        if (!$this->isCsrfTokenValid('mailsendvx-flow-save', (string) $request->request->get('_token'))) {
            $this->addFlash('danger', $this->trans('El token de seguridad no es válido. Recarga la página e inténtalo de nuevo.', 'Admin.Notifications.Error', []));

            return $this->redirect($this->getQueueReturnUrl($request));
        }

        try {
            $result = $this->flowAdminService->runQueueWorker((int) $request->request->get('queue_limit', 50));
            $this->addFlash('success', sprintf(
                'Queue procesada. Encontrados: %d, enviados: %d, reintentos: %d, cancelados: %d, skipped: %d.',
                (int) ($result['found'] ?? 0),
                (int) ($result['sent'] ?? 0),
                (int) ($result['retry_scheduled'] ?? 0),
                (int) ($result['cancelled'] ?? 0),
                (int) ($result['skipped'] ?? 0)
            ));
        } catch (\Throwable $exception) {
            $this->addFlash('danger', (string) $exception->getMessage());
        }

        return $this->redirect($this->getQueueReturnUrl($request));
    }

    public function cancelQueueJobAction(Request $request, int $idQueue): Response
    {
        $token = (string) $request->get('_token', $request->request->get('_token'));
        if (!$this->isCsrfTokenValid('cancel-queue-' . $idQueue, $token)
            && !$this->isCsrfTokenValid('mailsendvx-queue-cancel', $token)
        ) {
            $this->addFlash('danger', $this->trans('El token de seguridad no es válido. Recarga la página e inténtalo de nuevo.', 'Admin.Notifications.Error', []));

            return $this->redirectToRoute('mailsendvx_flows');
        }

        try {
            $reason = trim((string) $request->request->get('cancel_reason', ''));
            $this->flowAdminService->cancelQueueJob(
                $idQueue,
                $reason !== '' ? $reason : 'Cancelled manually from Back Office.'
            );
            $this->addFlash('success', $this->trans('Job cancelado.', 'Admin.Notifications.Success', []));
        } catch (\Throwable $exception) {
            $this->addFlash('danger', (string) $exception->getMessage());
        }

        return $this->redirect($this->getQueueReturnUrl($request));
    }

    public function cancelQueueBulkAction(Request $request): Response
    {
        if (!$this->isCsrfTokenValid('mailsendvx-queue-bulk-cancel', (string) $request->get('_token'))) {
            $this->addFlash('danger', $this->trans('El token de seguridad no es válido. Recarga la página e inténtalo de nuevo.', 'Admin.Notifications.Error', []));

            return $this->redirectToRoute('mailsendvx_flows');
        }

        $selectedIds = array_values(array_filter(array_map('intval', (array) $request->request->get(MailSendVxQueueGridDefinitionFactory::GRID_ID . '_bulk_queue', []))));
        if (empty($selectedIds)) {
            $this->addFlash('warning', $this->trans('Selecciona al menos un job para cancelar.', 'Admin.Notifications.Warning', []));

            return $this->redirectToRoute('mailsendvx_flows');
        }

        $cancelled = 0;
        $errors = [];

        foreach ($selectedIds as $idQueue) {
            try {
                if ($this->flowAdminService->cancelQueueJob($idQueue, 'Cancelled manually from Back Office (bulk action).')) {
                    ++$cancelled;
                }
            } catch (\Throwable $exception) {
                $errors[] = sprintf('#%d: %s', $idQueue, $exception->getMessage());
            }
        }

        if ($cancelled > 0) {
            $this->addFlash('success', sprintf('%d job(s) cancelado(s).', $cancelled));
        }

        foreach ($errors as $error) {
            $this->addFlash('danger', $error);
        }

        return $this->redirect($this->getQueueReturnUrl($request));
    }

    private function getQueueReturnUrl(Request $request): string
    {
        $referer = (string) $request->headers->get('referer', '');

        return $referer !== '' ? $referer : $this->generateUrl('mailsendvx_flows');
    }

    private function renderFlowForm(Request $request, ?int $editId): Response
    {
        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('mailsendvx-flow-save', (string) $request->request->get('_token'))) {
                $this->addFlash('danger', $this->trans('El token de seguridad no es válido. Recarga la página e inténtalo de nuevo.', 'Admin.Notifications.Error', []));

                return $this->redirectToRoute($editId ? 'mailsendvx_flow_edit' : 'mailsendvx_flow_create', $editId ? ['idFlow' => $editId] : []);
            }

            try {
                $presetId = trim((string) $request->request->get('preset_id', ''));
                if ($presetId !== '') {
                    $this->flowAdminService->createPresetFlow($presetId);
                    $this->addFlash('success', $this->trans('Preset comercial creado.', 'Admin.Notifications.Success', []));

                    return $this->redirectToRoute('mailsendvx_flows');
                }

                $this->flowAdminService->saveFlow($request->request->all());
                $savedId = (int) ($request->request->get('id_mailsendvx_flow', 0) ?: 0);
                if ($savedId <= 0) {
                    $savedId = $this->findLastSavedFlowId($request->request->all());
                }
                $this->addFlash('success', $this->trans('Flow guardado.', 'Admin.Notifications.Success', []));

                return $savedId > 0
                    ? $this->redirectToRoute('mailsendvx_flow_edit', ['idFlow' => $savedId])
                    : $this->redirectToRoute('mailsendvx_flows');
            } catch (\Throwable $exception) {
                $this->addFlash('danger', (string) $exception->getMessage());
            }
        }

        return $this->render('@Modules/mailsendvx/views/templates/admin/flow_form.html.twig', array_merge(
            $this->flowAdminService->getOperationsViewData(),
            [
                'shopName' => (string) $this->getContext()->shop->name,
                'flowFormData' => $this->flowAdminService->getFlowFormData($editId),
                'currentEditFlowId' => $editId,
            ]
        ));
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function findLastSavedFlowId(array $payload): int
    {
        $name = trim((string) ($payload['name'] ?? ''));
        $triggerEvent = trim((string) ($payload['trigger_event'] ?? ''));
        $contextType = trim((string) ($payload['context_type'] ?? ''));

        foreach ($this->flowAdminService->getFlowsForView() as $flow) {
            if ((string) ($flow['name'] ?? '') !== $name) {
                continue;
            }

            if ((string) ($flow['trigger_event'] ?? '') !== $triggerEvent) {
                continue;
            }

            if ((string) ($flow['context_type'] ?? '') !== $contextType) {
                continue;
            }

            return (int) ($flow['id_mailsendvx_flow'] ?? 0);
        }

        return 0;
    }
}
