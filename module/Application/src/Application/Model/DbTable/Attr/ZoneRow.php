<?php

namespace Application\Model\DbTable\Attr;

use Application\Db\Table\Row;

class ZoneRow extends Row
{
    public function hasAttribute(Row $attribute)
    {
        $attributes = new ZoneAttribute();
        return (bool)$attributes->getAdapter()->fetchOne(
            $attributes->select()
                ->from($attributes, 'COUNT(*)')
                ->where('zone_id = ?', $this->id)
                ->where('attribute_id = ?', $attribute->id)
        );
    }

    public function findAttributes(Row $parent = null)
    {
        $table = new Attribute();
        $select = $table->select()
            ->from($table)
            ->join('attrs_zone_attributes', 'attrs_zone_attributes.attribute_id=attrs_attributes.id', null)
            ->where('attrs_zone_attributes.zone_id = ?', $this->id)
            ->order('attrs_zone_attributes.position');

        if ($parent)
            $select->where('attrs_attributes.parent_id = ?', $parent->id);
        else
            $select->where('attrs_attributes.parent_id IS NULL');

        return $table->fetchAll($select);
    }
}