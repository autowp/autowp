<?php

use Application\Db\Table\Row;

class Attrs_Attributes_Row extends Row
{
    public function isMayBeMultiple()
    {
        return in_array($this->type_id, array(6, 7));
    }

    public function isMultiple()
    {
        return $this->multiple && $this->isMayBeMultiple();
    }
}