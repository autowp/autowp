<?php

namespace Application\Hydrator\Api;

use Application\Hydrator\Api\Strategy\Perspectives as HydratorPerspectivesStrategy;
use Application\Model\Perspective;
use Exception;
use Laminas\ServiceManager\ServiceLocatorInterface;

class PerspectiveGroupHydrator extends RestHydrator
{
    private Perspective $perspective;

    public function __construct(ServiceLocatorInterface $serviceManager)
    {
        parent::__construct();

        $this->perspective = $serviceManager->get(Perspective::class);

        $strategy = new HydratorPerspectivesStrategy($serviceManager);
        $this->addStrategy('perspectives', $strategy);
    }

    public function extract($object): ?array
    {
        $result = [
            'id'   => (int) $object['id'],
            'name' => $object['name'],
        ];

        if ($this->filterComposite->filter('perspectives')) {
            $result['perspectives'] = $this->extractValue(
                'perspectives',
                $this->perspective->getGroupPerspectives($object['id'])
            );
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
