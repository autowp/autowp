<?php

namespace Application\Model;

use Exception;
use Laminas\Db\TableGateway\TableGateway;

use function Autowp\Commons\currentFromResultSetInterface;

class PictureVote
{
    private TableGateway $voteTable;

    private TableGateway $summaryTable;

    public function __construct(TableGateway $voteTable, TableGateway $summaryTable)
    {
        $this->voteTable    = $voteTable;
        $this->summaryTable = $summaryTable;
    }

    /**
     * @throws Exception
     */
    public function getVote(int $pictureId, int $userId): array
    {
        $row = null;
        if ($userId) {
            $row = currentFromResultSetInterface($this->voteTable->select([
                'picture_id' => $pictureId,
                'user_id'    => $userId,
            ]));
        }

        $summary = currentFromResultSetInterface($this->summaryTable->select([
            'picture_id' => $pictureId,
        ]));

        return [
            'value'    => $row ? $row['value'] : null,
            'positive' => $summary ? $summary['positive'] : 0,
            'negative' => $summary ? $summary['negative'] : 0,
        ];
    }
}
