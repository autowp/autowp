<?php

class RestorepasswordController extends Zend_Controller_Action
{
    public function indexAction()
    {
        $form = new Application_Form_RestorePassword(array(
            'action' => $this->_helper->url->url(),
        ));

        $message = '';
        $success = false;

        $request = $this->getRequest();

        if ($request->isPost() && $form->isValid($request->getPost())) {
            $values = $form->getValues();

            $users = new Users();
            $user = $users->fetchRow([
                'e_mail = ?' => (string)$values['email'],
                'not deleted'
            ]);

            if ($user) {

                $usersService = $this->getInvokeArg('bootstrap')->getResource('users');
                
                $code = $usersService->createRestorePasswordToken($user->id);

                $url = $this->view->serverUrl($this->_helper->url->url(array(
                    'action' => 'new',
                    'code'   => $code
                )));

                $message = 'Для ввода нового пароля перейдите по ссылке: ' . $url . PHP_EOL . PHP_EOL .
                           'С Уважением, робот www.autowp.ru' . PHP_EOL;

                $mail = new Zend_Mail('utf-8');
                $mail->setBodyText($message)
                     ->setFrom('no-reply@autowp.ru', 'robot autowp.ru')
                     ->addTo($user->e_mail, $user->getCompoundName())
                     ->setSubject('Восстановление пароля')
                     ->send();

                $message = 'На ваш e-mail отправлены дальнейшие инструкции';
                $success = true;
            } else {
                $message = 'Пользователь с таким e-mail не найден';
            }
        }

        $this->view->assign(array(
            'message' => $message,
            'success' => $success,
            'form'    => $form
        ));
    }

    public function newAction()
    {
        $code = (string)$this->_getParam('code');

        $uprTable = new User_Password_Remind();

        $uprRow = $uprTable->fetchRow(array(
            'hash = ?' => $code,
            'created > DATE_SUB(NOW(), INTERVAL 10 DAY)'
        ));

        if (!$uprRow) {
            return $this->_forward('notfound', 'error');
        }

        $user = $uprRow->findParentUsers();

        if (!$user) {
            return $this->_forward('notfound', 'error');
        }

        $form = new Application_Form_NewPassword(array(
            'action' => $this->_helper->url->url(),
        ));

        $request = $this->getRequest();

        if ($request->isPost() && $form->isValid($request->getPost())) {
            $values = $form->getValues();
            
            $usersService = $this->getInvokeArg('bootstrap')->getResource('users');
            $usersService->setPassword($user, $values['password']);

            $uprRow->delete();

            $adapter = new Project_Auth_Adapter_Id();
            $adapter->setIdentity($user->id);

            $result = Zend_Auth::getInstance()->authenticate($adapter);

            if ($result->isValid()) {
                return $this->_redirect($this->_helper->url->url(array(
                    'code'   => null,
                    'action' => 'saved'
                )));
            }
        }

        $this->view->assign(array(
            'form' => $form
        ));
    }

    public function savedAction()
    {
    }
}