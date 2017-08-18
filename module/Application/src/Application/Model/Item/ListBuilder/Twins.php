<?php

namespace Application\Model\Item\ListBuilder;

use Application\Model\Item\ListBuilder;

class Twins extends ListBuilder
{
    protected $group;

    public function setGroup($group)
    {
        $this->group = $group;

        return $this;
    }

    public function getPictureUrl($item, $picture)
    {
        return $this->router->assemble([
            'id'         => $this->group['id'],
            'picture_id' => $picture['identity']
        ], [
            'name' => 'twins/group/pictures/picture'
        ]);
    }
}
