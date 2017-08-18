<?php

namespace Application\Controller\Plugin;

use Zend\Db\Sql;
use Zend\Db\TableGateway\TableGateway;
use Zend\Mvc\Controller\Plugin\AbstractPlugin;

use Autowp\User\Model\DbTable\User;

use Application\Model\Picture;
use Application\Model\PictureModerVote;

class PictureVote extends AbstractPlugin
{
    /**
     * @var TableGateway
     */
    private $voteTemplateTable;

    /**
     * @var PictureModerVote
     */
    private $pictureModerVote;

    /**
     * @var Picture
     */
    private $picture;

    public function __construct(
        PictureModerVote $pictureModerVote,
        TableGateway $voteTemplateTeable,
        Picture $picture
    ) {
        $this->voteTemplateTable = $voteTemplateTeable;
        $this->pictureModerVote = $pictureModerVote;
        $this->picture = $picture;
    }

    private function isLastPicture($picture)
    {
        if ($picture['status'] != Picture::STATUS_ACCEPTED) {
            return null;
        }

        return $this->picture->isExists([
            'id_exclude' => $picture['id'],
            'status'     => Picture::STATUS_ACCEPTED,
            'item'       => [
                'contains_picture' => $picture['id']
            ]
        ]);
    }

    private function getAcceptedCount(int $pictureId)
    {
        return $this->picture->getCount([
            'status' => Picture::STATUS_ACCEPTED,
            'item'   => [
                'contains_picture' => $pictureId
            ]
        ]);
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

    public function __invoke(int $pictureId, $options)
    {
        $options = array_replace([
            'hideVote' => false
        ], $options);

        $picture = $this->picture->getRow(['id' => $pictureId]);
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
            'acceptedCount'     => $this->getAcceptedCount($picture['id']),
            'canDelete'         => $this->pictureCanDelete($picture),
            'apiUrl'            => $controller->url()->fromRoute('api/picture/picture/update', [
                'id' => $picture['id']
            ]),
            'canVote'           => ! $voteExists && $controller->user()->isAllowed('picture', 'moder_vote'),
            'voteExists'        => $voteExists,
            'moderVotes'        => $moderVotes,
            'voteOptions' => $this->getVoteOptions2(),
            'voteUrl' => $controller->url()->fromRoute('api/picture-moder-vote', [
                'id' => $picture['id']
            ]),
        ];
    }

    private function pictureCanDelete($picture)
    {
        if (! $this->picture->canDelete($picture)) {
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
