<?php

class Project_Most
{
    /**
     * @var Zend_Db_Adapter
     */
    protected $_db;

    /**
     * @var Zend_Db_Table
     */
    protected $_carsTable;

    /**
     * @var Zend_Db_Table
     */
    protected $_equipesTable;

    protected $_carsCount = 10;

    /**
     * @var Project_Most_Adapter_Abstract
     */
    protected $_adapter = array();

    protected $_wheres = array();

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

    public function setWheres($wheres)
    {
        $this->_wheres = $wheres;

        return $this;
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
        $select = $this->_carsTable->select(true)
            ->limit($this->_carsCount);

        foreach ($this->_wheres as $key => $value) {
            if (is_numeric($key)) {
                $select->where($value);
            } else {
                $select->where($key, $value);
            }
        }

        return $this->_adapter->getCars($select);
    }

    public function setCarsTable($table)
    {
        $this->_carsTable = $table;

        return $this;
    }
}