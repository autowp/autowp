<?php

namespace Application\Most\Adapter;

use Application\Most;
use Exception;
use Laminas\Db\Sql;
use Laminas\Db\TableGateway\TableGateway;

use function method_exists;
use function ucfirst;

abstract class AbstractAdapter
{
    /** @var Most */
    protected $most;

    /** @var TableGateway */
    protected $attributeTable;

    /** @var TableGateway */
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

    abstract public function getCars(Sql\Select $select, string $language): array;
}
