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
            throw new \InvalidArgumentException('template must be file ' . $template);
        }

        $this->template = $template;
    }

    /**
     * @param string|null $moduleName
     * @return string
     */
    private function resolveTemplateName($moduleName) {
        if (empty($moduleName)) {
            return $this->resolveTemplateName('default');
        }

        $path = $this->getTemplateDirPath() . '/' . $moduleName . '.phtml';

        if (!is_file($path)) {
            if ($moduleName === 'default') {
                throw new ResolveException('default template does not exist');
            }

            return $this->resolveTemplateName('default');
        }

        return $moduleName;
    }

    /**
     * @param string|null $fileName
     * @return string|null public relative URL
     */
    public function createTemplateJSPath($fileName) {
        if (empty($fileName)) {
            return null;
        }

        $path = '/media/com_zasilkovna/media/js/box/' . $fileName . '.js';
        if (is_file(JPATH_ROOT . $path)) {
            return $path . '?v=' . filemtime(JPATH_ROOT . $path);
        }

        return null;
    }

    /**
     * @param string $templateName
     * @return string
     */
    public function createTemplatePath($templateName) {
        $templateName = $this->resolveTemplateName($templateName);
        return $this->getTemplateDirPath() . '/' . $templateName . '.phtml';
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
