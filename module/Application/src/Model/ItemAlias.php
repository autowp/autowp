<?php

namespace Application\Model;

use Laminas\Db\TableGateway\TableGateway;

use function mb_strlen;
use function usort;

class ItemAlias
{
    private TableGateway $table;

    private Item $itemModel;

    public function __construct(TableGateway $table, Item $itemModel)
    {
        $this->table     = $table;
        $this->itemModel = $itemModel;
    }

    public function getAliases(int $itemId): array
    {
        $aliases = [];

        $rows = $this->table->select([
            'item_id' => $itemId,
        ]);
        foreach ($rows as $row) {
            if ($row['name']) {
                $aliases[] = $row['name'];
            }
        }

        $langNames = $this->itemModel->getNames($itemId);
        foreach ($langNames as $langName) {
            $aliases[] = $langName;
        }

        usort($aliases, function ($a, $b) {
            $la = mb_strlen($a);
            $lb = mb_strlen($b);

            if ($la === $lb) {
                return 0;
            }
            return $la > $lb ? -1 : 1;
        });

        return $aliases;
    }
}
