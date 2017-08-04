<?php

namespace Application\Most\Adapter;

use Exception;

use Zend\Db\Sql;
use Zend\Db\TableGateway\TableGateway;

use Application\Most;

abstract class AbstractAdapter
{
    /**
     * @var Most
     */
    protected $most;

    /**
     * @var TableGateway
     */
    protected $attributeTable;

    /**
     * @var TableGateway
     */
    protected $itemTable;

    public function __construct(array $options)
    {
        $this->setOptions($options);
    }

    public function setAttributeTable(TableGateway $table)
    {
        $this->attributeTable = $table;
    }

    public function setItemTable(TableGateway $itemTable)
    {
        $this->itemTable = $itemTable;
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

    abstract public function getCars(Sql\Select $select, $language);
}
