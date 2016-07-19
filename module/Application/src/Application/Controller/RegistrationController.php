<?php

namespace Application\Controller;

use Zend\Form\Form;
use Zend\Mvc\Controller\AbstractActionController;

use Application\Service\UsersService;

class RegistrationController extends AbstractActionController
{
    /**
     * @var UsersService
     */
    private $service;

    /**
     * @var Form
     */
    private $form;

    public function __construct(UsersService $service, Form $form)
    {
        $this->service = $service;
        $this->form = $form;
    }

    public function indexAction()
    {
        $this->form->setAttribute('action', $this->url()->fromRoute('registration'));

        $request = $this->getRequest();

        if ($request->isPost()) {
            $this->form->setData($this->params()->fromPost());
            if ($this->form->isValid()) {
                $values = $this->form->getData();

                $this->service->addUser([
                    'email'    => $values['email'],
                    'password' => $values['password'],
                    'name'     => $values['name'],
                    'ip'       => $request->getServer('REMOTE_ADDR')
                ], $this->language());

                return $this->redirect()->toUrl(
                    $this->url()->fromRoute('registration/ok')
                );
            }
        }
        return [
            'form' => $this->form
        ];
    }

    public function okAction()
    {
    }
}