<?php

class Attrs_Attributes_Row extends Project_Db_Table_Row
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