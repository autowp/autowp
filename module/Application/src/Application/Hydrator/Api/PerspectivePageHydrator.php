<?php

namespace Application\Hydrator\Api;

use Application\Hydrator\Api\Strategy\PerspectiveGroups as HydratorPerspectiveGroupsStrategy;
use Exception;
use Laminas\Db\Sql;
use Laminas\Db\TableGateway\TableGateway;
use Laminas\ServiceManager\ServiceLocatorInterface;

class PerspectivePageHydrator extends RestHydrator
{
    private TableGateway $groupTable;

    public function __construct(ServiceLocatorInterface $serviceManager)
    {
        parent::__construct();

        $tables = $serviceManager->get('TableManager');

        $this->groupTable = $tables->get('perspectives_groups');

        $strategy = new HydratorPerspectiveGroupsStrategy($serviceManager);
        $this->addStrategy('groups', $strategy);
    }

    public function extract($object)
    {
        $result = [
            'id'   => (int) $object['id'],
            'name' => $object['name'],
        ];

        if ($this->filterComposite->filter('groups')) {
            $select = new Sql\Select($this->groupTable->getTable());

            $select->where(['page_id' => $object['id']])
                ->order('position');

            $rows = $this->groupTable->selectWith($select);

            $result['groups'] = $this->extractValue('groups', $rows);
        }

        return $result;
    }

    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @param $object
     * @throws Exception
     */
    public function hydrate(array $data, $object)
    {
        throw new Exception("Not supported");
    }
}
