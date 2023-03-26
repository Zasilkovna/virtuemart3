<?php

namespace VirtueMartModelZasilkovna\Order;

class DetailFormValidationReport
{
    /** @var string[] */
    private $errors = [];

    /**
     * @return string[]
     */
    public function getErrors()
    {
        return $this->errors;
    }

    /**
     * @return bool
     */
    public function isValid()
    {
        return empty($this->errors);
    }

    /**
     * @param string $error
     * @return void
     */
    public function addError($error)
    {
        $this->errors[] = $error;
    }

}
