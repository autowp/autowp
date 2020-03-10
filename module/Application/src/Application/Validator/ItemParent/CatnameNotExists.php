<?php

namespace Application\Validator\ItemParent;

use Application\Model\ItemParent;
use Laminas\Validator\AbstractValidator;

class CatnameNotExists extends AbstractValidator
{
    private const EXISTS = 'itemParentCatnameAlreadyExists';

    protected array $messageTemplates = [
        self::EXISTS => "Item parent catname '%value%' already exists",
    ];

    private int $parentId;

    private int $ignoreItemId;

    private ItemParent $itemParent;

    public function setItemParent(ItemParent $itemParent): self
    {
        $this->itemParent = $itemParent;

        return $this;
    }

    public function setParentId(int $parentId): self
    {
        $this->parentId = $parentId;

        return $this;
    }

    public function setIgnoreItemId(int $ignoreItemId): self
    {
        $this->ignoreItemId = $ignoreItemId;

        return $this;
    }

    /**
     * @param mixed $value
     */
    public function isValid($value): bool
    {
        $this->setValue($value);

        $row = $this->itemParent->getRowByCatname($this->parentId, $value);

        if ($this->ignoreItemId && $row['item_id'] === $this->ignoreItemId) {
            $row = null;
        }

        if ($row) {
            $this->error(self::EXISTS);
            return false;
        }
        return true;
    }
}
