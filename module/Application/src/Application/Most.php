<?php

namespace Application;

use Application\Service\SpecificationsService;

use Zend_Db_Table_Select;

use Application\Most\Adapter\AbstractAdapter;

use Exception;

class Most
{
    protected $carsCount = 10;

    /**
     * @var Zend_Db_Table_Select
     */
    protected $carsSelect;

    /**
     * @var AbstractAdapter
     */
    protected $adapter = null;

    /**
     * @var SpecificationsService
     */
    protected $specs;

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
        $this->carsCount = (int)$value;

        return $this;
    }

    public function getCarsCount()
    {
        return $this->carsCount;
    }

    /**
     * @param SpecificationsService $value
     * @return Most
     */
    public function setSpecs(SpecificationsService $value)
    {
        $this->specs = $value;

        return $this;
    }

    /**
     * @return SpecificationsService
     */
    public function getSpecs()
    {
        return $this->specs;
    }

    public function setAdapter($options)
    {
        $adapterNamespace = 'Application\\Most\\Adapter';

        $adapter = null;
        if (isset($options['name'])) {
            $adapter = $options['name'];
            unset($options['name']);
        }

        if (! is_string($adapter) || empty($adapter)) {
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
                    $adapterName,
                    AbstractAdapter::class
                )
            );
        }

        $this->adapter = $mostAdapter;

        return $this;
    }

    public function getData($language)
    {
        $select = clone $this->carsSelect;

        $select->limit($this->carsCount);

        return $this->adapter->getCars($select, $language);
    }

    /**
     * @param Zend_Db_Table_Select $select
     * @return Most
     */
    public function setCarsSelect(Zend_Db_Table_Select $select)
    {
        $this->carsSelect = $select;

        return $this;
    }
}
