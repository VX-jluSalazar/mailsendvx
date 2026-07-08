<?php

namespace Velox\MailSendVx\Controller\Admin;

use PrestaShop\PrestaShop\Core\Grid\Definition\Factory\GridDefinitionFactoryInterface;
use PrestaShop\PrestaShop\Core\Grid\GridFactoryInterface;
use PrestaShopBundle\Controller\Admin\FrameworkBundleAdminController;
use PrestaShopBundle\Service\Grid\ResponseBuilder;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Velox\MailSendVx\Grid\Definition\Factory\MailSendVxQueueGridDefinitionFactory;
use Velox\MailSendVx\Grid\Filters\MailSendVxQueueFilters;
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

    public function indexAction(Request $request, MailSendVxQueueFilters $queueFilters): Response
    {
        $editId = $request->query->getInt('edit', 0) ?: null;

        if ($request->isMethod('POST') && $request->request->has(MailSendVxQueueGridDefinitionFactory::GRID_ID)) {
            return $this->responseBuilder->buildSearchResponse(
                $this->queueGridDefinitionFactory,
                $request,
                MailSendVxQueueGridDefinitionFactory::GRID_ID,
                'mailsendvx_flows',
                ['edit']
            );
        }

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('mailsendvx-flow-save', (string) $request->request->get('_token'))) {
                $this->addFlash('danger', $this->trans('El token de seguridad no es válido. Recarga la página e inténtalo de nuevo.', 'Admin.Notifications.Error', []));

                return $this->redirectToRoute('mailsendvx_flows', $editId ? ['edit' => $editId] : []);
            }

            try {
                $presetId = trim((string) $request->request->get('preset_id', ''));
                $runQueueNow = (string) $request->request->get('run_queue_now', '');
                if ($runQueueNow === '1') {
                    $result = $this->flowAdminService->runQueueWorker((int) $request->request->get('queue_limit', 50));
                    $this->addFlash('success', sprintf(
                        'Queue procesada. Encontrados: %d, enviados: %d, reintentos: %d, cancelados: %d, skipped: %d.',
                        (int) ($result['found'] ?? 0),
                        (int) ($result['sent'] ?? 0),
                        (int) ($result['retry_scheduled'] ?? 0),
                        (int) ($result['cancelled'] ?? 0),
                        (int) ($result['skipped'] ?? 0)
                    ));

                    return $this->redirectToRoute('mailsendvx_flows');
                }

                if ($presetId !== '') {
                    $this->flowAdminService->createPresetFlow($presetId);
                    $this->addFlash('success', $this->trans('Preset comercial creado.', 'Admin.Notifications.Success', []));

                    return $this->redirectToRoute('mailsendvx_flows');
                }

                $this->flowAdminService->saveFlow($request->request->all());
                $this->addFlash('success', $this->trans('Flow guardado.', 'Admin.Notifications.Success', []));

                return $this->redirectToRoute('mailsendvx_flows');
            } catch (\Throwable $exception) {
                $this->addFlash('danger', (string) $exception->getMessage());
            }
        }

        return $this->render('@Modules/mailsendvx/views/templates/admin/flows.html.twig', array_merge(
            $this->flowAdminService->getOperationsViewData(),
            [
                'shopName' => (string) $this->getContext()->shop->name,
                'flowFormData' => $this->flowAdminService->getFlowFormData($editId),
                'currentEditFlowId' => $editId,
                'queueGrid' => $this->presentGrid($this->queueGridFactory->getGrid($queueFilters)),
            ]
        ));
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

        return $this->redirectToRoute('mailsendvx_flows');
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

        return $this->redirectToRoute('mailsendvx_flows');
    }
}
