<?php

namespace Application\Controller\Plugin;

use Zend\Db\Sql;
use Zend\Db\TableGateway\TableGateway;
use Zend\Mvc\Controller\Plugin\AbstractPlugin;

use Autowp\User\Model\DbTable\User;

use Application\Model\DbTable;
use Application\Model\PictureModerVote;

use Zend_Db_Expr;
use Application\Model\Picture;

class PictureVote extends AbstractPlugin
{
    /**
     * @var DbTable\Picture
     */
    private $table;

    /**
     * @var TableGateway
     */
    private $voteTemplateTable;

    /**
     * @var PictureModerVote
     */
    private $pictureModerVote;

    public function __construct(
        PictureModerVote $pictureModerVote,
        DbTable\Picture $pictureTable,
        TableGateway $voteTemplateTeable
    ) {
        $this->table = $pictureTable;
        $this->voteTemplateTable = $voteTemplateTeable;
        $this->pictureModerVote = $pictureModerVote;
    }

    private function isLastPicture($picture)
    {
        $result = null;
        if ($picture->status == Picture::STATUS_ACCEPTED) {
            $db = $this->table->getAdapter();
            $result = ! $db->fetchOne(
                $db->select()
                    ->from('pictures', [new Zend_Db_Expr('COUNT(1)')])
                    ->join('picture_item', 'pictures.id = picture_item.picture_id', null)
                    ->join(
                        ['pi2' => 'picture_item'],
                        'picture_item.item_id = pi2.item_id',
                        null
                    )
                    ->where('pi2.picture_id = ?', $picture->id)
                    ->where('pictures.status = ?', Picture::STATUS_ACCEPTED)
                    ->where('pictures.id <> ?', $picture->id)
            );
        }

        return $result;
    }

    private function getAcceptedCount($picture)
    {
        $result = null;

        $db = $this->table->getAdapter();
        $result = $db->fetchOne(
            $db->select()
                ->from('pictures', [new Zend_Db_Expr('COUNT(1)')])
                ->join('picture_item', 'pictures.id = picture_item.picture_id', null)
                ->join(
                    ['pi2' => 'picture_item'],
                    'picture_item.item_id = pi2.item_id',
                    null
                )
                ->where('pi2.picture_id = ?', $picture->id)
                ->where('status = ?', Picture::STATUS_ACCEPTED)
        );

        return $result;
    }

    private function getVoteOptions2()
    {
        $result = [
            'positive' => [],
            'negative' => []
        ];

        $user = $this->getController()->user()->get();

        if ($user) {
            $select = new Sql\Select($this->voteTemplateTable->getTable());
            $select
                ->columns(['reason', 'vote'])
                ->where(['user_id' => $user['id']])
                ->order('reason');

            foreach ($this->voteTemplateTable->selectWith($select) as $row) {
                $result[$row['vote'] > 0 ? 'positive' : 'negative'][] = $row['reason'];
            }
        }

        return $result;
    }

    public function __invoke($pictureId, $options)
    {
        $options = array_replace([
            'hideVote' => false
        ], $options);

        $picture = $this->table->find($pictureId)->current();
        if (! $picture) {
            return false;
        }

        $controller = $this->getController();

        if (! $controller->user()->inheritsRole('moder')) {
            return false;
        }

        $user = $controller->user()->get();
        $voteExists = $this->pictureModerVote->hasVote($picture['id'], $user['id']);

        $moderVotes = null;
        if (! $options['hideVote']) {
            $moderVotes = [];
            $userTable = new User();
            foreach ($this->pictureModerVote->getVotes($picture['id']) as $vote) {
                $moderVotes[] = [
                    'vote'   => $vote['vote'],
                    'reason' => $vote['reason'],
                    'user'   => $userTable->find($vote['user_id'])->current()
                ];
            }
        }

        return [
            'isLastPicture'     => $this->isLastPicture($picture),
            'acceptedCount'     => $this->getAcceptedCount($picture),
            'canDelete'         => $this->pictureCanDelete($picture),
            'apiUrl'            => $controller->url()->fromRoute('api/picture/picture/update', [
                'id' => $picture->id
            ]),
            'canVote'           => ! $voteExists && $controller->user()->isAllowed('picture', 'moder_vote'),
            'voteExists'        => $voteExists,
            'moderVotes'        => $moderVotes,
            'voteOptions' => $this->getVoteOptions2(),
            'voteUrl' => $controller->url()->fromRoute('api/picture-moder-vote', [
                'id' => $picture->id
            ]),
        ];
    }

    private function pictureCanDelete($picture)
    {
        if (! $this->table->canDelete($picture)) {
            return false;
        }

        $canDelete = false;
        $user = $this->getController()->user()->get();
        if ($this->getController()->user()->isAllowed('picture', 'remove')) {
            if ($this->pictureModerVote->hasVote($picture['id'], $user['id'])) {
                $canDelete = true;
            }
        } elseif ($this->getController()->user()->isAllowed('picture', 'remove_by_vote')) {
            if ($this->pictureModerVote->hasVote($picture['id'], $user['id'])) {
                $acceptVotes = $this->pictureModerVote->getPositiveVotesCount($picture['id']);
                $deleteVotes = $this->pictureModerVote->getNegativeVotesCount($picture['id']);

                $canDelete = ($deleteVotes > $acceptVotes);
            }
        }

        return $canDelete;
    }
}
