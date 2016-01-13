<?php

class RegistrationController extends Zend_Controller_Action
{
    public function indexAction()
    {
        $form = new Application_Form_Registration(array(
            'action' => $this->_helper->url->url()
        ));

        $request = $this->getRequest();

        if ($request->isPost() && $form->isValid($request->getPost())) {
            $values = $form->getValues();

            $usersService = $this->getInvokeArg('bootstrap')->getResource('users');
            $usersService->addUser(array(
                'email'    => $values['email'],
                'password' => $values['password'],
                'name'     => $values['name'],
                'ip'       => $request->getServer('REMOTE_ADDR')
            ), $this->_helper->language());

            return $this->_redirect($this->_helper->url->url(array(
                'action' => 'ok'
            )));
        }
        $this->view->form = $form;
    }

    public function okAction()
    {
    }
}