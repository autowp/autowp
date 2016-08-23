<?php

use Application\Model\Message;
use Application\Service\TrafficControl;

class Moder_PicturesController extends Zend_Controller_Action
{
    private $table;

    public function init()
    {
        parent::init();

        $this->table = $this->_helper->catalogue()->getPictureTable();
    }

    public function preDispatch()
    {
        parent::preDispatch();

        if (!$this->_helper->user()->inheritsRole('moder') ) {
            return $this->forward('forbidden', 'error', 'default');
        }
    }

    private function pictureUrl(Picture_Row $picture)
    {
        return $this->_helper->url->url([
            'module'        => 'moder',
            'controller'    => 'pictures',
            'action'        => 'picture',
            'picture_id'    => $picture->id
        ], 'default', true);
    }

    public function picturePerspectiveAction()
    {
        $picture = $this->table->find($this->_getParam('picture_id'))->current();
        if (!$picture) {
            return $this->forward('notfound', 'error', 'default');
        }

        if ($picture->type != Picture::CAR_TYPE_ID)
            throw new Exception('Картинка несовместимого типа');

        $perspectives = new Perspectives();

        $multioptions = [
            ''    => '--'
        ] + $perspectives->getAdapter()->fetchPairs(
            $perspectives->getAdapter()->select()
                ->from($perspectives->info('name'), ['id', 'name'])
                ->order('position')
        );

        $form = new Zend_Form([
            'method'    => 'post',
            'action'    => $this->_helper->url->url([
                'module'        => 'moder',
                'controller'    => 'pictures',
                'action'        => 'picture-perspective',
                'picture_id'    => $picture->id
            ], 'default', true),
            'elements'    => [
                ['select', 'perspective_id', [
                    'required'     => false,
                    'label'        => 'Ракурс',
                    'decorators'   => ['ViewHelper'],
                    'multioptions' => $multioptions,

                ]],
            ],
            'class' => 'tiny',
            'decorators'    => [
                'PrepareElements',
                ['viewScript', ['viewScript' => 'forms/pictures/perspective.phtml']],
                'Form'
            ],
        ]);

        $form->populate([
            'perspective_id' => $picture->perspective_id
        ]);

        $request = $this->getRequest();

        if ($request->isPost() && $form->isValid($request->getPost())) {
            $values = $form->getValues();

            $user = $this->_helper->user()->get();
            $picture->perspective_id = $values['perspective_id'];
            $picture->change_perspective_user_id = $user->id;
            $picture->save();

            $this->_helper->log(sprintf(
                'Установка ракурса картинки %s',
                $this->view->htmlA($this->pictureUrl($picture), $picture->getCaption())
            ), [$picture]);

            if ($request->isXmlHttpRequest())
                return $this->_helper->json([
                    'ok' => true
                ]);

            return $this->_redirect($this->pictureUrl($picture));
        }

        $this->view->assign([
            'picture' => $picture,
            'form'    => $form,
            'tiny'    => (bool)$this->getParam('tiny')
        ]);
    }

    private function pictureCanDelete($picture)
    {
        $user = $this->_helper->user()->get();
        $canDelete = false;
        if (in_array($picture->status, [Picture::STATUS_INBOX, Picture::STATUS_NEW])) {
            if ($this->_helper->user()->isAllowed('picture', 'remove')) {
                if ($this->pictureVoteExists($picture, $user)) {
                    $canDelete = true;
                }
            } elseif ($this->_helper->user()->isAllowed('picture', 'remove_by_vote')) {
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

    public function pictureVoteAction()
    {
        $picture = $this->table->find($this->_getParam('picture_id'))->current();
        if (!$picture) {
            return $this->forward('notfound', 'error', 'default');
        }

        $hideVote = (bool)$this->_getParam('hide-vote');

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

        $user = $this->_helper->user()->get();
        $voteExists = $this->pictureVoteExists($picture, $user);

        $request = $this->getRequest();

        $formPictureVote = null;
        if (!$voteExists && $this->_helper->user()->isAllowed('picture', 'moder_vote'))
        {
            $form = new Application_Form_Moder_Picture_Vote([
                'action' => $this->_helper->url->url([
                    'module'     => 'moder',
                    'controller' => 'pictures',
                    'action'     => 'picture-vote',
                    'form'       => 'picture-vote',
                    'picture_id' => $picture->id
                ], 'default'),
            ]);

            if ($request->isPost() && $this->_getParam('form') == 'picture-vote' && $form->isValid($request->getPost()))
            {
                $values = $form->getValues();

                if ($customReason = $request->getCookie('customReason')) {
                    $customReason = (array)unserialize($customReason);
                } else {
                    $customReason = [];
                }

                $customReason[] = $values['reason'];
                $customReason = array_unique($customReason);

                setcookie('customReason', serialize($customReason), time()+60*60*24*30, '/');

                $vote = (bool)($values['vote'] == 'Хочу принять');

                $user = $this->_helper->user()->get();
                $moderVotes = new Pictures_Moder_Votes();
                $moderVotes->insert([
                    'user_id'    => $user->id,
                    'picture_id' => $picture->id,
                    'day_date'   => new Zend_Db_Expr('NOW()'),
                    'reason'     => $values['reason'],
                    'vote'       => $vote ? 1 : 0
                ]);

                if ($vote && $picture->status == Picture::STATUS_REMOVING) {
                    $picture->status = Picture::STATUS_INBOX;
                    $picture->save();
                }

                $message = sprintf(
                    $vote
                        ? 'Подана заявка на принятие картинки %s'
                        : 'Подана заявка на удаление картинки %s',
                    $this->view->htmlA($this->pictureUrl($picture), $picture->getCaption())
                );
                $this->_helper->log($message, $picture);

                $owner = $picture->findParentUsersByOwner();
                $ownerIsModer = $owner && $this->_helper->user($owner)->inheritsRole('moder');
                if ($ownerIsModer) {
                    if ($owner->id != $this->_helper->user()->get()->id) {
                        $message = sprintf(
                            'Подана заявка на %s добавленной вами картинки %s'.PHP_EOL.' Причина: %s',
                            $vote ? 'удаление' : 'принятие',
                            $this->view->serverUrl($this->pictureUrl($picture)),
                            $values['reason']
                        );

                        $mModel = new Message();
                        $mModel->send(null, $owner->id, $message);
                    }
                }

                $referer = $request->getServer('HTTP_REFERER');
                if ($referer) {
                    return $this->_redirect($this->pictureUrl($picture));
                }

                return $this->_redirect($this->_helper->url->url());
            }

            $formPictureVote = $form;
        }

        $formPictureUnvote = null;
        if ($voteExists) {
            $form = new Application_Form_Moder_Picture_Unvote([
                'action' => $this->view->url([
                    'module'     => 'moder',
                    'controller' => 'pictures',
                    'action'     => 'picture-vote',
                    'form'       => 'picture-unvote',
                    'picture_id' => $picture->id
                ], 'default', true)
            ]);

            if ($request->isPost() && $this->_getParam('form') == 'picture-unvote' && $form->isValid($request->getPost())) {
                $values = $form->getValues();

                $moderVotes = new Pictures_Moder_Votes();

                $user = $this->_helper->user()->get();
                $moderVotes->delete([
                    'user_id = ?'    => $user->id,
                    'picture_id = ?' => $picture->id
                ]);

                $referer = $request->getServer('HTTP_REFERER');
                if ($referer) {
                    return $this->_redirect($referer);
                }

                return $this->_redirect($this->pictureUrl($picture));
            }

            $formPictureUnvote = $form;
        }

        $deletePictureForm = null;
        if ($canDelete) {
            $form = new Application_Form_Moder_Picture_Delete([
                'action' => $this->_helper->url->url([
                    'module'     => 'moder',
                    'controller' => 'pictures',
                    'action'     => 'delete-picture',
                    'picture_id' => $picture->id,
                    'form'       => 'picture-delete'
                ], 'default', true)
            ]);
            $deletePictureForm = $form;
        }

        $this->view->assign([
            'isLastPicture'     => $isLastPicture,
            'acceptedCount'     => $acceptedCount,
            'canDelete'         => $canDelete,
            'deletePictureForm' => $deletePictureForm,
            'formPictureVote'   => $formPictureVote,
            'formPictureUnvote' => $formPictureUnvote,
            'moderVotes'        => null
        ]);

        if (!$hideVote) {
            $this->view->assign([
                'moderVotes' => $picture->findPictures_Moder_Votes(),
            ]);
        }
    }

}