<?php

declare(strict_types=1);

namespace Application\Hydrator\Api;

class ItemParentLanguageHydrator extends RestHydrator
{
    /**
     * @var int|null
     */
    private $userId = null;

    public function __construct(
        $serviceManager
    ) {
        parent::__construct();
    }

    public function extract($object)
    {
        $result = [
            'language' => $object['language'],
            'name'     => $object['name']
        ];

        return $result;
    }

    public function hydrate(array $data, $object)
    {
        throw new \Exception("Not supported");
    }
}
