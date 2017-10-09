<?php

namespace Application\Controller;

use Zend\Authentication\AuthenticationService;
use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\ViewModel;

use Autowp\Forums\Forums;
use Autowp\Message\MessageService;
use Autowp\User\Auth\Adapter\Id as IdAuthAdapter;
use Autowp\User\Model\User;

use Application\Model\Picture;
use Application\Service\UsersService;

class AccountController extends AbstractActionController
{
    /**
     * @var UsersService
     */
    private $service;

    /**
     * @var MessageService
     */
    private $message;

    /**
     * @var Picture
     */
    private $picture;

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
        MessageService $message,
        Picture $picture,
        Forums $forums,
        User $userModel
    ) {

        $this->service = $service;
        $this->message = $message;
        $this->picture = $picture;
        $this->forums = $forums;
        $this->userModel = $userModel;
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

        /*if ($this->user()->logedIn()) {
            $viewModel->setVariables([
                'sidebar' => $this->sidebar()
            ]);
        }*/

        return $viewModel;
    }
}
