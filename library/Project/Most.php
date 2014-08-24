<?php

class Project_Most
{
    protected $_carsCount = 10;

    /**
     * @var Zend_Db_Table_Select
     */
    protected $_carsSelect;

    /**
     * @var Project_Most_Adapter_Abstract
     */
    protected $_adapter = array();

    public function __construct(array $options)
    {
        $this->setOptions($options);
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

    }

    public function setCarsCount($value)
    {
        $this->_carsCount = (int)$value;

        return $this;
    }

    public function getCarsCount()
    {
        return $this->_carsCount;
    }

    public function setAdapter($options)
    {
        $adapterNamespace = 'Project_Most_Adapter';

        $adapter = null;
        if (isset($options['name'])) {
            $adapter = $options['name'];
            unset($options['name']);
        }

        if (!is_string($adapter) || empty($adapter)) {
            throw new Exception('Adapter name must be specified in a string');
        }

        $adapterName = $adapterNamespace . '_';
        $adapterName .= str_replace(' ', '_', ucwords(str_replace('_', ' ', strtolower($adapter))));

        /*
         * Create an instance of the adapter class.
         * Pass the config to the adapter class constructor.
         */
        $options['most'] = $this;
        $mostAdapter = new $adapterName($options);

        /*
         * Verify that the object created is a descendent of the abstract adapter type.
         */
        if (! $mostAdapter instanceof Project_Most_Adapter_Abstract) {
            throw new Exception("Adapter class '$adapterName' does not extend Project_Most_Adapter_Abstract");
        }

        $this->_adapter = $mostAdapter;

        return $this;
    }

    public function getData()
    {
        $select = clone $this->_carsSelect;

        $select->limit($this->_carsCount);

        return $this->_adapter->getCars($select);
    }

    /**
     * @param Zend_Db_Table_Select $select
     * @return Project_Most
     */
    public function setCarsSelect(Zend_Db_Table_Select $select)
    {
        $this->_carsSelect = $select;

        return $this;
    }
}