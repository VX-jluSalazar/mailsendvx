<?php

namespace Velox\MailSendVx\Service\Theme;

use PrestaShop\PrestaShop\Adapter\Configuration;
use Velox\MailSendVx\ModuleConstants;

class ColorPaletteProvider
{
    /**
     * @var Configuration
     */
    private $configuration;

    /**
     * @var array<string, string>
     */
    private $defaults = [
        'primary' => '#1B3A5C',
        'secondary' => '#C4690A',
        'neutral' => '#6E6A62',
    ];

    /**
     * @var array<string, float>
     */
    private $lightMixMap = [
        '50' => 0.95,
        '100' => 0.88,
        '200' => 0.74,
        '300' => 0.58,
        '400' => 0.32,
    ];

    /**
     * @var array<string, float>
     */
    private $darkMixMap = [
        '600' => 0.10,
        '700' => 0.22,
        '800' => 0.36,
        '900' => 0.52,
        '950' => 0.68,
    ];

    /**
     * @var array<string, string>
     */
    private $prestaShopPresetPalette = [
        'PrestaShop Blue' => '#25B9D7',
        'PrestaShop Navy' => '#363A41',
        'PrestaShop Slate' => '#6C868E',
        'PrestaShop Cloud' => '#BBCDD2',
        'PrestaShop Mint' => '#72C279',
        'PrestaShop Coral' => '#F39D72',
        'PrestaShop Sky' => '#5AC7D7',
        'PrestaShop Steel' => '#A3B1BB',
    ];

    public function __construct(Configuration $configuration)
    {
        $this->configuration = $configuration;
    }

    /**
     * @return array<string, mixed>
     */
    public function getTemplateContext(): array
    {
        return [
            'color' => $this->buildPaletteContext(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function buildPaletteContext(): array
    {
        $context = [];

        foreach ($this->getBaseColors() as $family => $baseColor) {
            $scale = $this->generateScale($baseColor);
            $context[$family] = $scale;

            foreach ($scale as $shade => $hex) {
                $context[$family . '-' . $shade] = $hex;
                $context[$family . '_' . $shade] = $hex;
            }
        }

        return $context;
    }

    /**
     * @return array<string, string>
     */
    public function getBaseColors(): array
    {
        return [
            'primary' => $this->normalizeHexColor((string) $this->configuration->get(ModuleConstants::CONFIG_COLOR_PRIMARY_500), $this->defaults['primary']),
            'secondary' => $this->normalizeHexColor((string) $this->configuration->get(ModuleConstants::CONFIG_COLOR_SECONDARY_500), $this->defaults['secondary']),
            'neutral' => $this->normalizeHexColor((string) $this->configuration->get(ModuleConstants::CONFIG_COLOR_NEUTRAL_500), $this->defaults['neutral']),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function getPrestaShopPresetPalette(): array
    {
        return $this->prestaShopPresetPalette;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getDocumentationGuide(): array
    {
        $guide = [];

        foreach ($this->getBaseColors() as $family => $baseColor) {
            $rows = [];
            foreach ($this->generateScale($baseColor) as $shade => $hex) {
                $rows[] = [
                    'name' => $family . '-' . $shade,
                    'hex' => $hex,
                    'twig' => '{{ color.' . $family . '[' . $shade . '] }}',
                    'twig_alias' => '{{ color.' . $family . '_' . $shade . ' }}',
                    'twig_bracket' => "{{ color['" . $family . '-' . $shade . "'] }}",
                ];
            }

            $guide[] = [
                'family' => $family,
                'base_color' => $baseColor,
                'rows' => $rows,
            ];
        }

        return $guide;
    }

    /**
     * @return array<string, string>
     */
    private function generateScale(string $baseColor): array
    {
        $baseRgb = $this->hexToRgb($baseColor);
        $scale = [];

        foreach ($this->lightMixMap as $shade => $factor) {
            $scale[$shade] = $this->rgbToHex($this->mixRgb($baseRgb, [255, 255, 255], $factor));
        }

        $scale['500'] = $this->rgbToHex($baseRgb);

        foreach ($this->darkMixMap as $shade => $factor) {
            $scale[$shade] = $this->rgbToHex($this->mixRgb($baseRgb, [0, 0, 0], $factor));
        }

        return $scale;
    }

    /**
     * @return array{0: int, 1: int, 2: int}
     */
    private function hexToRgb(string $hex): array
    {
        $hex = ltrim($hex, '#');

        return [
            (int) hexdec(substr($hex, 0, 2)),
            (int) hexdec(substr($hex, 2, 2)),
            (int) hexdec(substr($hex, 4, 2)),
        ];
    }

    /**
     * @param array{0: int, 1: int, 2: int} $source
     * @param array{0: int, 1: int, 2: int} $target
     *
     * @return array{0: int, 1: int, 2: int}
     */
    private function mixRgb(array $source, array $target, float $factor): array
    {
        return [
            (int) round(($source[0] * (1 - $factor)) + ($target[0] * $factor)),
            (int) round(($source[1] * (1 - $factor)) + ($target[1] * $factor)),
            (int) round(($source[2] * (1 - $factor)) + ($target[2] * $factor)),
        ];
    }

    /**
     * @param array{0: int, 1: int, 2: int} $rgb
     */
    private function rgbToHex(array $rgb): string
    {
        return sprintf('#%02X%02X%02X', $rgb[0], $rgb[1], $rgb[2]);
    }

    private function normalizeHexColor(string $value, string $fallback): string
    {
        $value = trim($value);
        if ($value === '') {
            return $fallback;
        }

        if (preg_match('/^#([0-9a-fA-F]{3})$/', $value, $matches)) {
            $value = '#' . $matches[1][0] . $matches[1][0] . $matches[1][1] . $matches[1][1] . $matches[1][2] . $matches[1][2];
        }

        if (!preg_match('/^#[0-9a-fA-F]{6}$/', $value)) {
            return $fallback;
        }

        return strtoupper($value);
    }
}
