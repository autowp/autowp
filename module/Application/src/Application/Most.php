<?php

namespace Application;

use Application\Service\SpecificationsService;

use Zend_Db_Table_Select;

use Application\Most\Adapter\AbstractAdapter;

use Exception;

class Most
{
    protected $_carsCount = 10;

    /**
     * @var Zend_Db_Table_Select
     */
    protected $_carsSelect;

    /**
     * @var AbstractAdapter
     */
    protected $_adapter = null;

    /**
     * @var SpecificationsService
     */
    protected $_specs;

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

    /**
     * @param SpecificationsService $value
     * @return Most
     */
    public function setSpecs(SpecificationsService $value)
    {
        $this->_specs = $value;

        return $this;
    }

    /**
     * @return SpecificationsService
     */
    public function getSpecs()
    {
        return $this->_specs;
    }

    public function setAdapter($options)
    {
        $adapterNamespace = 'Application\\Most\\Adapter\\';

        $adapter = null;
        if (isset($options['name'])) {
            $adapter = $options['name'];
            unset($options['name']);
        }

        if (!is_string($adapter) || empty($adapter)) {
            throw new Exception('Adapter name must be specified in a string');
        }

        $adapterName = $adapterNamespace . '\\';
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
        if (! $mostAdapter instanceof AbstractAdapter) {
            throw new Exception(
                sprintf(
                    "Adapter class %s does not extend %s", 
                    $adapterName, AbstractAdapter::class
                )
            );
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
     * @return Most
     */
    public function setCarsSelect(Zend_Db_Table_Select $select)
    {
        $this->_carsSelect = $select;

        return $this;
    }
}