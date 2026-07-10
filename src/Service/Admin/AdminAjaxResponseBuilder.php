<?php

namespace Velox\MailSendVx\Service\Admin;

use Symfony\Component\HttpFoundation\JsonResponse;

class AdminAjaxResponseBuilder
{
    /**
     * @param array<string, mixed> $payload
     */
    public function createSuccessResponse(array $payload = [], ?string $message = null, int $statusCode = 200): JsonResponse
    {
        return new JsonResponse(array_filter([
            'success' => true,
            'message' => $message,
        ]) + $payload, $statusCode);
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function createErrorResponse(string $message, int $statusCode = 400, array $payload = []): JsonResponse
    {
        return new JsonResponse([
            'success' => false,
            'message' => $message,
        ] + $payload, $statusCode);
    }
}
