<?php

namespace Application\Controller\Api;

use Zend\Db\TableGateway\TableGateway;
use Zend\Form\Form;
use Zend\Mvc\Controller\AbstractRestfulController;
use Zend\View\Model\JsonModel;
use Autowp\Message\MessageService;
use Autowp\User\Model\User;
use Application\Controller\Plugin\ForbiddenAction;
use Application\Controller\Plugin\Pic;
use Application\HostManager;
use Application\Model\Picture;
use Application\Model\PictureModerVote;
use Application\Model\UserPicture;

/**
 * Class PictureModerVoteController
 * @package Application\Controller\Api
 *
 * @method \Autowp\User\Controller\Plugin\User user($user = null)
 * @method Pic pic()
 * @method string language()
 * @method ForbiddenAction forbiddenAction()
 * @method string translate(string $message, string $textDomain = 'default', $locale = null)
 */
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

    /**
     * @var PictureModerVote
     */
    private $pictureModerVote;

    /**
     * @var Picture
     */
    private $picture;

    /**
     * @var User
     */
    private $userModel;
    /**
     * @var TableGateway
     */
    private $templateTable;

    public function __construct(
        HostManager $hostManager,
        MessageService $message,
        Form $voteForm,
        UserPicture $userPicture,
        PictureModerVote $pictureModerVote,
        Picture $picture,
        TableGateway $templateTable,
        User $userModel
    ) {
        $this->hostManager = $hostManager;
        $this->message = $message;
        $this->voteForm = $voteForm;
        $this->templateTable = $templateTable;
        $this->userPicture = $userPicture;
        $this->pictureModerVote = $pictureModerVote;
        $this->picture = $picture;
        $this->userModel = $userModel;
    }

    private function pictureUrl($picture, $forceCanonical = false, $uri = null)
    {
        return $this->url()->fromRoute('index', [], [
            'force_canonical' => $forceCanonical,
            'uri'             => $uri
        ]) . 'ng/moder/pictures/' . $picture['id'];
    }

    private function notifyVote($picture, $vote, $reason)
    {
        $owner = $this->userModel->getRow((int)$picture['owner_id']);
        $ownerIsModer = $owner && $this->user($owner)->inheritsRole('moder');
        if ($ownerIsModer) {
            if ($owner['id'] != $this->user()->get()['id']) {
                $uri = $this->hostManager->getUriByLanguage($owner['language']);

                $message = sprintf(
                    $this->translate(
                        $vote
                            ? 'pm/new-picture-%s-vote-%s/accept'
                            : 'pm/new-picture-%s-vote-%s/delete',
                        'default',
                        $owner['language']
                    ),
                    $this->pictureUrl($picture, true, $uri),
                    $reason
                );

                $this->message->send(null, $owner['id'], $message);
            }
        }
    }

    private function unaccept($picture)
    {
        $previousStatusUserId = $picture['change_status_user_id'];

        $user = $this->user()->get();

        $this->picture->getTable()->update([
            'status'                => Picture::STATUS_INBOX,
            'change_status_user_id' => $user['id']
        ], [
            'id' => $picture['id']
        ]);

        if ($picture['owner_id']) {
            $this->userPicture->refreshPicturesCount($picture['owner_id']);
        }

        $this->log(sprintf(
            'С картинки %s снят статус "принято"',
            htmlspecialchars($this->pic()->name($picture, $this->language()))
        ), [
            'pictures' => $picture['id']
        ]);

        if ($previousStatusUserId != $user['id']) {
            $prevUser = $this->userModel->getRow((int)$previousStatusUserId);
            if ($prevUser) {
                $uri = $this->hostManager->getUriByLanguage($prevUser['language']);

                $uri->setPath('/picture/' . urlencode($picture['identity']))->toString();

                $message = sprintf(
                    'С картинки %s снят статус "принято"',
                    $uri->toString()
                );
                $this->message->send(null, $prevUser['id'], $message);
            }
        }
    }

    /**
     * Return single resource
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
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
     * @param mixed $id
     * @param mixed $data
     * @return mixed
     * @throws \Exception
     */
    public function update($id, $data)
    {
        if (! $this->user()->isAllowed('picture', 'moder_vote')) {
            return $this->forbiddenAction();
        }

        $picture = $this->picture->getRow(['id' => (int)$id]);
        if (! $picture) {
            return $this->notFoundAction();
        }

        $user = $this->user()->get();
        $voteExists = $this->pictureModerVote->hasVote($picture['id'], $user['id']);

        if ($voteExists) {
            /* @phan-suppress-next-line PhanUndeclaredMethod */
            return $this->getResponse()->setStatusCode(400);
        }

        $this->voteForm->setData($data);

        if (! $this->voteForm->isValid()) {
            /* @phan-suppress-next-line PhanUndeclaredMethod */
            $this->getResponse()->setStatusCode(400);
            return new JsonModel([
                'details' => $this->voteForm->getMessages()
            ]);
        }

        $values = $this->voteForm->getData();

        $vote = $values['vote'] > 0;

        $this->pictureModerVote->add($picture['id'], $user['id'], $vote ? 1 : 0, $values['reason']);

        if ($vote && $picture['status'] == Picture::STATUS_REMOVING) {
            $this->picture->getTable()->update([
                'status' => Picture::STATUS_INBOX
            ], [
                'id' => $picture['id']
            ]);
        }

        if ((! $vote) && $picture['status'] == Picture::STATUS_ACCEPTED) {
            $this->unaccept($picture);
        }

        if ($values['save']) {
            $row = $this->templateTable->select([
                'user_id' => $user['id'],
                'reason'  => $values['reason'],
            ])->current();
            if (! $row) {
                $this->templateTable->insert([
                    'user_id' => $user['id'],
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
        $this->log($message, [
            'pictures' => $picture['id']
        ]);

        $this->notifyVote($picture, $vote, $values['reason']);

        /* @phan-suppress-next-line PhanUndeclaredMethod */
        $this->getResponse()->setStatusCode(200);

        return new JsonModel([
            'status' => true
        ]);
    }

    /**
     * Delete an existing resource
     *
     * @param mixed $id
     * @return mixed
     * @throws \Exception
     */
    public function delete($id)
    {
        $picture = $this->picture->getRow(['id' => (int)$id]);
        if (! $picture) {
            return $this->notFoundAction();
        }

        $user = $this->user()->get();
        if (! $user) {
            return $this->forbiddenAction();
        }

        $voteExists = $this->pictureModerVote->hasVote($picture['id'], $user['id']);
        if (! $voteExists) {
            return $this->notFoundAction();
        }

        $this->pictureModerVote->delete($picture['id'], $user['id']);

        $message = sprintf(
            'Отменена заявка на принятие/удаление картинки %s',
            htmlspecialchars($this->pic()->name($picture, $this->language()))
        );
        $this->log($message, [
            'pictures' => $picture['id']
        ]);

        /* @phan-suppress-next-line PhanUndeclaredMethod */
        return $this->getResponse()->setStatusCode(204);
    }
}
