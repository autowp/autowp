<?php

namespace Application\Hydrator\Api;

use Exception;

class ItemParentLanguageHydrator extends RestHydrator
{
    public function extract($object)
    {
        return [
            'language' => $object['language'],
            'name'     => $object['name'],
        ];
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
