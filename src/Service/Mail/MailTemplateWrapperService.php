<?php

namespace Velox\MailSendVx\Service\Mail;

use Language;
use RuntimeException;
use Validate;

class MailTemplateWrapperService
{
    /**
     * @return array<string, string>
     */
    public function getAvailableWrappers(): array
    {
        $wrappers = [];
        foreach ($this->getLanguageDirectories() as $directory) {
            foreach (glob($directory . '*.html') ?: [] as $filePath) {
                $name = pathinfo($filePath, PATHINFO_FILENAME);
                if ($name === 'index') {
                    continue;
                }

                $wrappers[$name] = $name;
            }
        }

        ksort($wrappers);

        return $wrappers;
    }

    public function wrapperExists(string $wrapperName): bool
    {
        $wrapperName = $this->normalizeWrapperName($wrapperName);
        foreach ($this->getLanguageDirectories() as $directory) {
            if (is_file($directory . $wrapperName . '.html') && is_file($directory . $wrapperName . '.txt')) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array{html: string, text: string}
     */
    public function getWrapperContent(string $wrapperName, int $idLang): array
    {
        $wrapperName = $this->normalizeWrapperName($wrapperName);
        $preferredDirectory = $this->getPreferredLanguageDirectory($idLang);
        $fallbackDirectories = array_unique(array_merge(
            $preferredDirectory ? [$preferredDirectory] : [],
            $this->getLanguageDirectories()
        ));

        foreach ($fallbackDirectories as $directory) {
            $htmlPath = $directory . $wrapperName . '.html';
            $textPath = $directory . $wrapperName . '.txt';
            if (is_file($htmlPath) && is_file($textPath)) {
                return [
                    'html' => $this->normalizeTwigWrapperSyntax((string) file_get_contents($htmlPath), false),
                    'text' => $this->normalizeTwigWrapperSyntax((string) file_get_contents($textPath), true),
                ];
            }
        }

        return [
            'html' => '',
            'text' => '',
        ];
    }

    public function saveWrapperContent(string $wrapperName, string $htmlContent, string $textContent): string
    {
        $wrapperName = $this->normalizeWrapperName($wrapperName);
        if ($wrapperName === '') {
            throw new RuntimeException('Wrapper name is required.');
        }

        if ($htmlContent === '' || $textContent === '') {
            throw new RuntimeException('Wrapper HTML and text content are required.');
        }

        $htmlContent = $this->normalizeTwigWrapperSyntax($htmlContent, false);
        $textContent = $this->normalizeTwigWrapperSyntax($textContent, true);

        foreach ($this->getLanguageDirectories(true) as $directory) {
            if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
                throw new RuntimeException(sprintf('Wrapper directory could not be created: %s', $directory));
            }

            file_put_contents($directory . $wrapperName . '.html', $htmlContent);
            file_put_contents($directory . $wrapperName . '.txt', $textContent);
        }

        return $wrapperName;
    }

    private function normalizeWrapperName(string $wrapperName): string
    {
        $wrapperName = trim($wrapperName);
        if ($wrapperName === '') {
            return '';
        }

        $wrapperName = preg_replace('/[^a-zA-Z0-9_]+/', '_', $wrapperName) ?: '';

        return trim($wrapperName, '_');
    }

    /**
     * @return array<int, string>
     */
    private function getLanguageDirectories(bool $createMissing = false): array
    {
        $directories = [];
        foreach (Language::getLanguages(false) as $language) {
            $iso = isset($language['iso_code']) ? (string) $language['iso_code'] : '';
            if ($iso === '') {
                continue;
            }

            $directory = $this->getMailBaseDirectory() . $iso . '/';
            if ($createMissing || is_dir($directory)) {
                $directories[] = $directory;
            }
        }

        return array_values(array_unique($directories));
    }

    private function getPreferredLanguageDirectory(int $idLang): ?string
    {
        $language = new Language($idLang);
        if (!Validate::isLoadedObject($language) || empty($language->iso_code)) {
            return null;
        }

        $directory = $this->getMailBaseDirectory() . $language->iso_code . '/';

        return is_dir($directory) ? $directory : null;
    }

    private function getMailBaseDirectory(): string
    {
        return dirname(__DIR__, 2) . '/mails/';
    }

    private function normalizeTwigWrapperSyntax(string $content, bool $isText): string
    {
        if ($content === '') {
            return '';
        }

        $replacements = [
            '{shop_name}' => '{{ shop.name }}',
            '{shop_url}' => '{{ shop.url }}',
            '{unsubscribe_url}' => '{{ shop.unsubscribe_url }}',
            '{shop_unsubscribe_url}' => '{{ shop.unsubscribe_url }}',
            '{mailsendvx_text_content}' => '{{ mailsendvx_text_content }}',
            '{mailsendvx_html_content}' => $isText ? '{{ mailsendvx_text_content }}' : '{{ mailsendvx_html_content|raw }}',
        ];

        return strtr($content, $replacements);
    }
}
