<?php

namespace Application\Validator\Attr;

use Laminas\Db\TableGateway\TableGateway;
use Laminas\Validator\AbstractValidator;

class TypeId extends AbstractValidator
{
    private const INVALID = 'invalidTypeId';

    protected array $messageTemplates = [
        self::INVALID => "Type is invalid",
    ];

    private TableGateway $table;

    public function setTable(TableGateway $table): self
    {
        $this->table = $table;

        return $this;
    }

    /**
     * @param mixed $value
     */
    public function isValid($value): bool
    {
        $this->setValue($value);

        $row = $this->table->select([
            'id' => (int) $value,
        ])->current();

        if (! $row) {
            $this->error(self::INVALID);
            return false;
        }
        return true;
    }
}
