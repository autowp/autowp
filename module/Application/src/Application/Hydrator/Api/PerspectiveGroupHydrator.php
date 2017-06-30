<?php

namespace Application\Hydrator\Api;

use Application\Hydrator\Api\Strategy\Perspectives as HydratorPerspectivesStrategy;
use Application\Model\DbTable;

class PerspectiveGroupHydrator extends RestHydrator
{
    /**
     * @var DbTable\Perspective
     */
    private $perspectiveTable;

    public function __construct($serviceManager)
    {
        parent::__construct();

        $this->perspectiveTable = new DbTable\Perspective();

        $strategy = new HydratorPerspectivesStrategy($serviceManager);
        $this->addStrategy('perspectives', $strategy);
    }

    public function extract($object)
    {
        $result = [
            'id'   => (int)$object['id'],
            'name' => $object['name']
        ];

        if ($this->filterComposite->filter('perspectives')) {
            $rows = $this->perspectiveTable->fetchAll(
                $this->perspectiveTable->select(true)
                    ->join(
                        'perspectives_groups_perspectives',
                        'perspectives.id = perspectives_groups_perspectives.perspective_id',
                        []
                    )
                    ->where('perspectives_groups_perspectives.group_id = ?', $object['id'])
                    ->order('perspectives_groups_perspectives.position')
            );

            $result['perspectives'] = $this->extractValue('perspectives', $rows);
        }

        return $result;
    }

    public function hydrate(array $data, $object)
    {
        throw new \Exception("Not supported");
    }
}
