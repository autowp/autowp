<?php

namespace Application\Model;

use Laminas\Db\Adapter\Adapter;

use function array_merge;
use function implode;

class LanguagePriority
{
    private array $values = [
        'xx'    => ['en', 'it', 'fr', 'de', 'es', 'pt', 'ru', 'be', 'uk', 'zh', 'jp', 'he', 'xx'],
        'en'    => ['en', 'it', 'fr', 'de', 'es', 'pt', 'ru', 'be', 'uk', 'zh', 'jp', 'he', 'xx'],
        'fr'    => ['fr', 'en', 'it', 'de', 'es', 'pt', 'ru', 'be', 'uk', 'zh', 'jp', 'he', 'xx'],
        'pt-br' => ['pt', 'en', 'it', 'fr', 'de', 'es', 'ru', 'be', 'uk', 'zh', 'jp', 'he', 'xx'],
        'ru'    => ['ru', 'en', 'it', 'fr', 'de', 'es', 'pt', 'be', 'uk', 'zh', 'jp', 'he', 'xx'],
        'be'    => ['be', 'ru', 'uk', 'en', 'it', 'fr', 'de', 'es', 'pt', 'zh', 'jp', 'he', 'xx'],
        'uk'    => ['uk', 'ru', 'en', 'it', 'fr', 'de', 'es', 'pt', 'be', 'zh', 'jp', 'he', 'xx'],
        'zh'    => ['zh', 'en', 'it', 'fr', 'de', 'es', 'pt', 'ru', 'be', 'uk', 'jp', 'he', 'xx'],
        'it'    => ['it', 'en', 'fr', 'de', 'es', 'pt', 'ru', 'be', 'uk', 'zh', 'jp', 'he', 'xx'],
        'he'    => ['he', 'en', 'it', 'fr', 'de', 'es', 'pt', 'ru', 'be', 'uk', 'zh', 'jp', 'xx'],
    ];

    public function getList(string $language): array
    {
        if (isset($this->values[$language])) {
            $result = $this->values[$language];
            if ($result[0] !== $language) {
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
        return '
            SELECT name
            FROM item_language
            WHERE item_id = item.id AND length(name) > 0
            ORDER BY ' . $this->getOrderByExpression($language, $adapter) . '
            LIMIT 1
        ';
    }
}
