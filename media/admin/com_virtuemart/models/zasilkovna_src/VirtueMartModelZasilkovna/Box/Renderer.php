<?php

namespace VirtueMartModelZasilkovna\Box;

class Renderer
{
    /** @var string */
    protected $template;

    /** @var array */
    protected $variables = [];

    /**
     * @param string $template absolute path to PHTML file
     */
    public function setTemplate($template) {
        if (!is_file($template)) {
            throw new \InvalidArgumentException('template must be file ' . $template);
        }

        $this->template = $template;
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
