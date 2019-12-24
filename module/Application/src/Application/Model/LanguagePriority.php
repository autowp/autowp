<?php

namespace Application\Model;

use Zend\Db\Adapter\Adapter;

class LanguagePriority
{
    private $values = [
        'xx'    => ['en', 'it', 'fr', 'de', 'es', 'pt', 'ru', 'be', 'uk', 'zh', 'xx'],
        'en'    => ['en', 'it', 'fr', 'de', 'es', 'pt', 'ru', 'be', 'uk', 'zh', 'xx'],
        'fr'    => ['fr', 'en', 'it', 'de', 'es', 'pt', 'ru', 'be', 'uk', 'zh', 'xx'],
        'pt-br' => ['pt', 'en', 'it', 'fr', 'de', 'es', 'ru', 'be', 'uk', 'zh', 'xx'],
        'ru'    => ['en', 'it', 'fr', 'de', 'es', 'pt', 'ru', 'be', 'uk', 'zh', 'xx'],
        'be'    => ['be', 'ru', 'uk', 'en', 'it', 'fr', 'de', 'es', 'pt', 'zh', 'xx'],
        'uk'    => ['uk', 'ru', 'en', 'it', 'fr', 'de', 'es', 'pt', 'be', 'zh', 'xx'],
        'zh'    => ['en', 'it', 'fr', 'de', 'es', 'pt', 'ru', 'be', 'uk', 'zh', 'xx'],
    ];

    public function getList(string $language): array
    {
        if (isset($this->values[$language])) {
            $result = $this->values[$language];
            if ($result[0] != $language) {
                $result = array_merge([$language], $result);
            }
            return $result;
        }

        return array_merge([$language], $this->values['xx']);
    }

    public function getOrderByExpression(string $language, Adapter $adapter): string
    {
        $languages = $this->getList($language);

        $quoted = [];
        foreach ($languages as $lang) {
            $quoted[] = $adapter->platform->quoteValue($lang);
        }

        return 'FIELD(language, ' . implode(', ', $quoted) . ')';
    }

    public function getSelectItemName(string $language, Adapter $adapter): string
    {
        $languages = $this->getList($language);

        $quoted = [];
        foreach ($languages as $lang) {
            $quoted[] = $adapter->platform->quoteValue($lang);
        }

        return '
            SELECT name
            FROM item_language
            WHERE item_id = item.id AND length(name) > 0
            ORDER BY ' . $this->getOrderByExpression($language, $adapter) . '
            LIMIT 1
        ';
    }
}
