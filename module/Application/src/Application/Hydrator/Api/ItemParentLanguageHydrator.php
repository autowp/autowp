<?php

declare(strict_types=1);

namespace Application\Hydrator\Api;

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
     */
    public function hydrate(array $data, $object)
    {
        throw new \Exception("Not supported");
    }
}
