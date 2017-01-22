<?php

namespace Application\Model\DbTable\Attr;

use Autowp\Commons\Db\Table\Row;

class AttributeRow extends Row
{
    public function isMayBeMultiple()
    {
        return in_array($this->type_id, [6, 7]);
    }

    public function isMultiple()
    {
        return $this->multiple && $this->isMayBeMultiple();
    }
}
