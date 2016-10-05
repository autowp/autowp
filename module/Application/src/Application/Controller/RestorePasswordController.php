<?php

namespace Application\Controller;

use Zend\Authentication\AuthenticationService;
use Zend\Mail;
use Zend\Mvc\Controller\AbstractActionController;
use Zend\Form\Form;

use Application\Auth\Adapter\Id as IdAuthAdapter;
use Application\Model\DbTable\User;
use Application\Model\DbTable\User\PasswordRemind as UserPasswordRemind;
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

    public function __construct(UsersService $service, Form $restorePasswordForm, Form $newPasswordForm, $transport)
    {
        $this->service = $service;
        $this->restorePasswordForm = $restorePasswordForm;
        $this->newPasswordForm = $newPasswordForm;
        $this->transport = $transport;
    }

    public function indexAction()
    {
        $message = '';
        $success = false;

        $request = $this->getRequest();

        if ($this->getRequest()->isPost()) {
            $this->restorePasswordForm->setData($this->params()->fromPost());

            if ($this->restorePasswordForm->isValid()) {
                $values = $this->restorePasswordForm->getData();

                $users = new User();
                $user = $users->fetchRow([
                    'e_mail = ?' => (string)$values['email'],
                    'not deleted'
                ]);

                if ($user) {

                    $code = $this->service->createRestorePasswordToken($user->id);

                    $uri = $this->hostManager->getUriByLanguage($user->language);

                    $url = $this->url()->fromRoute('restorepassword/new', [
                        'code' => $code
                    ], [
                        'force_canonical' => true,
                        'uri'             => $uri
                    ]);

                    $message = sprintf(
                        $this->translate('restore-password/new-password/mail/body-%s', 'default', $user->language),
                        $url
                    );

                    $mail = new Mail\Message();
                    $mail
                        ->setEncoding('utf-8')
                        ->setBody($message)
                        ->setFrom('no-reply@autowp.ru', 'robot autowp.ru')
                        ->addTo($user->e_mail, $user->getCompoundName())
                        ->setSubject($this->translate('restore-password/new-password/mail/subject', 'default', $user->language));

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

        $uprTable = new UserPasswordRemind();

        $uprRow = $uprTable->fetchRow([
            'hash = ?' => $code,
            'created > DATE_SUB(NOW(), INTERVAL 10 DAY)'
        ]);

        if (!$uprRow) {
            return $this->notFoundAction();
        }

        $user = $uprRow->findParentRow(User::class);

        if (!$user) {
            return $this->notFoundAction();
        }

        if ($this->getRequest()->isPost()) {
            $this->newPasswordForm->setData($this->params()->fromPost());

            if ($this->newPasswordForm->isValid()) {
                $values = $this->newPasswordForm->getData();

                $this->service->setPassword($user, $values['password']);

                $uprRow->delete();

                $adapter = new IdAuthAdapter();
                $adapter->setIdentity($user->id);

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