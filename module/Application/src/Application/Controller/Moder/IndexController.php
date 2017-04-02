<?php

namespace Application\Controller\Moder;

use Zend\Mvc\Controller\AbstractActionController;

use Application\Model\DbTable;

class IndexController extends AbstractActionController
{
    public function tooBigCarsAction()
    {
        if (! $this->user()->inheritsRole('moder')) {
            return $this->forbiddenAction();
        }

        $itemTable = new DbTable\Item();

        $rows = $itemTable->getAdapter()->fetchAll("
            SELECT
                item.id, item.name, item.body,
                (
                    case item_parent.type
                        when 0 then 'Stock'
                        when 1 then 'Related'
                        when 2 then 'Sport'
                        else item_parent.type
                    end
                ) as t,
                count(1) as c
            from item_parent
            join item on item_parent.parent_id=item.id
            group by item.id, item_parent.type
            order by c desc
                limit 100
        ");

        return [
            'rows' => $rows
        ];
    }
}
