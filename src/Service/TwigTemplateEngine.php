<?php

namespace Velox\MailSendVx\Service;

use Twig\Environment;
use Twig\Loader\ArrayLoader;

class TwigTemplateEngine implements TemplateEngineInterface
{
    /**
     * @var Environment
     */
    private $htmlEnvironment;

    /**
     * @var Environment
     */
    private $textEnvironment;

    public function __construct()
    {
        $this->htmlEnvironment = new Environment(new ArrayLoader(), [
            'autoescape' => 'html',
            'cache' => false,
            'strict_variables' => false,
        ]);

        $this->textEnvironment = new Environment(new ArrayLoader(), [
            'autoescape' => false,
            'cache' => false,
            'strict_variables' => false,
        ]);
    }

    /**
     * @param array<string, mixed> $context
     */
    public function renderSubject(string $content, array $context): string
    {
        return $this->render($this->textEnvironment, $content, $context);
    }

    /**
     * @param array<string, mixed> $context
     */
    public function renderHtml(string $content, array $context): string
    {
        return $this->render($this->htmlEnvironment, $content, $context);
    }

    /**
     * @param array<string, mixed> $context
     */
    public function renderText(string $content, array $context): string
    {
        return $this->render($this->textEnvironment, $content, $context);
    }

    /**
     * @param array<string, mixed> $context
     */
    private function render(Environment $environment, string $content, array $context): string
    {
        if ($content === '') {
            return '';
        }

        return $environment->createTemplate($content)->render($context);
    }
}
