<?php

namespace Application\Controller;

use DateTimeZone;
use Exception;
use Imagick;
use Locale;

use Zend\Authentication\AuthenticationService;
use Zend\Db\Sql;
use Zend\Db\TableGateway\TableGateway;
use Zend\Form\Form;
use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\ViewModel;

use Autowp\ExternalLoginService\PluginManager as ExternalLoginServices;
use Autowp\Forums\Forums;
use Autowp\Message\MessageService;
use Autowp\User\Auth\Adapter\Id as IdAuthAdapter;
use Autowp\User\Model\User;
use Autowp\User\Model\UserRename;

use Application\Controller\LoginController;
use Application\Model\Item;
use Application\Model\Picture;
use Application\Model\UserAccount;
use Application\Service\SpecificationsService;
use Application\Service\UsersService;

class AccountController extends AbstractActionController
{
    /**
     * @var UsersService
     */
    private $service;

    /**
     * @var Form
     */
    private $emailForm;

    /**
     * @var Form
     */
    private $profileForm;

    /**
     * @var Form
     */
    private $settingsForm;

    /**
     * @var Form
     */
    private $photoForm;

    /**
     * @var Form
     */
    private $changePasswordForm;

    /**
     * @var Form
     */
    private $deleteUserForm;

    /**
     * @var ExternalLoginServices
     */
    private $externalLoginServices;

    /**
     * @var array
     */
    private $hosts = [];

    /**
     * @var SpecificationsService
     */
    private $specsService = null;

    /**
     * @var MessageService
     */
    private $message;

    /**
     * @var UserRename
     */
    private $userRename;

    /**
     * @var UserAccount
     */
    private $userAccount;

    /**
     * @var Picture
     */
    private $picture;

    /**
     * @var TableGateway
     */
    private $loginStateTable;

    /**
     * @var Item
     */
    private $item;

    /**
     * @var Forums
     */
    private $forums;

    /**
     * @var User
     */
    private $userModel;

    public function __construct(
        UsersService $service,
        Form $emailForm,
        Form $profileForm,
        Form $settingsForm,
        Form $photoForm,
        Form $changePasswordForm,
        Form $deleteUserForm,
        ExternalLoginServices $externalLoginServices,
        array $hosts,
        SpecificationsService $specsService,
        MessageService $message,
        UserRename $userRename,
        UserAccount $userAccount,
        Picture $picture,
        TableGateway $loginStateTable,
        Item $item,
        Forums $forums,
        User $userModel
    ) {

        $this->service = $service;
        $this->emailForm = $emailForm;
        $this->profileForm = $profileForm;
        $this->settingsForm = $settingsForm;
        $this->photoForm = $photoForm;
        $this->changePasswordForm = $changePasswordForm;
        $this->deleteUserForm = $deleteUserForm;
        $this->externalLoginServices = $externalLoginServices;
        $this->hosts = $hosts;
        $this->specsService = $specsService;
        $this->message = $message;
        $this->userRename = $userRename;
        $this->userAccount = $userAccount;
        $this->picture = $picture;
        $this->loginStateTable = $loginStateTable;
        $this->item = $item;
        $this->forums = $forums;
        $this->userModel = $userModel;
    }

    private function forwardToLogin()
    {
        return $this->forward()->dispatch(LoginController::class, [
            'action' => 'index'
        ]);
    }

    public function sidebar()
    {
        $user = $this->user()->get();

        $picsCount = $this->picture->getCount([
            'status' => Picture::STATUS_ACCEPTED,
            'user'   => $user['id']
        ]);

        $notTakenPicturesCount = $this->picture->getCount([
            'status' => Picture::STATUS_INBOX,
            'user'   => $user['id']
        ]);

        return [
            'smCount'               => $this->message->getSystemCount($user['id']),
            'newSmCount'            => $this->message->getNewSystemCount($user['id']),
            'pmCount'               => $this->message->getInboxCount($user['id']),
            'newPmCount'            => $this->message->getInboxNewCount($user['id']),
            'omCount'               => $this->message->getSentCount($user['id']),
            'notTakenPicturesCount' => $notTakenPicturesCount,
            'subscribesCount'       => $this->forums->getSubscribedTopicsCount($user['id']),
            'picsCount'             => $picsCount
        ];
    }

    public function addAccountFailedAction()
    {
        if (! $this->user()->logedIn()) {
            return $this->forwardToLogin();
        }

        return [
            'sidebar' => $this->sidebar()
        ];
    }

    /**
     * @param string $serviceId
     * @return \Autowp\ExternalLoginService\AbstractService
     */
    private function getExternalLoginService($serviceId)
    {
        $service = $this->externalLoginServices->get($serviceId);

        if (! $service) {
            throw new Exception("Service `$serviceId` not found");
        }
        return $service;
    }

    public function accountsAction()
    {
        $user = $this->user()->get();

        if (! $user) {
            return $this->forwardToLogin();
        }

        $accounts = [];
        foreach ($this->userAccount->getAccounts($user['id']) as $row) {
            $accounts[] = array_replace($row, [
                'canRemove' => $this->canRemoveAccount($row['service_id']),
                'removeUrl' => $this->url()->fromRoute('account/remove-account', [
                    'service' => $row['service_id']
                ])
            ]);
        }

        $request = $this->getRequest();

        if ($request->isPost()) {
            $serviceId = $this->params()->fromPost('type');
            $service = $this->getExternalLoginService($serviceId);

            $loginUrl = $service->getLoginUrl();

            $this->loginStateTable->insert([
                'state'    => $service->getState(),
                'time'     => new Sql\Expression('now()'),
                'user_id'  => $user['id'],
                'language' => $this->language(),
                'service'  => $serviceId,
                'url'      => $this->url()->fromRoute('account/accounts')
            ]);

            return $this->redirect()->toUrl($loginUrl);
        }

        return [
            'sidebar'  => $this->sidebar(),
            'accounts' => $accounts,
            'types'    => [
                'facebook'    => 'Facebook',
                'vk'          => 'VK',
                'google-plus' => 'Google+',
                'twitter'     => 'Twitter',
                'github'      => 'Github',
                'linkedin'    => 'Linkedin'
            ]
        ];
    }

    private function canRemoveAccount(string $serviceId): bool
    {
        if (! $this->user()->logedIn()) {
            return false;
        }

        if ($this->user()->get()['e_mail']) {
            return true;
        }

        $haveAccounts = $this->userAccount->haveAccountsForOtherServices(
            $this->user()->get()['id'],
            $serviceId
        );
        if ($haveAccounts) {
            return true;
        }

        return false;
    }

    public function removeAccountAction()
    {
        $user = $this->user()->get();
        if (! $user) {
            return $this->forwardToLogin();
        }

        $service = (string)$this->params('service');

        if (! $this->canRemoveAccount($service)) {
            return $this->forward()->dispatch(self::class, [
                'action' => 'remove-account-failed'
            ]);
        }

        $this->userAccount->removeAccount($user['id'], $service);

        $this->flashMessenger()->addSuccessMessage($this->translate('account/accounts/removed'));

        return $this->redirect()->toRoute('account/accounts');
    }

    public function removeAccountFailedAction()
    {
    }

    public function profileAction()
    {
        $user = $this->user()->get();

        if (! $user) {
            return $this->forwardToLogin();
        }

        $request = $this->getRequest();

        $this->profileForm->setAttribute('action', $this->url()->fromRoute('account/profile', [
            'form' => 'profile'
        ]));

        $this->profileForm->setData([
            'name' => $user['name']
        ]);
        if ($request->isPost() && $this->params('form') == 'profile') {
            $this->profileForm->setData($this->params()->fromPost());
            if ($this->profileForm->isValid()) {
                $values = $this->profileForm->getData();

                $oldName = $user['name'];

                $this->userModel->getTable()->update([
                    'name' => $values['name']
                ], [
                    'id' => $user['id']
                ]);

                $newName = $values['name'];

                if ($oldName != $newName) {
                    $this->userRename->add($user['id'], $oldName, $newName);
                }

                $this->flashMessenger()->addSuccessMessage($this->translate('account/profile/saved'));

                return $this->redirect()->toRoute();
            }
        }

        if ($request->isPost() && $this->params('form') == 'reset-photo') {
            $oldImageId = $user['img'];
            if ($oldImageId) {
                $this->userModel->getTable()->update([
                    'img' => null
                ], [
                    'id' => $user['id']
                ]);

                $this->imageStorage()->removeImage($oldImageId);
            }

            $this->flashMessenger()->addSuccessMessage($this->translate('account/profile/photo/deleted'));

            return $this->redirect()->toRoute();
        }

        $this->photoForm->setAttribute('action', $this->url()->fromRoute('account/profile', [
            'form' => 'photo'
        ]));

        if ($request->isPost() && $this->params('form') == 'photo') {
            $data = array_merge_recursive(
                $this->getRequest()->getPost()->toArray(),
                $this->getRequest()->getFiles()->toArray()
            );
            $this->photoForm->setData($data);
            if ($this->photoForm->isValid()) {
                $imageStorage = $this->imageStorage();
                $imageSampler = $imageStorage->getImageSampler();

                $imagick = new Imagick();
                if (! $imagick->readImage($data['photo']['tmp_name'])) {
                    throw new Exception("Error loading image");
                }
                $format = $imageStorage->getFormat('photo');
                $imageSampler->convertImagick($imagick, $format);

                $newImageId = $imageStorage->addImageFromImagick($imagick, 'user');

                $imagick->clear();

                $oldImageId = $user['img'];

                $this->userModel->getTable()->update([
                    'img' => $newImageId
                ], [
                    'id' => $user['id']
                ]);

                if ($oldImageId) {
                    $imageStorage->removeImage($oldImageId);
                }

                $this->flashMessenger()->addSuccessMessage($this->translate('account/profile/photo/saved'));

                return $this->redirect()->toRoute('account/profile');
            }
        }

        $language = $this->language();

        foreach (DateTimeZone::listAbbreviations() as $group) {
            foreach ($group as $timeZone) {
                $tzId = $timeZone['timezone_id'];
                if ($tzId) {
                    $list[] = $tzId;
                }
            }
        }
        sort($list, SORT_STRING);
        $list = array_combine($list, $list);

        foreach (array_keys($this->hosts) as $language) {
            $languages[$language] = Locale::getDisplayLanguage($language, $language);
        }

        $this->settingsForm->setAttribute('action', $this->url()->fromRoute('account/profile', [
            'form' => 'settings'
        ]));
        $this->settingsForm->get('language')->setValueOptions($languages);
        $this->settingsForm->get('timezone')->setValueOptions($list);

        $this->settingsForm->setData([
            'timezone' => $user['timezone'],
            'language' => $user['language']
        ]);

        if ($request->isPost() && $this->params('form') == 'settings') {
            $this->settingsForm->setData($this->params()->fromPost());
            if ($this->settingsForm->isValid()) {
                $values = $this->settingsForm->getData();

                $this->userModel->getTable()->update([
                    'timezone' => $values['timezone'],
                    'language' => $values['language']
                ], [
                    'id' => $user['id']
                ]);

                $this->flashMessenger()->addSuccessMessage($this->translate('account/profile/saved'));

                return $this->redirect()->toRoute();
            }
        }

        return [
            'settingsForm' => $this->settingsForm,
            'profileForm'  => $this->profileForm,
            'photoForm'    => $this->photoForm,
            'sidebar'      => $this->sidebar()
        ];
    }

    public function emailAction()
    {
        $user = $this->user()->get();
        if (! $user) {
            return $this->forwardToLogin();
        }

        $request = $this->getRequest();

        $this->emailForm->setAttribute('action', $this->url()->fromRoute('account/email'));
        $this->emailForm->setData([
            'email' => $user['e_mail']
        ]);
        if ($request->isPost()) {
            $this->emailForm->setData($this->params()->fromPost());
            if ($this->emailForm->isValid()) {
                $values = $this->emailForm->getData();

                $this->service->changeEmailStart($user, $values['email'], $this->language());

                $this->flashMessenger()->addSuccessMessage(
                    $this->translate('users/change-email/confirmation-message-sent')
                );

                return $this->redirect()->toRoute();
            }
        }

        return [
            'sidebar' => $this->sidebar(),
            'form'    => $this->emailForm
        ];
    }

    public function emailcheckAction()
    {
        $code = $this->params('email_check_code');
        $user = $this->service->emailChangeFinish($code);

        $template = 'application/account/emailcheck-fail';

        if ($user) {
            if (! $this->user()->logedIn()) {
                $adapter = new IdAuthAdapter($this->userModel);
                $adapter->setIdentity($user['id']);
                $auth = new AuthenticationService();
                $result = $auth->authenticate($adapter);

                if ($result->isValid()) {
                    // hmmm...
                }
            }

            $template = 'application/account/emailcheck-ok';
        }

        $viewModel = new ViewModel();

        $viewModel->setTemplate($template);

        if ($this->user()->logedIn()) {
            $viewModel->setVariables([
                'sidebar' => $this->sidebar()
            ]);
        }

        return $viewModel;
    }

    public function notTakenPicturesAction()
    {
        if (! $this->user()->logedIn()) {
            return $this->forwardToLogin();
        }

        $paginator = $this->picture->getPaginator([
            'user'   => $this->user()->get()['id'],
            'status' => Picture::STATUS_INBOX,
            'order'  => 'add_date_desc'
        ]);

        $paginator
            ->setItemCountPerPage(16)
            ->setCurrentPageNumber($this->params('page'));

        $picturesData = $this->pic()->listData($paginator->getCurrentItems(), [
            'width' => 4
        ]);

        return [
            'paginator'    => $paginator,
            'picturesData' => $picturesData,
            'sidebar'      => $this->sidebar()
        ];
    }

    public function accessAction()
    {
        $user = $this->user()->get();
        if (! $user) {
            return $this->forwardToLogin();
        }

        $request = $this->getRequest();

        if ($request->isPost()) {
            $this->changePasswordForm->setData($this->params()->fromPost());
            if ($this->changePasswordForm->isValid()) {
                $values = $this->changePasswordForm->getData();

                $correct = $this->service->checkPassword($user['id'], $values['password_old']);

                if (! $correct) {
                    $this->changePasswordForm->get('password_old')->setMessages([
                        $this->translate('account/access/change-password/current-password-is-incorrect')
                    ]);
                } else {
                    $this->service->setPassword($user, $values['password']);

                    $this->flashMessenger()->addSuccessMessage(
                        $this->translate('account/access/change-password/saved')
                    );

                    return $this->redirect()->toRoute('account/access');
                }
            }
        }

        return [
            'sidebar'      => $this->sidebar(),
            'formPassword' => $this->changePasswordForm
        ];
    }

    public function deleteAction()
    {
        if (! $this->user()->logedIn()) {
            return $this->forwardToLogin();
        }

        $request = $this->getRequest();

        $this->deleteUserForm->setAttribute('action', $this->url()->fromRoute('account/delete'));

        if ($request->isPost()) {
            $this->deleteUserForm->setData($this->params()->fromPost());
            if ($this->deleteUserForm->isValid()) {
                $values = $this->deleteUserForm->getData();

                $user = $this->user()->get();

                $valid = $this->service->checkPassword($user['id'], $values['password']);

                if (! $valid) {
                    $this->deleteUserForm->get('password')->setMessages([
                        $this->translate('account/access/self-delete/password-is-incorrect')
                    ]);
                } else {
                    $this->service->markDeleted($user['id']);

                    $auth = new AuthenticationService();
                    $auth->clearIdentity();
                    $this->service->clearRememberCookie($this->language());

                    $viewModel = new ViewModel();

                    $viewModel->setTemplate('application/account/deleted');

                    return $viewModel;
                }
            }
        }

        return [
            'sidebar' => $this->sidebar(),
            'form'    => $this->deleteUserForm
        ];
    }

    public function specsConflictsAction()
    {
        if (! $this->user()->logedIn()) {
            return $this->forwardToLogin();
        }

        $filter = $this->params('conflict', '0');
        $page = (int)$this->params('page');

        $userId = $this->user()->get()['id'];

        $language = $this->language();

        $data = $this->specsService->getConflicts($userId, $filter, $page, 50, $language);
        $conflicts = $data['conflicts'];
        $paginator = $data['paginator'];

        foreach ($conflicts as &$conflict) {
            foreach ($conflict['values'] as &$value) {
                $value['user'] = $this->userModel->getRow((int)$value['userId']);
            }

            $car = $this->item->getRow(['id' => $conflict['itemId']]);
            $conflict['object'] = $car ? $this->car()->formatName($car, $language) : null;
            $conflict['url'] = $this->url()->fromRoute('cars/params', [
                'action'  => 'car-specifications-editor',
                'item_id' => $conflict['itemId'],
                'tab'     => 'spec'
            ]);
        }
        unset($conflict);

        return [
            'sidebar'   => $this->sidebar(),
            'filter'    => (string)$filter,
            'conflicts' => $conflicts,
            'paginator' => $paginator,
            'weight'    => $this->user()->get()['specs_weight']
        ];
    }

    public function contactsAction()
    {
        if (! $this->user()->logedIn()) {
            return $this->forwardToLogin();
        }

        $user = $this->user()->get();

        $userRows = $this->userModel->getRows([
            'in_contacts' => $user['id'],
            'order'       => ['users.deleted', 'users.name']
        ]);

        $users = [];
        foreach ($userRows as $row) {
            $users[] = $row;
        }

        return [
            'sidebar' => $this->sidebar(),
            'users'   => $users
        ];
    }
}
