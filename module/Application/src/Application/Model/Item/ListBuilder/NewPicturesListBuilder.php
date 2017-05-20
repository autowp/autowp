<?php

namespace Application\Model\Item\ListBuilder;

use Application\Model\DbTable;

class NewPicturesListBuilder extends \Application\Model\Item\ListBuilder
{
    /**
     * @var string
     */
    protected $date;

    /**
     * @var array
     */
    protected $pictureIds = [];

    public function setDate($date)
    {
        $this->date = $date;

        return $this;
    }

    public function setPictureIds($pictureIds)
    {
        $this->pictureIds = $pictureIds;

        return $this;
    }

    public function getPicturesUrl(DbTable\Item\Row $item)
    {
        return $this->router->assemble([
            'action'        => 'index',
            'date'          => $this->date,
            'item_id'       => $item['id'],
            'page'          => null
        ], [
            'name' => 'new/date/item'
        ]);
    }
}
