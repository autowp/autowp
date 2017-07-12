<?php

namespace Application\Hydrator\Api;

use Application\Hydrator\Api\Strategy\PerspectiveGroups as HydratorPerspectiveGroupsStrategy;
use Application\Model\DbTable;

class PerspectivePageHydrator extends RestHydrator
{
    /**
     * @var DbTable\Perspective\Group
     */
    private $groupTable;

    public function __construct($serviceManager)
    {
        parent::__construct();

        $this->groupTable = new DbTable\Perspective\Group();

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
            $rows = $this->groupTable->fetchAll([
                'page_id = ?' => $object['id']
            ], 'position');

            $result['groups'] = $this->extractValue('groups', $rows);
        }

        return $result;
    }

    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function hydrate(array $data, $object)
    {
        throw new \Exception("Not supported");
    }
}
