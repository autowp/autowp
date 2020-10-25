<?php

namespace Application\Controller\Api;

use Application\Controller\Plugin\Log;
use Application\Controller\Plugin\Pic;
use Application\HostManager;
use Application\Model\Picture;
use Application\Model\PictureModerVote;
use Application\Model\UserPicture;
use ArrayAccess;
use Autowp\Message\MessageService;
use Autowp\User\Controller\Plugin\User as UserPlugin;
use Autowp\User\Model\User;
use Exception;
use Laminas\Db\TableGateway\TableGateway;
use Laminas\Form\Form;
use Laminas\Mvc\Controller\AbstractRestfulController;
use Laminas\Stdlib\ResponseInterface;
use Laminas\View\Model\JsonModel;
use Laminas\View\Model\ViewModel;

use function htmlspecialchars;
use function sprintf;
use function urlencode;

/**
 * @method UserPlugin user($user = null)
 * @method Pic pic()
 * @method string language()
 * @method ViewModel forbiddenAction()
 * @method string translate(string $message, string $textDomain = 'default', $locale = null)
 * @method Log log(string $message, array $objects)
 */
class PictureModerVoteController extends AbstractRestfulController
{
    private Form $voteForm;

    private HostManager $hostManager;

    private MessageService $message;

    private UserPicture $userPicture;

    private PictureModerVote $pictureModerVote;

    private Picture $picture;

    private User $userModel;

    private TableGateway $templateTable;

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
        $this->hostManager      = $hostManager;
        $this->message          = $message;
        $this->voteForm         = $voteForm;
        $this->templateTable    = $templateTable;
        $this->userPicture      = $userPicture;
        $this->pictureModerVote = $pictureModerVote;
        $this->picture          = $picture;
        $this->userModel        = $userModel;
    }

    /**
     * @param array|ArrayAccess $picture
     * @throws Exception
     */
    private function notifyVote($picture, bool $vote, string $reason): void
    {
        $owner        = $this->userModel->getRow((int) $picture['owner_id']);
        $ownerIsModer = $owner && $this->user($owner)->inheritsRole('moder');
        if ($ownerIsModer) {
            if ((int) $owner['id'] !== (int) $this->user()->get()['id']) {
                $uri = $this->hostManager->getUriByLanguage($owner['language']);
                $uri->setPath('/moder/pictures/' . $picture['id']);

                $message = sprintf(
                    $this->translate(
                        $vote
                            ? 'pm/new-picture-%s-vote-%s/accept'
                            : 'pm/new-picture-%s-vote-%s/delete',
                        'default',
                        $owner['language']
                    ),
                    $uri->toString(),
                    $reason
                );

                $this->message->send(null, $owner['id'], $message);
            }
        }
    }

    /**
     * @param array|ArrayAccess $picture
     * @throws Exception
     */
    private function unaccept($picture): void
    {
        $previousStatusUserId = (int) $picture['change_status_user_id'];

        $user = $this->user()->get();

        $this->picture->getTable()->update([
            'status'                => Picture::STATUS_INBOX,
            'change_status_user_id' => $user['id'],
        ], [
            'id' => $picture['id'],
        ]);

        if ($picture['owner_id']) {
            $this->userPicture->refreshPicturesCount($picture['owner_id']);
        }

        $this->log(sprintf(
            'С картинки %s снят статус "принято"',
            htmlspecialchars($this->pic()->name($picture, $this->language()))
        ), [
            'pictures' => $picture['id'],
        ]);

        if ($previousStatusUserId !== (int) $user['id']) {
            $prevUser = $this->userModel->getRow((int) $previousStatusUserId);
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
     * Return list of resources
     */
    public function getList(): array
    {
        return $this->notFoundAction();
    }

    /**
     * Update an existing resource
     *
     * @param string $id
     * @param mixed  $data
     * @return ViewModel|array
     * @throws Exception
     */
    public function update($id, $data)
    {
        if (! $this->user()->isAllowed('picture', 'moder_vote')) {
            return $this->forbiddenAction();
        }

        $picture = $this->picture->getRow(['id' => (int) $id]);
        if (! $picture) {
            return $this->notFoundAction();
        }

        $user       = $this->user()->get();
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
                'details' => $this->voteForm->getMessages(),
            ]);
        }

        $values = $this->voteForm->getData();

        $vote = $values['vote'] > 0;

        $this->pictureModerVote->add($picture['id'], $user['id'], $vote ? 1 : 0, $values['reason']);

        if ($vote && $picture['status'] === Picture::STATUS_REMOVING) {
            $this->picture->getTable()->update([
                'status' => Picture::STATUS_INBOX,
            ], [
                'id' => $picture['id'],
            ]);
        }

        if ((! $vote) && $picture['status'] === Picture::STATUS_ACCEPTED) {
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
                    'vote'    => $vote ? 1 : -1,
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
            'pictures' => $picture['id'],
        ]);

        $this->notifyVote($picture, $vote, $values['reason']);

        /* @phan-suppress-next-line PhanUndeclaredMethod */
        $this->getResponse()->setStatusCode(200);

        return new JsonModel([
            'status' => true,
        ]);
    }

    /**
     * Delete an existing resource
     *
     * @param mixed $id
     * @return ViewModel|ResponseInterface|array
     * @throws Exception
     */
    public function delete($id)
    {
        $picture = $this->picture->getRow(['id' => (int) $id]);
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
            'pictures' => $picture['id'],
        ]);

        /* @phan-suppress-next-line PhanUndeclaredMethod */
        return $this->getResponse()->setStatusCode(204);
    }
}
