<?php

namespace Application\Controller;

use Zend\Authentication\AuthenticationService;
use Zend\Form\Form;
use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\ViewModel;

use Autowp\Forums\Forums;
use Autowp\Message\MessageService;
use Autowp\User\Auth\Adapter\Id as IdAuthAdapter;
use Autowp\User\Model\User;
use Autowp\User\Model\UserRename;

use Application\Model\Item;
use Application\Model\Picture;
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
    private $changePasswordForm;

    /**
     * @var Form
     */
    private $deleteUserForm;

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
     * @var Picture
     */
    private $picture;

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
        Form $changePasswordForm,
        Form $deleteUserForm,
        array $hosts,
        SpecificationsService $specsService,
        MessageService $message,
        UserRename $userRename,
        Picture $picture,
        Item $item,
        Forums $forums,
        User $userModel
    ) {

        $this->service = $service;
        $this->changePasswordForm = $changePasswordForm;
        $this->deleteUserForm = $deleteUserForm;
        $this->hosts = $hosts;
        $this->specsService = $specsService;
        $this->message = $message;
        $this->userRename = $userRename;
        $this->picture = $picture;
        $this->item = $item;
        $this->forums = $forums;
        $this->userModel = $userModel;
    }

    private function forwardToLogin()
    {
        return $this->redirect()->toUrl('/ng/login');
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
}
