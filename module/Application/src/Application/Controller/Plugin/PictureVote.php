<?php

namespace Application\Controller\Plugin;

use Zend\Db\Adapter\Adapter;
use Zend\Db\Sql;
use Zend\Db\TableGateway\TableGateway;
use Zend\Mvc\Controller\Plugin\AbstractPlugin;

use Application\Model\DbTable;

use Zend_Db_Expr;

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

    public function __construct(Adapter $adapter)
    {
        $this->table = new DbTable\Picture();
        $this->voteTemplateTable = new TableGateway('picture_moder_vote_template', $adapter);
    }

    private function isLastPicture($picture)
    {
        $result = null;
        if ($picture->status == DbTable\Picture::STATUS_ACCEPTED) {
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
                    ->where('pictures.status = ?', DbTable\Picture::STATUS_ACCEPTED)
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
                ->where('status = ?', DbTable\Picture::STATUS_ACCEPTED)
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
        $voteExists = $this->pictureVoteExists($picture, $user);

        return [
            'isLastPicture'     => $this->isLastPicture($picture),
            'acceptedCount'     => $this->getAcceptedCount($picture),
            'canDelete'         => $this->pictureCanDelete($picture),
            'canVote'           => ! $voteExists && $controller->user()->isAllowed('picture', 'moder_vote'),
            'voteExists'        => $voteExists,
            'moderVotes'        => $options['hideVote']
                ? null
                : $picture->findDependentRowset(DbTable\Picture\ModerVote::class),
            'pictureDeleteUrl'  => $controller->url()->fromRoute('moder/pictures/params', [
                'action'     => 'delete-picture',
                'picture_id' => $picture->id
            ]),
            'pictureUnvoteUrl'  => $controller->url()->fromRoute('moder/pictures/params', [
                'action'     => 'picture-vote',
                'form'       => 'picture-unvote',
                'picture_id' => $picture->id
            ]),
            'voteOptions' => $this->getVoteOptions2(),
            'voteUrl' => $controller->url()->fromRoute('api/picture-moder-vote', [
                'id' => $picture->id
            ]),
        ];
    }

    private function pictureCanDelete($picture)
    {
        $canDelete = false;
        if ($picture->canDelete()) {
            $user = $this->getController()->user()->get();
            if ($this->getController()->user()->isAllowed('picture', 'remove')) {
                if ($this->pictureVoteExists($picture, $user)) {
                    $canDelete = true;
                }
            } elseif ($this->getController()->user()->isAllowed('picture', 'remove_by_vote')) {
                if ($this->pictureVoteExists($picture, $user)) {
                    $db = $this->table->getAdapter();
                    $acceptVotes = (int)$db->fetchOne(
                        $db->select()
                            ->from('pictures_moder_votes', [new Zend_Db_Expr('COUNT(1)')])
                            ->where('picture_id = ?', $picture->id)
                            ->where('vote > 0')
                    );
                    $deleteVotes = (int)$db->fetchOne(
                        $db->select()
                            ->from('pictures_moder_votes', [new Zend_Db_Expr('COUNT(1)')])
                            ->where('picture_id = ?', $picture->id)
                            ->where('vote = 0')
                    );

                    $canDelete = ($deleteVotes > $acceptVotes);
                }
            }
        }

        return $canDelete;
    }

    private function pictureVoteExists($picture, $user)
    {
        $db = $this->table->getAdapter();
        return $db->fetchOne(
            $db->select()
                ->from('pictures_moder_votes', new Zend_Db_Expr('COUNT(1)'))
                ->where('picture_id = ?', $picture->id)
                ->where('user_id = ?', $user->id)
        );
    }
}
