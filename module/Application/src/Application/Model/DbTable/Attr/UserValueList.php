<?php

namespace Application\Model\DbTable\Attr;

class UserValueList extends UserValueAbstract
{
    protected $_name = 'attrs_user_values_list';
    protected $_primary = ['attribute_id', 'item_id', 'user_id', 'ordering'];
}
