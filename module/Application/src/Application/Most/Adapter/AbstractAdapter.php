<?php

namespace Application\Most\Adapter;

use Application\Most;

use Zend_Db_Table_Select;

abstract class AbstractAdapter
{
    /**
     * @var Most
     */
    protected $most;

    public function __construct(array $options)
    {
        $this->setOptions($options);
    }

    public function setMost(Most $most)
    {
        $this->most = $most;

        return $this;
    }

    public function setOptions(array $options)
    {
        foreach ($options as $key => $value) {
            $normalized = ucfirst($key);

            $method = 'set' . $normalized;
            if (method_exists($this, $method)) {
                $this->$method($value);
            } else {
                throw new Exception("Option '$key' not found");
            }
        }

        return $this;
    }

    abstract public function getCars(Zend_Db_Table_Select $select, $language);
}
