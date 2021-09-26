<?php

namespace Application;

use Application\Most\Adapter\AbstractAdapter;
use Application\Service\SpecificationsService;
use Exception;
use Laminas\Db\Sql;
use Laminas\Db\TableGateway\TableGateway;

use function is_string;
use function method_exists;
use function sprintf;
use function str_replace;
use function strtolower;
use function ucfirst;
use function ucwords;

class Most
{
    private int $carsCount = 10;

    private Sql\Select $carsSelect;

    private AbstractAdapter $adapter;

    private SpecificationsService $specs;

    private TableGateway $attributeTable;

    private TableGateway $itemTable;

    /**
     * @throws Exception
     */
    public function __construct(array $options)
    {
        $this->setOptions($options);
    }

    /**
     * @throws Exception
     */
    public function setOptions(array $options): void
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

    public function setAttributeTable(TableGateway $table): void
    {
        $this->attributeTable = $table;
    }

    public function setItemTable(TableGateway $table): void
    {
        $this->itemTable = $table;
    }

    public function setCarsCount(int $value): self
    {
        $this->carsCount = $value;

        return $this;
    }

    public function getCarsCount(): int
    {
        return $this->carsCount;
    }

    public function setSpecs(SpecificationsService $value): self
    {
        $this->specs = $value;

        return $this;
    }

    public function getSpecs(): SpecificationsService
    {
        return $this->specs;
    }

    /**
     * @throws Exception
     */
    public function setAdapter(array $options): self
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

        $adapterName  = $adapterNamespace . '\\';
        $adapterName .= str_replace(' ', '_', ucwords(str_replace('_', ' ', strtolower($adapter))));

        if (! isset($this->attributeTable)) {
            throw new Exception("attributeTable not provided");
        }

        /*
         * Create an instance of the adapter class.
         * Pass the config to the adapter class constructor.
         */
        $options['most']           = $this;
        $options['attributeTable'] = $this->attributeTable;
        $options['itemTable']      = $this->itemTable;
        $mostAdapter               = new $adapterName($options);

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

    public function getData(string $language): array
    {
        $select = clone $this->carsSelect;

        $select->limit($this->carsCount);

        return $this->adapter->getCars($select, $language);
    }

    public function setCarsSelect(Sql\Select $select): self
    {
        $this->carsSelect = $select;

        return $this;
    }
}
