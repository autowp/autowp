<?php

namespace Application\Validator\ItemParent;

use Zend\Validator\AbstractValidator;

use Application\Model\DbTable;

class CatnameNotExists extends AbstractValidator
{
    const EXISTS = 'itemParentCatnameAlreadyExists';

    protected $messageTemplates = [
        self::EXISTS => "Item parent catname '%value%' already exists"
    ];

    private $parentId;

    private $ignoreItemId;

    public function setParentId($parentId)
    {
        $this->parentId = $parentId;

        return $this;
    }

    public function setIgnoreItemId($ignoreItemId)
    {
        $this->ignoreItemId = $ignoreItemId;

        return $this;
    }

    public function isValid($value)
    {
        $this->setValue($value);

        $filter = [
            'parent_id = ?' => (int)$this->parentId,
            'catname = ?'   => (string)$value
        ];
        if ($this->ignoreItemId) {
            $filter['item_id <> ?'] = $this->ignoreItemId;
        }

        $table = new DbTable\Item\ParentTable();
        $row = $table->fetchRow($filter);
        if ($row) {
            $this->error(self::EXISTS);
            return false;
        }
        return true;
    }
}
