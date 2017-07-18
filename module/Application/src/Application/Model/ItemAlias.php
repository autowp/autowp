<?php

namespace Application\Model;

use Zend\Db\TableGateway\TableGateway;

class ItemAlias
{
    /**
     * @var TableGateway
     */
    private $table;

    public function __construct(TableGateway $table, TableGateway $itemLanguageTable)
    {
        $this->table = $table;
        $this->itemLanguageTable = $itemLanguageTable;
    }

    public function getAliases(int $itemId, string $name): array
    {
        $aliases = [$name];

        $rows = $this->table->select([
            'item_id' => $itemId
        ]);
        foreach ($rows as $row) {
            if ($row['name']) {
                $aliases[] = $row['name'];
            }
        }

        $langRows = $this->itemLanguageTable->select([
            'item_id' => $itemId
        ]);
        foreach ($langRows as $langRow) {
            if ($langRow['name']) {
                $aliases[] = $langRow['name'];
            }
        }

        usort($aliases, function ($a, $b) {
            $la = mb_strlen($a);
            $lb = mb_strlen($b);

            if ($la == $lb) {
                return 0;
            }
            return ($la > $lb) ? -1 : 1;
        });

        return $aliases;
    }
}
