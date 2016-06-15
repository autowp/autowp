<?php

use Application\Model\Forums;
use Application\Model\Message;

class AccountController extends Zend_Controller_Action
{
    public function sidebar()
    {
        $mModel = new Message();

        $user = $this->_helper->user()->get();

        $this->view->assign([
            'smCount'    => $mModel->getSystemCount($user->id),
            'newSmCount' => $mModel->getNewSystemCount($user->id),
            'pmCount'    => $mModel->getInboxCount($user->id),
            'newPmCount' => $mModel->getInboxNewCount($user->id),
            'omCount'    => $mModel->getSentCount($user->id)
        ]);

        $pictures = $this->_helper->catalogue()->getPictureTable();

        $db = $pictures->getAdapter();

        $this->view->picsCount = $db->fetchOne(
            $db->select()
                 ->from('pictures', array(new Zend_Db_Expr('COUNT(1)')))
                 ->where('owner_id=?', $user->id)
                 ->where('status IN (?)', array(Picture::STATUS_ACCEPTED, Picture::STATUS_NEW))
        );

        $this->view->subscribesCount = $db->fetchOne(
            $db->select()
                 ->from('forums_topics', new Zend_Db_Expr('COUNT(*)'))
                 ->join('forums_topics_subscribers', 'forums_topics.id=forums_topics_subscribers.topic_id', null)
                 ->where('forums_topics_subscribers.user_id = ?', $user->id)
                 ->where('forums_topics.status IN (?)', array(Forums::STATUS_CLOSED, Forums::STATUS_NORMAL))
        );



        $this->view->notTakenPicturesCount = $pictures->getAdapter()->fetchOne(
            $pictures->select()
                ->from($pictures, new Zend_Db_Expr('COUNT(1)'))
                ->where('owner_id = ?', $user->id)
                ->where('status IN (?)', array(Picture::STATUS_NEW, Picture::STATUS_INBOX))
        );

        $this->getResponse()->insert('sidebar', $this->view->render('account/sidebar.phtml'));
    }

    public function sendPersonalMessageAction()
    {
        $currentUser = $this->_helper->user()->get();
        if (!$currentUser) {
            return $this->forward('index', 'login');
        }

        $users = new Users();

        $user = $users->find($this->getParam('user_id'))->current();
        if (!$user) {
            return $this->forward('notfound', 'error');
        }

        $message = $this->getParam('message');

        $mModel = new Message();
        $mModel->send($currentUser->id, $user->id, $message);

        return $this->_helper->json(array(
            'ok'      => true,
            'message' => 'Сообщение отправлено'
        ));
    }

    public function deletePersonalMessageAction()
    {
        if (!$this->_helper->user()->logedIn()) {
            return $this->forward('index', 'login');
        }

        $user = $this->_helper->user()->get();

        $mModel = new Message();
        $mModel->delete($user->id, $this->getParam('id'));

        return $this->_helper->json(array(
            'ok' => true
        ));
    }

    public function addAccountFailedAction()
    {
        if (!$this->_helper->user()->logedIn()) {
            return $this->forward('index', 'login');
        }

        $this->sidebar();
    }

    /**
     * @param string $serviceId
     * @return Autowp_ExternalLoginService_Abstract
     */
    private function _getExternalLoginService($serviceId)
    {
        $factory = $this->getInvokeArg('bootstrap')->getResource('externalloginservice');
        $service = $factory->getService($serviceId, $serviceId, array(
            'redirect_uri' => 'http://en.wheelsage.org/login/callback'
        ));

        if (!$service) {
            throw new Exception("Service `$serviceId` not found");
        }
        return $service;
    }

    public function accountsAction()
    {
        if (!$this->_helper->user()->logedIn()) {
            return $this->forward('index', 'login');
        }

        $this->sidebar();

        $user = $this->_helper->user()->get();

        $uaTable = new User_Account();

        $uaRows = $uaTable->fetchAll(array(
            'user_id = ?' => $user->id
        ));

        $accounts = array();
        foreach ($uaRows as $uaRow) {
            $accounts[] = array(
                'name'      => $uaRow->name,
                'link'      => $uaRow->link,
                'icon'      => 'fa fa-' . $uaRow->service_id,
                'canRemove' => $this->_canRemoveAccount($uaRow->service_id),
                'removeUrl' => $this->_helper->url->url(array(
                    'action'  => 'remove-account',
                    'service' => $uaRow->service_id
                ))
            );
        }

        $addAccountForm = new Application_Form_Account_AddAccount(array(
            'action'           => $this->_helper->url->url(),
            'typeMultioptions' => array(
                'facebook'    => 'Facebook',
                'vk'          => 'VK',
                'google-plus' => 'Google+',
                'twitter'     => 'Twitter',
                'github'      => 'Github',
                'linkedin'    => 'Linkedin'
            )
        ));

        $request = $this->getRequest();

        if ($request->isPost() && $addAccountForm->isValid($request->getPost())) {
            $values = $addAccountForm->getValues();
            $serviceId = $values['type'];
            $service = $this->_getExternalLoginService($values['type']);

            $loginUrl = $service->getLoginUrl();

            //print $loginUrl; exit;

            $table = new LoginState();
            $row = $table->createRow(array(
                'state'    => $service->getState(),
                'time'     => new Zend_Db_Expr('now()'),
                'user_id'  => $user->id,
                'language' => $this->_helper->language(),
                'service'  => $serviceId,
                'url'      => $this->_helper->url->url(array(
                    'controller' => 'account',
                    'action'     => 'accounts'
                ), 'account', true)
            ));

            $row->save();

            return $this->redirect($loginUrl);
        }

        $this->view->assign(array(
            'accounts'       => $accounts,
            'addAccountForm' => $addAccountForm
        ));
    }

    private function _canRemoveAccount($serviceId)
    {
        if (!$this->_helper->user()->logedIn()) {
            return false;
        }

        if ($this->_helper->user()->get()->e_mail) {
            return true;
        }

        $uaTable = new User_Account();
        $uaRow = $uaTable->fetchRow(array(
            'user_id = ?'     => $this->_helper->user()->get()->id,
            'service_id <> ?' => $serviceId
        ));

        if ($uaRow) {
            return true;
        }

        return false;
    }

    public function removeAccountAction()
    {
        if (!$this->_helper->user()->logedIn()) {
            return $this->forward('index', 'login');
        }

        $serviceId = (string)$this->_getParam('service');

        $uaTable = new User_Account();
        $uaRow = $uaTable->fetchRow(array(
            'user_id = ?'    => $this->_helper->user()->get()->id,
            'service_id = ?' => $serviceId
        ));

        if (!$uaRow) {
            return $this->forward('notfound', 'error');
        }

        if (!$this->_canRemoveAccount($serviceId)) {
            return $this->forward('remove-account-failed');
        }

        $uaRow->delete();

        $this->_helper->flashMessenger->addMessage('Учётная запись удалена');

        return $this->redirect($this->_helper->url->url(array(
            'action'     => 'accounts',
            'service_id' => null
        )));
    }

    public function removeAccountFailedAction()
    {

    }

    public function profileAction()
    {
        if (!$this->_helper->user()->logedIn()) {
            return $this->forward('index', 'login');
        }

        $this->sidebar();

        $user = $this->_helper->user()->get();

        $request = $this->getRequest();

        $form = new Application_Form_Account_Profile(array(
            'action' => $this->_helper->url->url(array(
                'form' => 'profile'
            ))
        ));
        $form->populate($user->toArray());
        if ($request->isPost() && $this->_getParam('form') == 'profile' && $form->isValid($request->getPost())) {
            $values = $form->getValues();

            $old_name = $user->getCompoundName();

            $user->setFromArray(array(
                'name' => $values['name']
            ))->save();

            $new_name = $user->getCompoundName();

            if ($old_name != $new_name) {
                $user_renames = new User_Renames();
                $user_renames->insert(array(
                    'user_id'  => $user->id,
                    'old_name' => $old_name,
                    'new_name' => $new_name,
                    'date'     => new Zend_Db_Expr('NOW()')
                ));
            }

            $this->_helper->flashMessenger->addMessage('Данные сохранены');

            return $this->redirect($this->_helper->url->url(array()));
        }

        $this->view->form = $form;


        if ($request->isPost() && $this->_getParam('form') == 'reset-photo') {

            $oldImageId = $user->img;
            if ($oldImageId) {
                $user->img = null;
                $user->save();
                $imageStorage = $this->getInvokeArg('bootstrap')->getResource('imagestorage');
                $imageStorage->removeImage($oldImageId);
            }

            $this->_helper->flashMessenger->addMessage('Фотография удалена');

            return $this->redirect($this->_helper->url->url(array(
                'form' => null
            )));
        }

        $form = new Application_Form_Account_Photo(array(
            'action' => $this->_helper->url->url(array(
                'form' => 'photo'
            ))
        ));
        if ($request->isPost() && $this->_getParam('form') == 'photo' && $form->isValid($request->getPost())) {

            $form->photo->receive();
            $filepath = $form->photo->getFileName();

            $imageStorage = $this->getInvokeArg('bootstrap')->getResource('imagestorage');
            $imageSampler = $imageStorage->getImageSampler();

            $imagick = new Imagick();
            if (!$imagick->readImage($filepath)) {
                throw new Exception("Error loading image");
            }
            $format = $imageStorage->getFormat('photo');
            $imageSampler->convertImagick($imagick, $format);

            $newImageId = $imageStorage->addImageFromImagick($imagick, 'user');

            $imagick->clear();

            $oldImageId = $user->img;
            $user->img = $newImageId;
            $user->save();
            if ($oldImageId) {
                $imageStorage->removeImage($oldImageId);
            }

            $this->_helper->flashMessenger->addMessage('Фотография сохранена');

            return $this->redirect($this->_helper->url->url(array()));
        }

        $this->view->formPhoto = $form;

        $language = $this->_helper->language();
        $list = Zend_Locale::getTranslationList("timezonetowindows", $language);
        $list = array_values($list);
        $list[] = 'UTC';
        foreach ($list as $key => $value) {
            if (strncmp($value, 'Etc/', 4) == 0) {
                unset($list[$key]);
            }
        }
        sort($list, SORT_STRING);

        $hosts = Zend_Controller_Front::getInstance()
                ->getParam('bootstrap')->getOption('hosts');

        foreach ($hosts as $language => $options) {
            $name = Zend_Locale::getTranslation($language, 'language', $language);
            $languages[$language] = $name;
        }

        $settingsForm = new Application_Form_Account_Settings([
            'timezoneList' => $list,
            'languages'    => $languages,
            'action' => $this->_helper->url->url(array(
                'form' => 'settings'
            ))
        ]);

        $settingsForm->populate([
            'timezone' => $user->timezone,
            'language' => $user->language
        ]);

        if ($request->isPost() && $this->_getParam('form') == 'settings' && $settingsForm->isValid($request->getPost())) {

            $values = $settingsForm->getValues();

            $user->timezone = $values['timezone'];
            $user->language = $values['language'];
            $user->save();

            return $this->redirect($this->_helper->url->url());
        }

        $this->view->assign(array(
            'settingsForm' => $settingsForm
        ));
    }

    public function emailAction()
    {
        if (!$this->_helper->user()->logedIn()) {
            return $this->forward('index', 'login');
        }

        $this->sidebar();

        $user = $this->_helper->user()->get();

        $request = $this->getRequest();

        $form = new Application_Form_Account_Email(array(
            'action' => $this->_helper->url->url()
        ));
        $form->populate(array(
            'e_mail' => $user->e_mail
        ));
        if ($request->isPost() && $form->isValid($request->getPost())) {
            $values = $form->getValues();

            $usersService = $this->getInvokeArg('bootstrap')->getResource('users');
            $usersService->changeEmailStart($user, $values['e_mail'], $this->_helper->language());

            $this->_helper->flashMessenger->addMessage($this->view->translate('users/change-email/confirmation-message-sent'));

            return $this->redirect($this->_helper->url->url(array()));
        }

        $this->view->form = $form;
    }

    public function emailcheckAction()
    {
        $usersService = $this->getInvokeArg('bootstrap')->getResource('users');

        $code = $this->getParam('email_check_code');
        $user = $usersService->emailChangeFinish($code);

        $template = 'emailcheck-fail';

        if ($user) {
            if (!$this->_helper->user()->logedIn()) {
                $adapter = new Project_Auth_Adapter_Id();
                $adapter->setIdentity($user->id);
                $result = Zend_Auth::getInstance()->authenticate($adapter);

                if ($result->isValid()) {
                    // hmmm...
                }
            }

            $template = 'emailcheck-ok';
        }

        if ($this->_helper->user()->logedIn()) {
            $this->sidebar();
        }

        return $this->render($template);
    }

    private function _preparePersonalMessages($messages)
    {
        //TODO: remove
        return $messages;
    }

    public function personalMessagesInboxAction()
    {
        if (!$this->_helper->user()->logedIn()) {
            return $this->forward('index', 'login');
        }

        $this->sidebar();

        $user = $this->_helper->user()->get();

        $mModel = new Message();
        $inbox = $mModel->getInbox($user->id, $this->getParam('page'));

        $this->view->assign(array(
            'paginator' => $inbox['paginator'],
            'messages'  => $this->_preparePersonalMessages($inbox['messages'])
        ));
    }


    public function personalMessagesSentAction()
    {
        if (!$this->_helper->user()->logedIn()) {
            return $this->forward('index', 'login');
        }

        $this->sidebar();

        $user = $this->_helper->user()->get();

        $mModel = new Message();
        $sentbox = $mModel->getSentbox($user->id, $this->getParam('page'));

        $this->view->assign(array(
            'paginator' => $sentbox['paginator'],
            'messages'  => $this->_preparePersonalMessages($sentbox['messages'])
        ));
    }

    public function personalMessagesSystemAction()
    {
        if (!$this->_helper->user()->logedIn()) {
            return $this->forward('index', 'login');
        }

        $this->sidebar();

        $user = $this->_helper->user()->get();

        $mModel = new Message();
        $systembox = $mModel->getSystembox($user->id, $this->getParam('page'));

        $this->view->assign(array(
            'paginator' => $systembox['paginator'],
            'messages'  => $this->_preparePersonalMessages($systembox['messages'])
        ));
    }

    public function personalMessagesUserAction()
    {
        if (!$this->_helper->user()->logedIn()) {
            return $this->forward('index', 'login');
        }

        $users = new Users();

        $user = $users->find($this->getParam('user_id'))->current();
        if (!$user) {
            return $this->forward('notfound', 'error');
        }

        $this->sidebar();

        $logedInUser = $this->_helper->user()->get();

        $mModel = new Message();
        $dialogbox = $mModel->getDialogbox($logedInUser->id, $user->id, $this->getParam('page'));

        $this->view->assign(array(
            'paginator' => $dialogbox['paginator'],
            'messages'  => $this->_preparePersonalMessages($dialogbox['messages'])
        ));
    }

    public function forumsAction()
    {
        if (!$this->_helper->user()->logedIn()) {
            return $this->forward('index', 'login');
        }

        $this->sidebar();
    }

    public function forumsUnsubscribeAction()
    {
        $user = $this->_helper->user()->get();

        if (!$user) {
            return $this->forward('index', 'login');
        }

        $topicId = (int)$this->getParam('topic_id');

        $model = new Forums();
        $model->unsubscribe($topicId, $user->id);

        return $this->redirect($this->_helper->url->url(array(
            'module'     => 'default',
            'controller' => 'account',
            'action'     => 'forums',
        ), 'account', true));
    }

    public function notTakenPicturesAction()
    {
        if (!$this->_helper->user()->logedIn()) {
            return $this->forward('index', 'login');
        }

        $this->sidebar();

        $pictures = $this->_helper->catalogue()->getPictureTable();

        $select = $pictures->select(true)
            ->where('owner_id = ?', $this->_helper->user()->get()->id)
            ->where('status IN (?)', array(Picture::STATUS_NEW, Picture::STATUS_INBOX))
            ->order(array('add_date DESC'));

        $paginator = Zend_Paginator::factory($select)
            ->setItemCountPerPage(16)
            ->setCurrentPageNumber($this->_getParam('page'));

        $select->limitPage($paginator->getCurrentPageNumber(), $paginator->getItemCountPerPage());

        $picturesData = $this->_helper->pic->listData($select, array(
            'width' => 4
        ));

        $this->view->assign(array(
            'paginator'    => $paginator,
            'picturesData' => $picturesData,
        ));
    }

    public function clearSystemMessagesAction()
    {
        if (!$this->_helper->user()->logedIn()) {
            return $this->forward('index', 'login');
        }

        $mModel = new Message();
        $mModel->deleteAllSystem($this->_helper->user()->get()->id);

        return $this->redirect($this->_helper->url->url(array(
            'controller' => 'account',
            'action'    => 'personal-messages-system'
        )));
    }

    public function clearSentMessagesAction()
    {
        if (!$this->_helper->user()->logedIn()) {
            return $this->forward('index', 'login');
        }

        $mModel = new Message();
        $mModel->deleteAllSent($this->_helper->user()->get()->id);

        return $this->redirect($this->_helper->url->url(array(
            'controller' => 'account',
            'action'     => 'personal-messages-sent'
        )));
    }

    public function accessAction()
    {
        if (!$this->_helper->user()->logedIn()) {
            return $this->forward('index', 'login');
        }

        $this->sidebar();

        $form = new Application_Form_Account_Password(array(
            'action' => $this->_helper->url->url(array()),
        ));

        $request = $this->getRequest();

        if ($request->isPost() && $form->isValid($request->getPost())) {
            $values = $form->getValues();

            $user = $this->_helper->user()->get();

            $uTable = new Users();

            $uRow = $uTable->fetchRow(array(
                'id = ?'                                  => $user->id,
                'password = ' . Users::passwordHashExpr() => $values['password_old']
            ));

            if (!$uRow) {

                $form->password_old->addError('Текущий пароль введен неверно');

            } else {

                $passwordExpr = $uTable->getAdapter()->quoteInto(Users::passwordHashExpr(), $values['password']);

                $user->password = new Zend_Db_Expr($passwordExpr);
                $user->save();

                $this->_helper->flashMessenger->addMessage('Пароль успешно изменён');

                return $this->redirect($this->_helper->url->url(array(
                    'form' => null
                )));
            }
        }

        $this->view->formPassword = $form;
    }

    public function deleteAction()
    {
        if (!$this->_helper->user()->logedIn()) {
            return $this->forward('index', 'login');
        }

        $request = $this->getRequest();

        $form = new Application_Form_Account_Delete(array(
            'action' => $this->_helper->url->url(array()),
        ));

        if ($request->isPost() && $form->isValid($request->getPost())) {
            $values = $form->getValues();

            $user = $this->_helper->user()->get();

            $usersService = $this->getInvokeArg('bootstrap')->getResource('users');

            $valid = $usersService->checkPassword($user->id, $values['password']);

            if (!$valid) {

                $form->password->addError('Пароль введен неверно');

            } else {

                $user->deleted = true;
                $user->save();

                Zend_Auth::getInstance()->clearIdentity();
                $this->_helper->user()->clearRememberCookie();

                return $this->render('deleted');
            }
        }

        $this->sidebar();
        $this->view->form = $form;
    }

    public function specsConflictsAction()
    {
        if (!$this->_helper->user()->logedIn()) {
            return $this->forward('index', 'login');
        }

        $service = new Application_Service_Specifications();

        $filter = $this->getParam('conflict');
        $page = (int)$this->getParam('page');

        $userId = $this->_helper->user()->get()->id;

        $data = $service->getConflicts($userId, $filter, $page, 50);
        $conflicts = $data['conflicts'];
        $paginator = $data['paginator'];

        $userTable = new Users();
        $carTable = new Cars();
        $engineTable = new Engines();

        $language = $this->_helper->language();

        foreach ($conflicts as &$conflict) {
            foreach ($conflict['values'] as &$value) {
                $value['user'] = $userTable->find($value['userId'])->current();
            }

            switch ($conflict['itemTypeId']) {
                case Application_Service_Specifications::ITEM_TYPE_CAR:
                    $car = $carTable->find($conflict['itemId'])->current();
                    $conflict['object'] = $car ? $car->getFullName($language) : null;
                    $conflict['url'] = $this->_helper->url->url(array(
                        'controller' => 'cars',
                        'action'     => 'car-specifications-editor',
                        'car_id'     => $conflict['itemId'],
                        'tab'        => 'spec'
                    ), 'default', true);
                    break;
                case Application_Service_Specifications::ITEM_TYPE_ENGINE:
                    $engine = $engineTable->find($conflict['itemId'])->current();
                    $conflict['object'] = $engine ? 'Двигатель ' . $engine->caption : null;
                    $conflict['url'] = $this->_helper->url->url(array(
                        'controller' => 'cars',
                        'action'     => 'engine-spec-editor',
                        'engine_id'  => $conflict['itemId'],
                        'tab'        => 'engine'
                    ), 'default', true);
                    break;
            }
        }
        unset($conflict);

        $this->view->assign(array(
            'filter'    => (string)$filter,
            'conflicts' => $conflicts,
            'paginator' => $paginator,
            'weight'    => $this->_helper->user()->get()->specs_weight
        ));

        $this->sidebar();
    }

    public function contactsAction()
    {
        if (!$this->_helper->user()->logedIn()) {
            return $this->forward('index', 'login');
        }

        $this->sidebar();

        $user = $this->_helper->user()->get();

        $userTable = new Users();

        $userRows = $userTable->fetchAll(
            $userTable->select(true)
                ->join('contact', 'users.id = contact.contact_user_id', null)
                ->where('contact.user_id = ?', $user->id)
                ->order(['users.deleted', 'users.name'])
        );
        $users = [];
        foreach ($userRows as $row) {
            $users[] = $row;
        }

        $this->view->assign(array(
            'users' => $users
        ));
    }
}