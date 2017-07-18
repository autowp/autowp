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
        'contact' => [],
        'item' => [],
        'item_language' => [],
        'item_parent' => [],
        'item_parent_cache' => [],
        'item_parent_language' => [],
        'links' => [],
        'perspectives' => [],
        'perspectives_groups' => [],
        'perspectives_pages' => [],
        'picture_view' => [],
        'pictures' => [],
        'spec' => [],
        'user_account' => [],
        'user_item_subscribe' => [],
        'user_password_remind' => [],
        'user_remember' => [],
        'user_renames' => []
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

    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function build($name, array $options = null)
    {
        if (! isset($this->specs[$name])) {
            throw new ServiceNotFoundException(sprintf(
                'Unable to create service "%s"',
                $name
            ));
        }

        $spec = $this->specs[$name];

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

        return new TableGateway($name, $this->adapter, $features);
    }

    public function get($id)
    {
        if (! isset($this->tables[$id])) {
            $this->tables[$id] = $this->build($id);
        }

        return $this->tables[$id];
    }

    public function has($id)
    {
        return isset($this->specs[$id]);
    }
}
