<?php

namespace Application\Service;

use Autowp\Image;
use Autowp\User\Model\User;
use Exception;
use Laminas\Db\Sql;
use Laminas\Db\TableGateway\TableGateway;

use const PHP_EOL;

class UsersService
{
    private Image\Storage $imageStorage;

    private User $userModel;

    private TableGateway $logEventUserTable;

    public function __construct(
        Image\Storage $imageStorage,
        User $userModel,
        TableGateway $logEventUserTable
    ) {
        $this->imageStorage      = $imageStorage;
        $this->userModel         = $userModel;
        $this->logEventUserTable = $logEventUserTable;
    }

    /**
     * @throws Exception
     */
    public function deleteUnused(): void
    {
        $table = $this->userModel->getTable();

        $rows = $table->selectWith(
            $table->getSql()->select()
                ->join('attrs_user_values', 'users.id = attrs_user_values.user_id', [], Sql\Select::JOIN_LEFT)
                ->join('comment_message', 'users.id = comment_message.author_id', [], Sql\Select::JOIN_LEFT)
                ->join('forums_topics', 'users.id = forums_topics.author_id', [], Sql\Select::JOIN_LEFT)
                ->join('pictures', 'users.id = pictures.owner_id', [], Sql\Select::JOIN_LEFT)
                ->join('voting_variant_vote', 'users.id = voting_variant_vote.user_id', [], Sql\Select::JOIN_LEFT)
                ->join(['pmf' => 'personal_messages'], 'users.id = pmf.from_user_id', [], Sql\Select::JOIN_LEFT)
                ->join(['pmt' => 'personal_messages'], 'users.id = pmt.to_user_id', [], Sql\Select::JOIN_LEFT)
                ->join('log_events', 'users.id = log_events.user_id', [], Sql\Select::JOIN_LEFT)
                ->where([
                    'users.last_online < DATE_SUB(NOW(), INTERVAL 2 YEAR)',
                    'users.role' => 'user',
                    'attrs_user_values.user_id is null',
                    'comment_message.author_id is null',
                    'forums_topics.author_id is null',
                    'pictures.owner_id is null',
                    'voting_variant_vote.user_id is null',
                    'pmf.from_user_id is null',
                    'pmt.to_user_id is null',
                    'log_events.user_id is null',
                ])
                ->order('users.id')
                ->limit(1000)
        );

        foreach ($rows as $row) {
            print 'Delete ' . $row['id'] . ' ' . $row['name'] . ' ' . PHP_EOL;

            $this->delete($row['id']);
        }
    }

    /**
     * @throws Exception
     */
    private function delete(int $userId): void
    {
        $row = $this->userModel->getRow($userId);
        if (! $row) {
            return;
        }

        $imageId = null;
        if ($row['img']) {
            $imageId = $row['img'];
        }

        $this->logEventUserTable->delete([
            'user_id' => $userId,
        ]);

        $this->userModel->getTable()->delete([
            'id' => $userId,
        ]);

        if ($imageId) {
            $this->imageStorage->removeImage($imageId);
        }
    }
}
