<?php

namespace Velox\MailSendVx\Service\Mail;

use Context;
use Language;
use RuntimeException;
use Validate;
use Velox\MailSendVx\Repository\MailSendVxWrapperRepository;

class MailTemplateWrapperService
{
    /**
     * @var Context
     */
    private $context;

    /**
     * @var MailSendVxWrapperRepository
     */
    private $repository;

    public function __construct(Context $context, MailSendVxWrapperRepository $repository)
    {
        $this->context = $context;
        $this->repository = $repository;
    }

    /**
     * @return array<string, string>
     */
    public function getAvailableWrappers(?int $idShop = null): array
    {
        $wrappers = [];
        foreach ($this->repository->findNamesByShop($this->resolveShopId($idShop)) as $name) {
            $wrappers[$name] = $name;
        }

        foreach ($this->getAvailableFileWrappers() as $name) {
            $wrappers[$name] = $name;
        }

        if (!isset($wrappers['mailsendvx_default'])) {
            $wrappers['mailsendvx_default'] = 'mailsendvx_default';
        }

        ksort($wrappers);

        return $wrappers;
    }

    public function wrapperExists(string $wrapperName, int $idLang, ?int $idShop = null): bool
    {
        $wrapperName = $this->normalizeWrapperName($wrapperName);
        if ($wrapperName === '') {
            return false;
        }

        if ($this->repository->findBestMatch($wrapperName, $idLang, $this->resolveShopId($idShop))) {
            return true;
        }

        $fileContent = $this->getFileWrapperContent($wrapperName, $idLang);

        return $fileContent['html'] !== '' && $fileContent['text'] !== '';
    }

    /**
     * @return array{html: string, text: string}
     */
    public function getWrapperContent(string $wrapperName, int $idLang, ?int $idShop = null): array
    {
        $wrapperName = $this->normalizeWrapperName($wrapperName);
        $idShop = $this->resolveShopId($idShop);
        $persisted = $this->repository->findBestMatch($wrapperName, $idLang, $idShop);

        if ($persisted) {
            return [
                'html' => $this->normalizeTwigWrapperSyntax((string) ($persisted['html_content'] ?? ''), false),
                'text' => $this->normalizeTwigWrapperSyntax((string) ($persisted['text_content'] ?? ''), true),
            ];
        }

        return $this->getFileWrapperContent($wrapperName, $idLang);
    }

    public function saveWrapperContent(string $wrapperName, string $htmlContent, string $textContent, int $idLang, ?int $idShop = null): string
    {
        $wrapperName = $this->normalizeWrapperName($wrapperName);
        if ($wrapperName === '') {
            throw new RuntimeException('Wrapper name is required.');
        }

        if ($htmlContent === '' || $textContent === '') {
            throw new RuntimeException('Wrapper HTML and text content are required.');
        }

        $this->repository->save([
            'id_shop' => $this->resolveShopId($idShop),
            'id_lang' => $idLang,
            'name' => $wrapperName,
            'html_content' => $this->normalizeTwigWrapperSyntax($htmlContent, false),
            'text_content' => $this->normalizeTwigWrapperSyntax($textContent, true),
        ]);

        return $wrapperName;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getWrappersTableRows(?int $idShop = null): array
    {
        $idShop = $this->resolveShopId($idShop);
        $rows = [];
        $persistedKeys = [];

        foreach ($this->repository->getAllByShop($idShop) as $row) {
            $row['source'] = 'database';
            $row['editable'] = true;
            $persistedKeys[$this->buildScopeKey((string) ($row['name'] ?? ''), (int) ($row['id_lang'] ?? 0), (int) ($row['id_shop'] ?? 0))] = true;
            $persistedKeys[$this->buildScopeKey((string) ($row['name'] ?? ''), (int) ($row['id_lang'] ?? 0), $idShop)] = true;
            $rows[] = $row;
        }

        foreach ($this->getFileWrapperRows() as $row) {
            $key = $this->buildScopeKey((string) $row['name'], (int) $row['id_lang'], $idShop);
            if (isset($persistedKeys[$key])) {
                continue;
            }

            $rows[] = [
                'id_mailsendvx_wrapper' => 0,
                'id_shop' => $idShop,
                'id_lang' => (int) $row['id_lang'],
                'name' => (string) $row['name'],
                'html_content' => null,
                'text_content' => null,
                'date_add' => null,
                'date_upd' => null,
                'source' => 'file',
                'editable' => true,
            ];
        }

        usort($rows, function (array $left, array $right): int {
            $nameComparison = strcmp((string) ($left['name'] ?? ''), (string) ($right['name'] ?? ''));
            if ($nameComparison !== 0) {
                return $nameComparison;
            }

            $langComparison = (int) ($left['id_lang'] ?? 0) <=> (int) ($right['id_lang'] ?? 0);
            if ($langComparison !== 0) {
                return $langComparison;
            }

            return strcmp((string) ($right['source'] ?? ''), (string) ($left['source'] ?? ''));
        });

        return $rows;
    }

    public function deleteWrapper(int $idWrapper, ?int $idShop = null): bool
    {
        return $this->repository->delete($idWrapper, $this->resolveShopId($idShop));
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

    private function resolveShopId(?int $idShop = null): int
    {
        return $idShop !== null ? max(0, $idShop) : (int) $this->context->shop->id;
    }

    /**
     * @return array<int, string>
     */
    private function getAvailableFileWrappers(): array
    {
        $wrappers = [];
        foreach ($this->getLanguageDirectories() as $directory) {
            foreach (glob($directory . '*.html') ?: [] as $filePath) {
                $name = pathinfo($filePath, PATHINFO_FILENAME);
                if ($name === 'index') {
                    continue;
                }

                $wrappers[] = $name;
            }
        }

        return array_values(array_unique($wrappers));
    }

    /**
     * @return array<int, array{name: string, id_lang: int}>
     */
    private function getFileWrapperRows(): array
    {
        $rows = [];
        foreach (Language::getLanguages(false) as $language) {
            $idLang = (int) ($language['id_lang'] ?? 0);
            $directory = $this->getMailBaseDirectory() . (string) ($language['iso_code'] ?? '') . '/';
            if (!is_dir($directory)) {
                continue;
            }

            foreach (glob($directory . '*.html') ?: [] as $filePath) {
                $name = pathinfo($filePath, PATHINFO_FILENAME);
                if ($name === 'index') {
                    continue;
                }

                if (!is_file($directory . $name . '.txt')) {
                    continue;
                }

                $rows[] = [
                    'name' => $name,
                    'id_lang' => $idLang,
                ];
            }
        }

        return $rows;
    }

    /**
     * @return array<int, string>
     */
    private function getLanguageDirectories(): array
    {
        $directories = [];
        foreach (Language::getLanguages(false) as $language) {
            $iso = isset($language['iso_code']) ? (string) $language['iso_code'] : '';
            if ($iso === '') {
                continue;
            }

            $directory = $this->getMailBaseDirectory() . $iso . '/';
            if (is_dir($directory)) {
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

    /**
     * @return array{html: string, text: string}
     */
    private function getFileWrapperContent(string $wrapperName, int $idLang): array
    {
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

    private function buildScopeKey(string $name, int $idLang, int $idShop): string
    {
        return $name . '|' . $idLang . '|' . $idShop;
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
