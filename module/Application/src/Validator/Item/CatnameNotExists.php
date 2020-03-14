<?php

namespace Application\Validator\Item;

use Application\Model\Item;
use Exception;
use Laminas\Validator\AbstractValidator;

class CatnameNotExists extends AbstractValidator
{
    private const EXISTS = 'itemCatnameAlreadyExists';

    protected array $messageTemplates = [
        self::EXISTS => "Catname '%value%' already exists",
    ];

    private int $exclude;

    private Item $item;

    public function setItem(Item $item): self
    {
        $this->item = $item;

        return $this;
    }

    public function setExclude(?int $exclude): self
    {
        $this->exclude = (int) $exclude;

        return $this;
    }

    /**
     * @param mixed $value
     * @throws Exception
     */
    public function isValid($value): bool
    {
        $this->setValue($value);

        $row = $this->item->getRow([
            'catname'    => (string) $value,
            'exclude_id' => $this->exclude,
        ]);

        if ($row) {
            $this->error(self::EXISTS);
            return false;
        }
        return true;
    }
}
