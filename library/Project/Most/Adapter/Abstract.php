<?php

abstract class Project_Most_Adapter_Abstract
{
    /**
     * @var Project_Most
     */
    protected $_most;

    public function __construct(array $options)
    {
        $this->setOptions($options);
    }

    public function setMost(Project_Most $most)
    {
        $this->_most = $most;

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

    abstract public function getCars(Zend_Db_Table_Select $select);
}