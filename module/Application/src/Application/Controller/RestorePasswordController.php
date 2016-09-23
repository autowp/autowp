<?php

namespace Application\Controller;

use Zend\Mail;
use Zend\Mvc\Controller\AbstractActionController;
use Zend\Form\Form;

use Application\Auth\Adapter\Id as IdAuthAdapter;
use Application\Service\UsersService;

use User_Password_Remind;
use Users;

use Zend_Auth;

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

                $users = new Users();
                $user = $users->fetchRow([
                    'e_mail = ?' => (string)$values['email'],
                    'not deleted'
                ]);

                if ($user) {

                    $code = $this->service->createRestorePasswordToken($user->id);

                    $url = $this->url()->fromRoute('restorepassword/new', [
                        'code' => $code
                    ], [
                        'force_canonical' => true
                    ]);

                    $message = 'Для ввода нового пароля перейдите по ссылке: ' . $url . PHP_EOL . PHP_EOL .
                               'С Уважением, робот www.autowp.ru' . PHP_EOL;

                    $mail = new Mail\Message();
                    $mail
                        ->setEncoding('utf-8')
                        ->setBody($message)
                        ->setFrom('no-reply@autowp.ru', 'robot autowp.ru')
                        ->addTo($user->e_mail, $user->getCompoundName())
                        ->setSubject('Восстановление пароля');

                    $this->transport->send($mail);

                    $message = 'На ваш e-mail отправлены дальнейшие инструкции';
                    $success = true;
                } else {
                    $message = 'Пользователь с таким e-mail не найден';
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

        $uprTable = new User_Password_Remind();

        $uprRow = $uprTable->fetchRow([
            'hash = ?' => $code,
            'created > DATE_SUB(NOW(), INTERVAL 10 DAY)'
        ]);

        if (!$uprRow) {
            return $this->notFoundAction();
        }

        $user = $uprRow->findParentUsers();

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

                $result = Zend_Auth::getInstance()->authenticate($adapter);

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