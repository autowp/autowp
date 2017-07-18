<?php

namespace Application\Validator\ItemParent;

use Zend\Validator\AbstractValidator;

use Application\Model\ItemParent;

class CatnameNotExists extends AbstractValidator
{
    const EXISTS = 'itemParentCatnameAlreadyExists';

    protected $messageTemplates = [
        self::EXISTS => "Item parent catname '%value%' already exists"
    ];

    private $parentId;

    private $ignoreItemId;

    /**
     * @var ItemParent
     */
    private $itemParent;

    public function setItemParent(ItemParent $itemParent)
    {
        $this->itemParent = $itemParent;

        return $this;
    }

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

        $row = $this->itemParent->getRowByCatname($this->parentId, $value);

        if ($this->ignoreItemId && $row['item_id'] == $this->ignoreItemId) {
            $row = null;
        }

        if ($row) {
            $this->error(self::EXISTS);
            return false;
        }
        return true;
    }
}
