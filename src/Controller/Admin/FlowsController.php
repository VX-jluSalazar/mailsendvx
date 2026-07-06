<?php

namespace Velox\MailSendVx\Controller\Admin;

use PrestaShopBundle\Controller\Admin\FrameworkBundleAdminController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Velox\MailSendVx\Service\Admin\FlowAdminService;

class FlowsController extends FrameworkBundleAdminController
{
    /**
     * @var FlowAdminService
     */
    private $flowAdminService;

    public function __construct(FlowAdminService $flowAdminService)
    {
        parent::__construct();
        $this->flowAdminService = $flowAdminService;
    }

    public function indexAction(Request $request): Response
    {
        $editId = $request->query->getInt('edit', 0) ?: null;

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
            ]
        ));
    }

    public function cancelQueueJobAction(Request $request, int $idQueue): Response
    {
        if (!$this->isCsrfTokenValid('cancel-queue-' . $idQueue, (string) $request->request->get('_token'))) {
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
}
