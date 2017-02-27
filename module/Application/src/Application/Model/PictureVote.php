<?php

namespace Application\Model;

use Zend\Db\Adapter\Adapter;
use Zend\Db\Sql;
use Zend\Db\TableGateway\TableGateway;

class PictureVote
{
    /**
     * @var TableGateway
     */
    private $voteTable;

    /**
     * @var TableGateway
     */
    private $summaryTable;

    public function __construct(Adapter $adapter)
    {
        $this->voteTable = new TableGateway('picture_vote', $adapter);
        $this->summaryTable = new TableGateway('picture_vote_summary', $adapter);
    }

    public function vote($pictureId, $userId, $value)
    {
        $value = $value > 0 ? 1 : -1;

        $sql = '
            insert into picture_vote (picture_id, user_id, value, timestamp)
            values (?, ?, ?, now())
            on duplicate key update
                value = VALUES(value),
                timestamp = VALUES(timestamp)
        ';

        $statement = $this->voteTable->getAdapter()->query($sql);
        $statement->execute([(int)$pictureId, (int)$userId, $value]);

        $this->updatePictureSummary($pictureId);
    }

    public function getVote($pictureId, $userId)
    {
        $row = null;
        if ($userId) {
            $row = $this->voteTable->select([
                'picture_id' => (int)$pictureId,
                'user_id'    => (int)$userId,
            ])->current();
        }

        $summary = $this->summaryTable->select([
            'picture_id' => (int)$pictureId
        ])->current();

        return [
            'value'    => $row ? $row['value'] : null,
            'positive' => $summary ? $summary['positive'] : 0,
            'negative' => $summary ? $summary['negative'] : 0
        ];
    }

    private function updatePictureSummary($pictureId)
    {
        $pictureId = (int)$pictureId;

        $sql = '
            insert into picture_vote_summary (picture_id, positive, negative) 
            values (
                ?, 
                (select count(1) from picture_vote where picture_id = ? and value > 0), 
                (select count(1) from picture_vote where picture_id = ? and value < 0)
            )
            on duplicate key update
                positive = VALUES(positive),
                negative = VALUES(negative)
        ';
        $statement = $this->summaryTable->getAdapter()->query($sql);
        $statement->execute([$pictureId, $pictureId, $pictureId]);
    }
}
