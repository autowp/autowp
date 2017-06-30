<?php

namespace Application\Db;

use Zend\Db\Adapter\Adapter;
use Zend\Db\TableGateway\Feature\SequenceFeature;
use Zend\Db\TableGateway\TableGateway;
use Zend\ServiceManager\ServiceLocatorInterface;
use Zend\ServiceManager\Exception\ServiceNotFoundException;

class TableManager implements ServiceLocatorInterface
{
    /**
     * @var array
     */
    private $specs = [
        'item' => [],
        'item_language' => [],
        'item_parent' => [],
        'item_parent_language' => [],
        'links' => [],
        'pictures' => [],
    ];

    /**
     * @var array
     */
    private $tables = [];

    /**
     * @var Adapter
     */
    private $adapter;

    public function __construct(Adapter $adapter)
    {
        $this->adapter = $adapter;
    }

    public function build($name, array $options = null)
    {
    }

    public function get($id)
    {
        if (! isset($this->tables[$id])) {
            if (! isset($this->specs[$id])) {
                throw new ServiceNotFoundException(sprintf(
                    'Unable to create service "%s"',
                    $id
                ));
            }

            $spec = $this->specs[$id];

            $platform = $this->adapter->getPlatform();
            $platformName = $platform->getName();

            $features = [];
            if ($platformName == 'PostgreSQL') {
                if (isset($spec['sequences'])) {
                    foreach ($spec['sequences'] as $field => $sequence) {
                        $features[] = new SequenceFeature($field, $sequence);
                    }
                }
            }

            $this->tables[$id] = new TableGateway($id, $this->adapter, $features);
        }

        return $this->tables[$id];
    }

    public function has($id)
    {
        return isset($this->specs[$id]);
    }
}
