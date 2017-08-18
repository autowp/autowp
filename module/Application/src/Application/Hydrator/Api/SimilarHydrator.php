<?php

namespace Application\Hydrator\Api;

use Application\Hydrator\Api\Strategy\Picture as HydratorPictureStrategy;
use Application\Model\Picture;

class SimilarHydrator extends RestHydrator
{
    /**
     * @var Picture
     */
    private $picture;

    public function __construct($serviceManager)
    {
        parent::__construct();

        $this->picture = $serviceManager->get(Picture::class);

        $strategy = new HydratorPictureStrategy($serviceManager);
        $this->addStrategy('picture', $strategy);
    }

    public function extract($object)
    {
        $result = [
            'picture_id' => (int)$object['picture_id'],
            'distance'   => $object['distance']
        ];

        if ($this->filterComposite->filter('picture')) {
            $row = $this->picture->getRow(['id' => (int)$object['picture_id']]);
            if ($row) {
                $result['picture'] = $this->extractValue('picture', $row);
            } else {
                $result['picture'] = null;
            }
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
