<?php

namespace Application\Hydrator\Api;

use Exception;

class ItemParentLanguageHydrator extends RestHydrator
{
    public function extract($object)
    {
        $result = [
            'language' => $object['language'],
            'name'     => $object['name']
        ];

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
