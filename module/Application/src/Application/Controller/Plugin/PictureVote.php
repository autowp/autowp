<?php

namespace Application\Controller\Plugin;

use Zend\Mvc\Controller\Plugin\AbstractPlugin;

use Picture;

use Zend_Db_Expr;

class PictureVote extends AbstractPlugin
{
    /**
     * @var Picture
     */
    private $table;

    public function __construct()
    {
        $this->table = new Picture();
    }

    public function __invoke($pictureId, $options)
    {
        $options = array_replace([
            'hideVote' => false
        ], $options);

        $picture = $this->table->find($pictureId)->current();
        if (!$picture) {
            return false;
        }

        if (!$this->getController()->user()->inheritsRole('moder')) {
            return false;
        }

        $hideVote = (bool)$options['hideVote'];

        $canDelete = $this->pictureCanDelete($picture);

        $isLastPicture = null;
        if ($picture->type == Picture::CAR_TYPE_ID && $picture->status == Picture::STATUS_ACCEPTED) {
            $car = $picture->findParentCars();
            if ($car) {
                $db = $this->table->getAdapter();
                $isLastPicture = !$db->fetchOne(
                    $db->select()
                        ->from('pictures', [new Zend_Db_Expr('COUNT(1)')])
                        ->where('car_id = ?', $car->id)
                        ->where('status = ?', Picture::STATUS_ACCEPTED)
                        ->where('id <> ?', $picture->id)
                );
            }
        }

        $acceptedCount = null;
        if ($picture->type == Picture::CAR_TYPE_ID) {
            $car = $picture->findParentCars();
            if ($car) {
                $db = $this->table->getAdapter();
                $acceptedCount = $db->fetchOne(
                    $db->select()
                        ->from('pictures', [new Zend_Db_Expr('COUNT(1)')])
                        ->where('car_id = ?', $car->id)
                        ->where('status = ?', Picture::STATUS_ACCEPTED)
                );
            }
        }

        $user = $this->getController()->user()->get();
        $voteExists = $this->pictureVoteExists($picture, $user);

        $voteOptions = [
            'плохое качество',
            'дубль',
            'любительское фото',
            'не по теме сайта',
            'другая',
            'единственное фото',
            'своя',
        ];

        $cookies = $this->getController()->getRequest()->getCookie();

        if (isset($cookies['customReason'])) {
            foreach ((array)unserialize($cookies['customReason']) as $reason) {
                if (strlen($reason) && !in_array($reason, $voteOptions)) {
                    $voteOptions[] = $reason;
                }
            }
        }

        return [
            'isLastPicture'     => $isLastPicture,
            'acceptedCount'     => $acceptedCount,
            'canDelete'         => $canDelete,
            'canVote'           => !$voteExists && $this->getController()->user()->isAllowed('picture', 'moder_vote'),
            'voteExists'        => $voteExists,
            'moderVotes'        => $hideVote ? null : $picture->findPictures_Moder_Votes(),
            'pictureDeleteUrl'  => $this->getController()->url()->fromRoute('moder/pictures/params', [
                'action'     => 'delete-picture',
                'picture_id' => $picture->id
            ]),
            'pictureUnvoteUrl'  => $this->getController()->url()->fromRoute('moder/pictures/params', [
                'action'     => 'picture-vote',
                'form'       => 'picture-unvote',
                'picture_id' => $picture->id
            ]),
            'pictureVoteUrl'    => $this->getController()->url()->fromRoute('moder/pictures/params', [
                'action'     => 'picture-vote',
                'form'       => 'picture-vote',
                'picture_id' => $picture->id
            ]),
            'voteOptions' => array_combine($voteOptions, $voteOptions)
        ];
    }

    private function pictureCanDelete($picture)
    {
        $user = $this->getController()->user()->get();
        $canDelete = false;
        if (in_array($picture->status, [Picture::STATUS_INBOX, Picture::STATUS_NEW])) {
            if ($this->getController()->user()->isAllowed('picture', 'remove')) {
                if ($this->pictureVoteExists($picture, $user)) {
                    $canDelete = true;
                }
            } elseif ($this->getController()->user()->isAllowed('picture', 'remove_by_vote')) {
                if ($this->pictureVoteExists($picture, $user)) {
                    $db = $this->table->getAdapter();
                    $acceptVotes = (int)$db->fetchOne(
                        $db->select()
                            ->from('pictures_moder_votes', array(new Zend_Db_Expr('COUNT(1)')))
                            ->where('picture_id = ?', $picture->id)
                            ->where('vote > 0')
                    );
                    $deleteVotes = (int)$db->fetchOne(
                        $db->select()
                            ->from('pictures_moder_votes', array(new Zend_Db_Expr('COUNT(1)')))
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