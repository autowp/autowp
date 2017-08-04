<?php

namespace Application;

use Exception;

use Zend\Db\Sql;
use Zend\Db\TableGateway\TableGateway;

use Application\Most\Adapter\AbstractAdapter;
use Application\Service\SpecificationsService;

class Most
{
    private $carsCount = 10;

    /**
     * @var Sql\Select
     */
    private $carsSelect;

    /**
     * @var AbstractAdapter
     */
    private $adapter = null;

    /**
     * @var SpecificationsService
     */
    private $specs;

    /**
     * @var TableGateway
     */
    private $attributeTable;

    /**
     * @var TableGateway
     */
    private $itemTable;

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

    public function setAttributeTable(TableGateway $table)
    {
        $this->attributeTable = $table;
    }

    public function setItemTable(TableGateway $table)
    {
        $this->itemTable = $table;
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

        if (! $this->attributeTable) {
            throw new Exception("attributeTable not provided");
        }

        /*
         * Create an instance of the adapter class.
         * Pass the config to the adapter class constructor.
         */
        $options['most'] = $this;
        $options['attributeTable'] = $this->attributeTable;
        $options['itemTable'] = $this->itemTable;
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

    public function setCarsSelect(Sql\Select $select): Most
    {
        $this->carsSelect = $select;

        return $this;
    }
}
