<?php

namespace Application\Filter\Api;

use Laminas\Filter\AbstractFilter;
use Laminas\Stdlib\ArrayUtils;

use function explode;
use function is_string;
use function strpos;
use function substr;

class FieldsFilter extends AbstractFilter
{
    protected array $fields = [];

    public function setFields(array $fields): self
    {
        $this->fields = $fields;

        return $this;
    }

    private function parseValue(string $value): array
    {
        $dotPos = strpos($value, '.');
        if ($dotPos !== false) {
            $fieldName  = (string) substr($value, 0, $dotPos);
            $fieldValue = $this->parseValue(substr($value, $dotPos + 1));
        } else {
            $fieldName  = $value;
            $fieldValue = true;
        }

        return [
            $fieldName => $fieldValue,
        ];
    }

    /**
     * @param mixed $value
     */
    public function filter($value): array
    {
        $value = is_string($value) ? $value : '';

        $result = [];
        foreach (explode(',', $value) as $field) {
            $pair = $this->parseValue($field);

            $result = ArrayUtils::merge($result, $pair);
        }

        return $result;
    }
}
