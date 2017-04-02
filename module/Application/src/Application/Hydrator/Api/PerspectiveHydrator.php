<?php

namespace Application\Hydrator\Api;

class PerspectiveHydrator extends RestHydrator
{
    public function __construct($serviceManager)
    {
        parent::__construct();
    }
    
    public function extract($object)
    {
        $result = [
            'id'   => (int)$object['id'],
            'name' => $object['name']
        ];
        
        return $result;
    }
    
    public function hydrate(array $data, $object)
    {
        throw new \Exception("Not supported");
    }
}