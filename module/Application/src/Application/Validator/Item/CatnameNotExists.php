<?php

namespace Application\Validator\Item;

use Zend\Validator\AbstractValidator;
use Application\Model\Item;

class CatnameNotExists extends AbstractValidator
{
    private const EXISTS = 'itemCatnameAlreadyExists';

    protected $messageTemplates = [
        self::EXISTS => "Catname '%value%' already exists"
    ];

    private $exclude;

    /**
     * @var Item
     */
    private $item;

    public function setItem(Item $item)
    {
        $this->item = $item;

        return $this;
    }

    public function setExclude($exclude)
    {
        $this->exclude = (int)$exclude;

        return $this;
    }

    public function isValid($value)
    {
        $this->setValue($value);

        $row = $this->item->getRow([
            'catname'    => (string)$value,
            'exclude_id' => (int)$this->exclude
        ]);

        if ($row) {
            $this->error(self::EXISTS);
            return false;
        }
        return true;
    }
}
