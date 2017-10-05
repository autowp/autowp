<?php

namespace Application\Controller;

use Zend\Authentication\AuthenticationService;
use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\ViewModel;

use Autowp\Forums\Forums;
use Autowp\Message\MessageService;
use Autowp\User\Auth\Adapter\Id as IdAuthAdapter;
use Autowp\User\Model\User;

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
     * @var SpecificationsService
     */
    private $specsService = null;

    /**
     * @var MessageService
     */
    private $message;

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
        SpecificationsService $specsService,
        MessageService $message,
        Picture $picture,
        Item $item,
        Forums $forums,
        User $userModel
    ) {

        $this->service = $service;
        $this->specsService = $specsService;
        $this->message = $message;
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
