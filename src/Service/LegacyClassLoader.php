<?php

namespace Velox\MailSendVx\Service;

class LegacyClassLoader
{
    public static function load(): void
    {
        $basePath = dirname(__DIR__, 2) . '/classes/';
        $files = [
            'Provider/MailSendVxMailProviderInterface.php',
            'Provider/MailSendVxPrestaShopMailProvider.php',
            'Repository/MailSendVxEventRepository.php',
            'Repository/MailSendVxLogRepository.php',
            'Repository/MailSendVxTemplateRepository.php',
            'Repository/MailSendVxQueueRepository.php',
            'Service/MailSendVxVariableRenderer.php',
            'Service/MailSendVxLogger.php',
            'Service/MailSendVxMailer.php',
        ];

        foreach ($files as $file) {
            $path = $basePath . $file;
            if (file_exists($path)) {
                require_once $path;
            }
        }
    }
}
