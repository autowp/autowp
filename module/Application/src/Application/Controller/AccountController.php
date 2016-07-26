<?php

namespace Application\Controller;

use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\JsonModel;

use Application\Controller\LoginController;
use Application\Model\Forums;
use Application\Model\Message;
use Application\Paginator\Adapter\Zend1DbTableSelect;

use Application_Service_Specifications;
use Cars;
use Engines;
use LoginState;
use Picture;
use Project_Auth_Adapter_Id;
use User_Account;
use User_Renames;
use Users;

use Zend_Auth;
use Zend_Db_Expr;
use Zend_Locale;

use Exception;
use Imagick;

class AccountController extends AbstractActionController
{
    private function forwadToLogin()
    {
        return $this->forward()->dispatch(LoginController::class, [
            'action' => 'index'
        ]);
    }

    public function sidebar()
    {
        $mModel = new Message();

        $user = $this->user()->get();

        $pictures = $this->catalogue()->getPictureTable();

        $db = $pictures->getAdapter();

        $picsCount = $db->fetchOne(
            $db->select()
                 ->from('pictures', [new Zend_Db_Expr('COUNT(1)')])
                 ->where('owner_id=?', $user->id)
                 ->where('status IN (?)', [Picture::STATUS_ACCEPTED, Picture::STATUS_NEW])
        );

        $subscribesCount = $db->fetchOne(
            $db->select()
                 ->from('forums_topics', new Zend_Db_Expr('COUNT(*)'))
                 ->join('forums_topics_subscribers', 'forums_topics.id=forums_topics_subscribers.topic_id', null)
                 ->where('forums_topics_subscribers.user_id = ?', $user->id)
                 ->where('forums_topics.status IN (?)', [Forums::STATUS_CLOSED, Forums::STATUS_NORMAL])
        );

        $notTakenPicturesCount = $pictures->getAdapter()->fetchOne(
            $pictures->select()
                ->from($pictures, new Zend_Db_Expr('COUNT(1)'))
                ->where('owner_id = ?', $user->id)
                ->where('status IN (?)', [Picture::STATUS_NEW, Picture::STATUS_INBOX])
        );

        return [
            'smCount'    => $mModel->getSystemCount($user->id),
            'newSmCount' => $mModel->getNewSystemCount($user->id),
            'pmCount'    => $mModel->getInboxCount($user->id),
            'newPmCount' => $mModel->getInboxNewCount($user->id),
            'omCount'    => $mModel->getSentCount($user->id),
            'notTakenPicturesCount' => $notTakenPicturesCount,
            'subscribesCount'       => $subscribesCount,
            'picsCount'             => $picsCount
        ];
    }

    public function sendPersonalMessageAction()
    {
        $currentUser = $this->user()->get();
        if (!$currentUser) {
            return $this->forwadToLogin();
        }

        $users = new Users();

        $user = $users->find($this->params('user_id'))->current();
        if (!$user) {
            return $this->notFoundAction();
        }

        $message = $this->params('message');

        $mModel = new Message();
        $mModel->send($currentUser->id, $user->id, $message);

        return new JsonModel([
            'ok'      => true,
            'message' => 'Сообщение отправлено'
        ]);
    }

    public function deletePersonalMessageAction()
    {
        if (!$this->user()->logedIn()) {
            return $this->forwadToLogin();
        }

        $user = $this->user()->get();

        $mModel = new Message();
        $mModel->delete($user->id, $this->params('id'));

        return new JsonModel([
            'ok' => true
        ]);
    }

    public function addAccountFailedAction()
    {
        if (!$this->user()->logedIn()) {
            return $this->forwadToLogin();
        }

        $this->sidebar();
    }

    /**
     * @param string $serviceId
     * @return Autowp_ExternalLoginService_Abstract
     */
    private function getExternalLoginService($serviceId)
    {
        $factory = $this->getInvokeArg('bootstrap')->getResource('externalloginservice');
        $service = $factory->getService($serviceId, $serviceId, [
            'redirect_uri' => 'http://en.wheelsage.org/login/callback'
        ]);

        if (!$service) {
            throw new Exception("Service `$serviceId` not found");
        }
        return $service;
    }

    public function accountsAction()
    {
        if (!$this->user()->logedIn()) {
            return $this->forwadToLogin();
        }

        $this->sidebar();

        $user = $this->user()->get();

        $uaTable = new User_Account();

        $uaRows = $uaTable->fetchAll([
            'user_id = ?' => $user->id
        ]);

        $accounts = [];
        foreach ($uaRows as $uaRow) {
            $accounts[] = [
                'name'      => $uaRow->name,
                'link'      => $uaRow->link,
                'icon'      => 'fa fa-' . $uaRow->service_id,
                'canRemove' => $this->canRemoveAccount($uaRow->service_id),
                'removeUrl' => $this->_helper->url->url([
                    'action'  => 'remove-account',
                    'service' => $uaRow->service_id
                ])
            ];
        }

        $addAccountForm = new Application_Form_Account_AddAccount([
            'action'           => $this->_helper->url->url(),
            'typeMultioptions' => [
                'facebook'    => 'Facebook',
                'vk'          => 'VK',
                'google-plus' => 'Google+',
                'twitter'     => 'Twitter',
                'github'      => 'Github',
                'linkedin'    => 'Linkedin'
            ]
        ]);

        $request = $this->getRequest();

        if ($request->isPost() && $addAccountForm->isValid($request->getPost())) {
            $values = $addAccountForm->getValues();
            $serviceId = $values['type'];
            $service = $this->getExternalLoginService($values['type']);

            $loginUrl = $service->getLoginUrl();

            //print $loginUrl; exit;

            $table = new LoginState();
            $row = $table->createRow([
                'state'    => $service->getState(),
                'time'     => new Zend_Db_Expr('now()'),
                'user_id'  => $user->id,
                'language' => $this->language(),
                'service'  => $serviceId,
                'url'      => $this->_helper->url->url([
                    'controller' => 'account',
                    'action'     => 'accounts'
                ], 'account', true)
            ]);

            $row->save();

            return $this->redirect()->toUrl($loginUrl);
        }

        return [
            'accounts'       => $accounts,
            'addAccountForm' => $addAccountForm
        ];
    }

    private function canRemoveAccount($serviceId)
    {
        if (!$this->user()->logedIn()) {
            return false;
        }

        if ($this->user()->get()->e_mail) {
            return true;
        }

        $uaTable = new User_Account();
        $uaRow = $uaTable->fetchRow([
            'user_id = ?'     => $this->user()->get()->id,
            'service_id <> ?' => $serviceId
        ]);

        if ($uaRow) {
            return true;
        }

        return false;
    }

    public function removeAccountAction()
    {
        if (!$this->user()->logedIn()) {
            return $this->forwadToLogin();
        }

        $serviceId = (string)$this->params('service');

        $uaTable = new User_Account();
        $uaRow = $uaTable->fetchRow([
            'user_id = ?'    => $this->user()->get()->id,
            'service_id = ?' => $serviceId
        ]);

        if (!$uaRow) {
            return $this->notFoundAction();
        }

        if (!$this->canRemoveAccount($serviceId)) {
            return $this->forward()->dispatch(self::class, [
                'action' => 'remove-account-failed'
            ]);
        }

        $uaRow->delete();

        $this->_helper->flashMessenger->addMessage('Учётная запись удалена');

        return $this->redirect()->toRoute('account/accounts');
    }

    public function removeAccountFailedAction()
    {

    }

    public function profileAction()
    {
        if (!$this->user()->logedIn()) {
            return $this->forwadToLogin();
        }

        $this->sidebar();

        $user = $this->user()->get();

        $request = $this->getRequest();

        $formProfile = new Application_Form_Account_Profile([
            'action' => $this->_helper->url->url([
                'form' => 'profile'
            ])
        ]);
        $formProfile->populate($user->toArray());
        if ($request->isPost() && $this->params('form') == 'profile' && $formProfile->isValid($request->getPost())) {
            $values = $formProfile->getValues();

            $old_name = $user->getCompoundName();

            $user->setFromArray([
                'name' => $values['name']
            ])->save();

            $new_name = $user->getCompoundName();

            if ($old_name != $new_name) {
                $user_renames = new User_Renames();
                $user_renames->insert([
                    'user_id'  => $user->id,
                    'old_name' => $old_name,
                    'new_name' => $new_name,
                    'date'     => new Zend_Db_Expr('NOW()')
                ]);
            }

            $this->_helper->flashMessenger->addMessage('Данные сохранены');

            return $this->redirect()->toRoute();
        }

        if ($request->isPost() && $this->params('form') == 'reset-photo') {

            $oldImageId = $user->img;
            if ($oldImageId) {
                $user->img = null;
                $user->save();
                $this->imageStorage()->removeImage($oldImageId);
            }

            $this->_helper->flashMessenger->addMessage('Фотография удалена');

            return $this->redirect()->toRoute();
        }

        $formPhoto = new Application_Form_Account_Photo([
            'action' => $this->_helper->url->url([
                'form' => 'photo'
            ])
        ]);
        if ($request->isPost() && $this->params('form') == 'photo' && $formPhoto->isValid($request->getPost())) {

            $formPhoto->photo->receive();
            $filepath = $formPhoto->photo->getFileName();

            $imageStorage = $this->imageStorage();
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

            return $this->redirect()->toRoute();
        }

        $language = $this->language();
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
            'action' => $this->_helper->url->url([
                'form' => 'settings'
            ])
        ]);

        $settingsForm->populate([
            'timezone' => $user->timezone,
            'language' => $user->language
        ]);

        if ($request->isPost() && $this->params('form') == 'settings' && $settingsForm->isValid($request->getPost())) {

            $values = $settingsForm->getValues();

            $user->timezone = $values['timezone'];
            $user->language = $values['language'];
            $user->save();

            return $this->redirect()->toRoute();
        }

        return [
            'settingsForm' => $settingsForm,
            'formProfile'  => $formProfile,
            'formPhoto'    => $formPhoto,
        ];
    }

    public function emailAction()
    {
        if (!$this->user()->logedIn()) {
            return $this->forwadToLogin();
        }

        $this->sidebar();

        $user = $this->user()->get();

        $request = $this->getRequest();

        $form = new Application_Form_Account_Email([
            'action' => $this->_helper->url->url()
        ]);
        $form->populate([
            'e_mail' => $user->e_mail
        ]);
        if ($request->isPost() && $form->isValid($request->getPost())) {
            $values = $form->getValues();

            $usersService = $this->getInvokeArg('bootstrap')->getResource('users');
            $usersService->changeEmailStart($user, $values['e_mail'], $this->language());

            $this->_helper->flashMessenger->addMessage($this->view->translate('users/change-email/confirmation-message-sent'));

            return $this->redirect()->toRoute();
        }

        return [
            'form' => $form
        ];
    }

    public function emailcheckAction()
    {
        $usersService = $this->getInvokeArg('bootstrap')->getResource('users');

        $code = $this->params('email_check_code');
        $user = $usersService->emailChangeFinish($code);

        $template = 'emailcheck-fail';

        if ($user) {
            if (!$this->user()->logedIn()) {
                $adapter = new Project_Auth_Adapter_Id();
                $adapter->setIdentity($user->id);
                $result = Zend_Auth::getInstance()->authenticate($adapter);

                if ($result->isValid()) {
                    // hmmm...
                }
            }

            $template = 'emailcheck-ok';
        }

        if ($this->user()->logedIn()) {
            $this->sidebar();
        }

        return $this->render($template);
    }

    private function preparePersonalMessages($messages)
    {
        //TODO: remove
        return $messages;
    }

    public function personalMessagesInboxAction()
    {
        if (!$this->user()->logedIn()) {
            return $this->forwadToLogin();
        }

        $user = $this->user()->get();

        $mModel = new Message();
        $inbox = $mModel->getInbox($user->id, $this->params('page'));

        return [
            'paginator' => $inbox['paginator'],
            'messages'  => $this->preparePersonalMessages($inbox['messages']),
            'sidebar'   => $this->sidebar()
        ];
    }

    public function personalMessagesSentAction()
    {
        if (!$this->user()->logedIn()) {
            return $this->forwadToLogin();
        }

        $user = $this->user()->get();

        $mModel = new Message();
        $sentbox = $mModel->getSentbox($user->id, $this->params('page'));

        return [
            'paginator' => $sentbox['paginator'],
            'messages'  => $this->preparePersonalMessages($sentbox['messages']),
            'sidebar'   => $this->sidebar()
        ];
    }

    public function personalMessagesSystemAction()
    {
        if (!$this->user()->logedIn()) {
            return $this->forwadToLogin();
        }

        $user = $this->user()->get();

        $mModel = new Message();
        $systembox = $mModel->getSystembox($user->id, $this->params('page'));

        return [
            'paginator' => $systembox['paginator'],
            'messages'  => $this->preparePersonalMessages($systembox['messages']),
            'sidebar'   => $this->sidebar()
        ];
    }

    public function personalMessagesUserAction()
    {
        if (!$this->user()->logedIn()) {
            return $this->forwadToLogin();
        }

        $users = new Users();

        $user = $users->find($this->params('user_id'))->current();
        if (!$user) {
            return $this->notFoundAction();
        }

        $logedInUser = $this->user()->get();

        $mModel = new Message();
        $dialogbox = $mModel->getDialogbox($logedInUser->id, $user->id, $this->params('page'));

        return [
            'paginator' => $dialogbox['paginator'],
            'messages'  => $this->preparePersonalMessages($dialogbox['messages']),
            'sidebar'   => $this->sidebar(),
            'urlParams' => [
                'user_id' => $user->id
            ]
        ];
    }

    public function notTakenPicturesAction()
    {
        if (!$this->user()->logedIn()) {
            return $this->forwadToLogin();
        }

        $this->sidebar();

        $pictures = $this->catalogue()->getPictureTable();

        $select = $pictures->select(true)
            ->where('owner_id = ?', $this->user()->get()->id)
            ->where('status IN (?)', [Picture::STATUS_NEW, Picture::STATUS_INBOX])
            ->order(['add_date DESC']);

        $paginator = new \Zend\Paginator\Paginator(
            new Zend1DbTableSelect($select)
        );

        $paginator
            ->setItemCountPerPage(16)
            ->setCurrentPageNumber($this->params('page'));

        $select->limitPage($paginator->getCurrentPageNumber(), $paginator->getItemCountPerPage());

        $picturesData = $this->pic()->listData($select, [
            'width' => 4
        ]);

        return [
            'paginator'    => $paginator,
            'picturesData' => $picturesData,
        ];
    }

    public function clearSystemMessagesAction()
    {
        if (!$this->user()->logedIn()) {
            return $this->forwadToLogin();
        }

        $mModel = new Message();
        $mModel->deleteAllSystem($this->user()->get()->id);

        return $this->redirect()->toRoute('account/personal-messages/system');
    }

    public function clearSentMessagesAction()
    {
        if (!$this->user()->logedIn()) {
            return $this->forwadToLogin();
        }

        $mModel = new Message();
        $mModel->deleteAllSent($this->user()->get()->id);

        return $this->redirect()->toRoute('account/personal-messages/sent');
    }

    public function accessAction()
    {
        if (!$this->user()->logedIn()) {
            return $this->forwadToLogin();
        }

        $this->sidebar();

        $form = new Application_Form_Account_Password([
            'action' => $this->_helper->url->url([]),
        ]);

        $request = $this->getRequest();

        if ($request->isPost() && $form->isValid($request->getPost())) {
            $values = $form->getValues();

            $user = $this->user()->get();

            $uTable = new Users();

            $uRow = $uTable->fetchRow([
                'id = ?'                                  => $user->id,
                'password = ' . Users::passwordHashExpr() => $values['password_old']
            ]);

            if (!$uRow) {

                $form->password_old->addError('Текущий пароль введен неверно');

            } else {

                $passwordExpr = $uTable->getAdapter()->quoteInto(Users::passwordHashExpr(), $values['password']);

                $user->password = new Zend_Db_Expr($passwordExpr);
                $user->save();

                $this->_helper->flashMessenger->addMessage('Пароль успешно изменён');

                return $this->redirect()->toRoute('account/access');
            }
        }

        return [
            'formPassword' => $form
        ];
    }

    public function deleteAction()
    {
        if (!$this->user()->logedIn()) {
            return $this->forwadToLogin();
        }

        $request = $this->getRequest();

        $form = new Application_Form_Account_Delete([
            'action' => $this->_helper->url->url([]),
        ]);

        if ($request->isPost() && $form->isValid($request->getPost())) {
            $values = $form->getValues();

            $user = $this->user()->get();

            $usersService = $this->getInvokeArg('bootstrap')->getResource('users');

            $valid = $usersService->checkPassword($user->id, $values['password']);

            if (!$valid) {

                $form->password->addError('Пароль введен неверно');

            } else {

                $user->deleted = true;
                $user->save();

                Zend_Auth::getInstance()->clearIdentity();
                $this->user()->clearRememberCookie();

                return $this->render('deleted');
            }
        }

        $this->sidebar();
        return [
            'form' => $form
        ];
    }

    public function specsConflictsAction()
    {
        if (!$this->user()->logedIn()) {
            return $this->forwadToLogin();
        }

        $service = new Application_Service_Specifications();

        $filter = $this->params('conflict', '0');
        $page = (int)$this->params('page');

        $userId = $this->user()->get()->id;

        $data = $service->getConflicts($userId, $filter, $page, 50);
        $conflicts = $data['conflicts'];
        $paginator = $data['paginator'];

        $userTable = new Users();
        $carTable = new Cars();
        $engineTable = new Engines();

        $language = $this->language();

        foreach ($conflicts as &$conflict) {
            foreach ($conflict['values'] as &$value) {
                $value['user'] = $userTable->find($value['userId'])->current();
            }

            switch ($conflict['itemTypeId']) {
                case Application_Service_Specifications::ITEM_TYPE_CAR:
                    $car = $carTable->find($conflict['itemId'])->current();
                    $conflict['object'] = $car ? $car->getFullName($language) : null;
                    $conflict['url'] = $this->url()->fromRoute('cars', [
                        'action' => 'car-specifications-editor',
                        'car_id' => $conflict['itemId'],
                        'tab'    => 'spec'
                    ]);
                    break;
                case Application_Service_Specifications::ITEM_TYPE_ENGINE:
                    $engine = $engineTable->find($conflict['itemId'])->current();
                    $conflict['object'] = $engine ? 'Двигатель ' . $engine->caption : null;
                    $conflict['url'] = $this->url()->fromRoute('cars', [
                        'action'    => 'engine-spec-editor',
                        'engine_id' => $conflict['itemId'],
                        'tab'       => 'engine'
                    ]);
                    break;
            }
        }
        unset($conflict);

        $this->sidebar();

        return [
            'filter'    => (string)$filter,
            'conflicts' => $conflicts,
            'paginator' => $paginator,
            'weight'    => $this->user()->get()->specs_weight
        ];
    }

    public function contactsAction()
    {
        if (!$this->user()->logedIn()) {
            return $this->forwadToLogin();
        }

        $this->sidebar();

        $user = $this->user()->get();

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

        return [
            'users' => $users
        ];
    }
}