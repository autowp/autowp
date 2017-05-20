<?php

namespace Application\Controller\Api;

use Zend\Db\Adapter\Adapter;
use Zend\Db\TableGateway\TableGateway;
use Zend\Form\Form;
use Zend\Mvc\Controller\AbstractRestfulController;
use Zend\View\Model\JsonModel;

use Autowp\Message\MessageService;
use Autowp\User\Model\DbTable\User;

use Application\HostManager;
use Application\Model\DbTable;
use Application\Model\UserPicture;

use Zend_Db_Expr;

class PictureModerVoteController extends AbstractRestfulController
{
    /**
     * @var Form
     */
    private $voteForm;

    /**
     * @var HostManager
     */
    private $hostManager;

    /**
     * @var MessageService
     */
    private $message;

    /**
     * @var UserPicture
     */
    private $userPicture;

    public function __construct(
        Adapter $adapter,
        HostManager $hostManager,
        MessageService $message,
        Form $voteForm,
        UserPicture $userPicture
    ) {
        $this->hostManager = $hostManager;
        $this->message = $message;
        $this->voteForm = $voteForm;
        $this->templateTable = new TableGateway('picture_moder_vote_template', $adapter);
        $this->userPicture = $userPicture;
    }

    private function pictureUrl(DbTable\Picture\Row $picture, $forceCanonical = false, $uri = null)
    {
        return $this->url()->fromRoute('index', [], [
            'force_canonical' => $forceCanonical,
            'uri'             => $uri
        ]) . 'ng/moder/pictures/' . $picture->id;
    }

    private function pictureVoteExists($picture, $user)
    {
        $pictureTable = new DbTable\Picture();
        $db = $pictureTable->getAdapter();
        return $db->fetchOne(
            $db->select()
                ->from('pictures_moder_votes', new Zend_Db_Expr('COUNT(1)'))
                ->where('picture_id = ?', $picture->id)
                ->where('user_id = ?', $user->id)
        );
    }

    private function notifyVote($picture, $vote, $reason)
    {
        $owner = $picture->findParentRow(User::class, 'Owner');
        $ownerIsModer = $owner && $this->user($owner)->inheritsRole('moder');
        if ($ownerIsModer) {
            if ($owner->id != $this->user()->get()->id) {
                $uri = $this->hostManager->getUriByLanguage($owner->language);

                $message = sprintf(
                    $this->translate(
                        $vote
                            ? 'pm/new-picture-%s-vote-%s/accept'
                            : 'pm/new-picture-%s-vote-%s/delete',
                        'default',
                        $owner->language
                    ),
                    $this->pictureUrl($picture, true, $uri),
                    $reason
                );

                $this->message->send(null, $owner->id, $message);
            }
        }
    }

    private function unaccept(DbTable\Picture\Row $picture)
    {
        $previousStatusUserId = $picture->change_status_user_id;

        $user = $this->user()->get();
        $picture->setFromArray([
            'status'                => DbTable\Picture::STATUS_INBOX,
            'change_status_user_id' => $user->id
        ]);
        $picture->save();

        if ($picture->owner_id) {
            $this->userPicture->refreshPicturesCount($picture->owner_id);
        }

        $this->log(sprintf(
            'С картинки %s снят статус "принято"',
            htmlspecialchars($this->pic()->name($picture, $this->language()))
        ), $picture);


        $pictureUrl = $this->pic()->url($picture->identity, true);
        if ($previousStatusUserId != $user->id) {
            $userTable = new User();
            foreach ($userTable->find($previousStatusUserId) as $prevUser) {
                $message = sprintf(
                    'С картинки %s снят статус "принято"',
                    $pictureUrl
                );
                $this->message->send(null, $prevUser->id, $message);
            }
        }
    }

    /**
     * Return single resource
     *
     * @param  mixed $id
     * @return mixed
     */
    public function get($id)
    {
        return $this->notFoundAcation();
    }

    /**
     * Return list of resources
     *
     * @return mixed
     */
    public function getList()
    {
        return $this->notFoundAcation();
    }

    /**
     * Update an existing resource
     *
     * @param  mixed $id
     * @param  mixed $data
     * @return mixed
     */
    public function update($id, $data)
    {
        if (! $this->user()->isAllowed('picture', 'moder_vote')) {
            return $this->forbiddenAction();
        }

        $pictureTable = new DbTable\Picture();
        $picture = $pictureTable->find($this->params('id'))->current();
        if (! $picture) {
            return $this->notFoundAction();
        }

        $user = $this->user()->get();
        $voteExists = $this->pictureVoteExists($picture, $user);

        if ($voteExists) {
            return $this->getResponse()->setStatusCode(400);
        }

        $this->voteForm->setData($data);

        if (! $this->voteForm->isValid()) {
            $this->getResponse()->setStatusCode(400);
            return new JsonModel([
                'details' => $this->voteForm->getMessages()
            ]);
        }

        $values = $this->voteForm->getData();

        $vote = $values['vote'] > 0;

        $moderVotes = new DbTable\Picture\ModerVote();
        $moderVotes->insert([
            'user_id'    => $user->id,
            'picture_id' => $picture->id,
            'day_date'   => new Zend_Db_Expr('NOW()'),
            'reason'     => $values['reason'],
            'vote'       => $vote ? 1 : 0
        ]);

        if ($vote && $picture->status == DbTable\Picture::STATUS_REMOVING) {
            $picture->status = DbTable\Picture::STATUS_INBOX;
            $picture->save();
        }

        if ((! $vote) && $picture->status == DbTable\Picture::STATUS_ACCEPTED) {
            $this->unaccept($picture);
        }

        if ($values['save']) {
            $row = $this->templateTable->select([
                'user_id' => $user->id,
                'reason'  => $values['reason'],
            ])->current();
            if (! $row) {
                $this->templateTable->insert([
                    'user_id' => $user->id,
                    'reason'  => $values['reason'],
                    'vote'    => $vote ? 1 : -1
                ]);
            }
        }

        $message = sprintf(
            $vote
                ? 'Подана заявка на принятие картинки %s'
                : 'Подана заявка на удаление картинки %s',
            htmlspecialchars($this->pic()->name($picture, $this->language()))
        );
        $this->log($message, $picture);

        $this->notifyVote($picture, $vote, $values['reason']);

        $this->getResponse()->setStatusCode(200);

        return new JsonModel([
            'status' => true
        ]);
    }

    /**
     * Delete an existing resource
     *
     * @param  mixed $id
     * @return mixed
     */
    public function delete($id)
    {
        $pictureTable = new DbTable\Picture();
        $picture = $pictureTable->find($this->params('id'))->current();
        if (! $picture) {
            return $this->notFoundAction();
        }

        $user = $this->user()->get();
        if (! $user) {
            return $this->forbiddenAction();
        }

        $voteExists = $this->pictureVoteExists($picture, $user);
        if (! $voteExists) {
            return $this->notFoundAction();
        }

        $moderVotes = new DbTable\Picture\ModerVote();
        $moderVotes->delete([
            'user_id = ?'    => $user->id,
            'picture_id = ?' => $picture->id
        ]);

        $message = sprintf(
            $vote
                ? 'Отменена заявка на принятие картинки %s'
                : 'Отменена заявка на удаление картинки %s',
            htmlspecialchars($this->pic()->name($picture, $this->language()))
        );
        $this->log($message, $picture);

        return $this->getResponse()->setStatusCode(204);
    }
}
