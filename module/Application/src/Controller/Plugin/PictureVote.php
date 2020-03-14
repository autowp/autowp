<?php

namespace Application\Controller\Plugin;

use Application\Model\Picture;
use Application\Model\PictureModerVote;
use ArrayAccess;
use Autowp\User\Model\User;
use Exception;
use Laminas\Db\Sql;
use Laminas\Db\TableGateway\TableGateway;
use Laminas\Mvc\Controller\Plugin\AbstractPlugin;

use function array_replace;

class PictureVote extends AbstractPlugin
{
    private TableGateway $voteTemplateTable;

    private PictureModerVote $pictureModerVote;

    private Picture $picture;

    private User $userModel;

    public function __construct(
        PictureModerVote $pictureModerVote,
        TableGateway $voteTemplateTable,
        Picture $picture,
        User $userModel
    ) {
        $this->voteTemplateTable = $voteTemplateTable;
        $this->pictureModerVote  = $pictureModerVote;
        $this->picture           = $picture;
        $this->userModel         = $userModel;
    }

    /**
     * @param array|ArrayAccess $picture
     * @throws Exception
     */
    private function isLastPicture($picture): ?bool
    {
        if ($picture['status'] !== Picture::STATUS_ACCEPTED) {
            return null;
        }

        return ! $this->picture->isExists([
            'id_exclude' => $picture['id'],
            'status'     => Picture::STATUS_ACCEPTED,
            'item'       => [
                'contains_picture' => $picture['id'],
            ],
        ]);
    }

    private function getAcceptedCount(int $pictureId): int
    {
        return $this->picture->getCount([
            'status' => Picture::STATUS_ACCEPTED,
            'item'   => [
                'contains_picture' => $pictureId,
            ],
        ]);
    }

    private function getVoteOptions2(): array
    {
        $result = [
            'positive' => [],
            'negative' => [],
        ];

        /* @phan-suppress-next-line PhanUndeclaredMethod */
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

    public function __invoke(int $pictureId, array $options): ?array
    {
        $options = array_replace([
            'hideVote' => false,
        ], $options);

        $picture = $this->picture->getRow(['id' => $pictureId]);
        if (! $picture) {
            return null;
        }

        $controller = $this->getController();

        /* @phan-suppress-next-line PhanUndeclaredMethod */
        if (! $controller->user()->inheritsRole('moder')) {
            return null;
        }

        /* @phan-suppress-next-line PhanUndeclaredMethod */
        $user       = $controller->user()->get();
        $voteExists = $this->pictureModerVote->hasVote($picture['id'], $user['id']);

        $moderVotes = null;
        if (! $options['hideVote']) {
            $moderVotes = [];
            foreach ($this->pictureModerVote->getVotes($picture['id']) as $vote) {
                $moderVotes[] = [
                    'vote'   => $vote['vote'],
                    'reason' => $vote['reason'],
                    'user'   => $this->userModel->getRow((int) $vote['user_id']),
                ];
            }
        }

        return [
            'isLastPicture' => $this->isLastPicture($picture),
            'acceptedCount' => $this->getAcceptedCount($picture['id']),
            'canDelete'     => $this->pictureCanDelete($picture),
            'apiUrl'        => $controller->url()->fromRoute('api/picture/picture/update', [
                'id' => $picture['id'],
            ]),
            /* @phan-suppress-next-line PhanUndeclaredMethod */
            'canVote'     => ! $voteExists && $controller->user()->isAllowed('picture', 'moder_vote'),
            'voteExists'  => $voteExists,
            'moderVotes'  => $moderVotes,
            'voteOptions' => $this->getVoteOptions2(),
            'voteUrl'     => $controller->url()->fromRoute('api/picture-moder-vote', [
                'id' => $picture['id'],
            ]),
        ];
    }

    /**
     * @param array|ArrayAccess $picture
     */
    private function pictureCanDelete($picture): bool
    {
        if (! $this->picture->canDelete($picture)) {
            return false;
        }

        $canDelete = false;
        /* @phan-suppress-next-line PhanUndeclaredMethod */
        $user = $this->getController()->user()->get();
        /* @phan-suppress-next-line PhanUndeclaredMethod */
        if ($this->getController()->user()->isAllowed('picture', 'remove')) {
            if ($this->pictureModerVote->hasVote($picture['id'], $user['id'])) {
                $canDelete = true;
            }
        /* @phan-suppress-next-line PhanUndeclaredMethod */
        } elseif ($this->getController()->user()->isAllowed('picture', 'remove_by_vote')) {
            if ($this->pictureModerVote->hasVote($picture['id'], $user['id'])) {
                $acceptVotes = $this->pictureModerVote->getPositiveVotesCount($picture['id']);
                $deleteVotes = $this->pictureModerVote->getNegativeVotesCount($picture['id']);

                $canDelete = $deleteVotes > $acceptVotes;
            }
        }

        return $canDelete;
    }
}
