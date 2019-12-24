<?php

namespace Application\Hydrator\Api;

use Exception;
use Zend\Db\Sql;
use Zend\Db\TableGateway\TableGateway;
use Application\Hydrator\Api\Strategy\PerspectiveGroups as HydratorPerspectiveGroupsStrategy;

class PerspectivePageHydrator extends RestHydrator
{
    /**
     * @var TableGateway
     */
    private $groupTable;

    public function __construct($serviceManager)
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
            'id'   => (int)$object['id'],
            'name' => $object['name']
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
     * @param array $data
     * @param $object
     * @throws Exception
     */
    public function hydrate(array $data, $object)
    {
        throw new Exception("Not supported");
    }
}
