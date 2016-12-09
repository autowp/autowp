<?php

namespace Application\Model\DbTable\Attr;

class ValueList extends ValueAbstract
{
    protected $_name = 'attrs_values_list';
    protected $_primary = ['attribute_id', 'item_id', 'ordering'];
}
