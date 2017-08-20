<?php

namespace Application\Controller;

use Zend\Authentication\AuthenticationService;
use Zend\Mail;
use Zend\Mvc\Controller\AbstractActionController;
use Zend\Form\Form;

use Autowp\User\Auth\Adapter\Id as IdAuthAdapter;
use Autowp\User\Model\User;
use Autowp\User\Model\UserPasswordRemind;

use Application\HostManager;
use Application\Service\UsersService;

class RestorePasswordController extends AbstractActionController
{
    /**
     * @var UsersService
     */
    private $service;

    /**
     * @var Form
     */
    private $restorePasswordForm;

    /**
     * @var Form
     */
    private $newPasswordForm;

    private $transport;

    /**
     * @var HostManager
     */
    private $hostManager;

    /**
     * @var UserPasswordRemind
     */
    private $userPasswordRemind;

    /**
     * @var User
     */
    private $userModel;

    public function __construct(
        UsersService $service,
        Form $restorePasswordForm,
        Form $newPasswordForm,
        $transport,
        HostManager $hostManager,
        UserPasswordRemind $userPasswordRemind,
        User $userModel
    ) {
        $this->service = $service;
        $this->restorePasswordForm = $restorePasswordForm;
        $this->newPasswordForm = $newPasswordForm;
        $this->transport = $transport;
        $this->hostManager = $hostManager;
        $this->userPasswordRemind = $userPasswordRemind;
        $this->userModel = $userModel;
    }

    public function indexAction()
    {
        $message = '';
        $success = false;

        if ($this->getRequest()->isPost()) {
            $this->restorePasswordForm->setData($this->params()->fromPost());

            if ($this->restorePasswordForm->isValid()) {
                $values = $this->restorePasswordForm->getData();

                $user = $this->userModel->getRow([
                    'email'       => (string)$values['email'],
                    'not_deleted' => true
                ]);

                if ($user) {
                    $code = $this->userPasswordRemind->createToken($user['id']);

                    $uri = $this->hostManager->getUriByLanguage($user['language']);

                    $url = $this->url()->fromRoute('restorepassword/new', [
                        'code' => $code
                    ], [
                        'force_canonical' => true,
                        'uri'             => $uri
                    ]);

                    $message = sprintf(
                        $this->translate('restore-password/new-password/mail/body-%s', 'default', $user['language']),
                        $url
                    );

                    $mail = new Mail\Message();
                    $mail
                        ->setEncoding('utf-8')
                        ->setBody($message)
                        ->setFrom('no-reply@autowp.ru', 'robot autowp.ru')
                        ->addTo($user['e_mail'], $user['name'])
                        ->setSubject($this->translate(
                            'restore-password/new-password/mail/subject',
                            'default',
                            $user['language']
                        ));

                    $this->transport->send($mail);

                    $message = $this->translate('restore-password/new-password/instructions-sent');
                    $success = true;
                } else {
                    $message = $this->translate('restore-password/new-password/email-not-found');
                }
            }
        }

        return [
            'message' => $message,
            'success' => $success,
            'form'    => $this->restorePasswordForm
        ];
    }

    public function newAction()
    {
        $code = (string)$this->params('code');

        $userId = $this->userPasswordRemind->getUserId($code);

        if (! $userId) {
            return $this->notFoundAction();
        }

        $user = $this->userModel->getRow((int)$userId);

        if (! $user) {
            return $this->notFoundAction();
        }

        if ($this->getRequest()->isPost()) {
            $this->newPasswordForm->setData($this->params()->fromPost());

            if ($this->newPasswordForm->isValid()) {
                $values = $this->newPasswordForm->getData();

                $this->service->setPassword($user, $values['password']);

                $this->userPasswordRemind->deleteToken($code);

                $adapter = new IdAuthAdapter($this->userModel);
                $adapter->setIdentity($user['id']);

                $auth = new AuthenticationService();
                $result = $auth->authenticate($adapter);

                if ($result->isValid()) {
                    return $this->redirect()->toUrl(
                        $this->url()->fromRoute('restorepassword/saved')
                    );
                }
            }
        }

        return [
            'form' => $this->newPasswordForm
        ];
    }

    public function savedAction()
    {
    }
}
