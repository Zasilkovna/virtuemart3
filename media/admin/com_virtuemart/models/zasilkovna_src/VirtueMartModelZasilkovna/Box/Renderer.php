<?php

namespace VirtueMartModelZasilkovna\Box;

class Renderer
{
    /** @var string */
    protected $template;

    /** @var string */
    protected $templateDir;

    /** @var array */
    protected $variables = [];

    /**
     * Renderer constructor.
     *
     * @param $templateDir
     */
    public function __construct($templateDir) {
        $this->templateDir = $templateDir;
        $this->template = $this->getTemplateDirPath() . '/default.phtml';
    }

    /**
     * @param $template
     */
    public function setTemplate($template) {
        if (!is_file($template)) {
            throw new \InvalidArgumentException('template must be file');
        }

        $this->template = $template;
    }

    public function getTemplateDirPath() {
        return __DIR__ . '/' . $this->templateDir;
    }

    /**
     * @param array $variables
     */
    public function setVariables(array $variables) {
        $this->variables = $variables;
    }

    /**
     * @return string
     */
    public function renderToString() {
        extract($this->variables);

        ob_start();
        include $this->template;
        $html = ob_get_clean();

        return $html;
    }
}
