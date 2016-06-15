<?php

class LoginController extends Zend_Controller_Action
{
    public function indexAction()
    {
        if ($this->_helper->user()->logedIn()) {
            return $this->render('loginsuccess');
        }

        $this->view->errorMessage = '';

        $form = new Application_Form_Login(array(
            'action' => $this->_helper->url->url(),
        ));

        $request = $this->getRequest();

        if ($request->isPost()) {
            if ($form->isValid($request->getPost())) {
                $values = $form->getValues();

                $usersService = $this->getInvokeArg('bootstrap')->getResource('users');
                $adapter = $usersService->getAuthAdapterLogin($values['login'], $values['password']);

                $result = Zend_Auth::getInstance()->authenticate($adapter);

                if ($result->isValid()) {
                    if ($values['remember']) {

                        $token = $usersService->createRememberToken($this->_helper->user()->get()->id);

                        $this->_helper->user()->setRememberCookie($token);
                    } else {
                        $this->_helper->user()->clearRememberCookie();
                    }

                    if ($url = $request->getServer('REQUEST_URI')) {
                        return $this->_redirect($url);
                    }

                    return $this->render('loginsuccess');
                } else {
                    // Invalid credentials
                    $this->view->errorMessage = $this->view->translate('login/login-or-password-is-incorrect');
                    $this->view->form = $form;
                }
            }
        }

        $services = array(
            'facebook'    => array(
                'name' => 'Facebook',
                'icon' => 'fa-facebook'
            ),
            'vk'          => array(
                'name' => 'VK',
                'icon' => 'fa-vk'
            ),
            'google-plus' => array(
                'name' => 'Google+',
                'icon' => 'fa-google-plus'
            ),
            'twitter'     => array(
                'name' => 'Twitter',
                'icon' => 'fa-twitter'
            ),
            'github'     => array(
                'name' => 'Github',
                'icon' => 'fa-github'
            ),
            'linkedin'     => array(
                'name' => 'LinkedIn',
                'icon' => 'fa-linkedin'
            ),
        );

        foreach ($services as $serviceId => &$service) {
            $service['url'] = $this->_helper->url->url(array(
                'module'     => 'default',
                'controller' => 'login',
                'action'     => 'start',
                'type'       => $serviceId
            ));
        }
        unset($service);


        $this->view->assign(array(
            'form'     => $form,
            'services' => $services
        ));
    }

    public function logoutAction()
    {
        Zend_Auth::getInstance()->clearIdentity();
        $this->_helper->user()->clearRememberCookie();
        $this->_helper->redirector('index');
    }

    /**
     * @param string $serviceId
     * @return Autowp_ExternalLoginService_Abstract
     */
    private function _getExternalLoginService($serviceId)
    {
        $serviceOptionsKey = $serviceId;

        $factory = $this->getInvokeArg('bootstrap')->getResource('externalloginservice');
        $service = $factory->getService($serviceId, $serviceOptionsKey, array(
            'redirect_uri' => 'http://en.wheelsage.org/login/callback'
        ));

        if (!$service) {
            throw new Exception("Service `$serviceId` not found");
        }
        return $service;
    }

    public function startAction()
    {
        if ($this->_helper->user()->logedIn()) {
            return $this->_redirect($this->_helper->url->url(array(
                'action' => 'index'
            )));
        }

        $serviceId = trim($this->getParam('type'));

        $service = $this->_getExternalLoginService($serviceId);

        $loginUrl = $service->getLoginUrl();

        $table = new LoginState();
        $row = $table->createRow(array(
            'state'    => $service->getState(),
            'time'     => new Zend_Db_Expr('now()'),
            'user_id'  => null,
            'language' => $this->_helper->language(),
            'service'  => $serviceId,
            'url'      => $this->_helper->url->url(array(
                'controller' => 'login',
                'action'     => 'index'
            ), 'default', true)
        ));

        $row->save();

        return $this->_redirect($loginUrl);
    }

    public function callbackAction()
    {
        $table = new LoginState();

        $state = (string)$this->getParam('state');
        if (!$state) { // twitter workaround
            $state = (string)$this->getParam('oauth_token');
        }

        $stateRow = $table->fetchRow(array(
            'state = ?' => $state
        ));

        if (!$stateRow) {
            return $this->_forward('notfound', 'error');
        }

        $params = $this->getRequest()->getQuery();

        if ($stateRow->language != $this->_helper->language()) {

            $hosts = $this->getInvokeArg('bootstrap')->getOption('hosts');

            if (!isset($hosts[$stateRow->language])) {
                throw new Exception("Host {$stateRow->language} not found");
            }

            return $this->_redirect(
                'http://' . $hosts[$stateRow->language]['hostname'] .
                    $this->_helper->url->url() . '?' . http_build_query($params)
            );
        }

        $service = $this->_getExternalLoginService($stateRow->service);
        $success = $service->callback($params);
        if (!$success) {
            throw new Exception("Error processing callback");
        }

        $data = $service->getData(array(
            'language' => $stateRow->language
        ));

        if (!$data) {
            throw new Exception("Error requesting data");
        }

        if (!$data->getExternalId()) {
            throw new Exception('external_id not found');
        }
        if (!$data->getName()) {
            throw new Exception('name not found');
        }

        $uaTable = new User_Account();

        $uaRow = $uaTable->fetchRow(array(
            'service_id = ?'  => $stateRow->service,
            'external_id = ?' => $data->getExternalId(),
        ));

        if (!$uaRow) {

            $usersService = $this->getInvokeArg('bootstrap')->getResource('users');

            $uTable = new Users();

            if ($stateRow->user_id) {
                $uRow = $uTable->find($stateRow->user_id)->current();
                if (!$uRow) {
                    throw new Exception("Account `{$stateRow->user_id}` not found");
                }
            } else {
                $uRow = $usersService->addUser(array(
                    'email'    => null,
                    'password' => uniqid(),
                    'name'     => $data->getName(),
                    'ip'       => $this->getRequest()->getServer('REMOTE_ADDR')
                ), $this->_helper->language());
            }

            if (!$uRow) {
                return $this->_forward('notfound', 'error');
            }

            $uaRow = $uaTable->fetchNew();
            $uaRow->setFromArray(array(
                'service_id'   => $stateRow->service,
                'external_id'  => $data->getExternalId(),
                'user_id'      => $uRow->id,
                'used_for_reg' => $stateRow->user_id ? 0 : 1
            ));

            if (!$stateRow->user_id) { // first login
                if ($photoUrl = $data->getPhotoUrl()) {
                    $photo = file_get_contents($photoUrl);

                    if ($photo) {

                        $imageStorage = $this->getInvokeArg('bootstrap')->getResource('imagestorage');
                        $imageSampler = $imageStorage->getImageSampler();

                        $imagick = new Imagick();
                        if (!$imagick->readImageBlob($photo)) {
                            throw new Exception("Error loading image");
                        }
                        $format = $imageStorage->getFormat('photo');
                        $imageSampler->convertImagick($imagick, $format);

                        $newImageId = $imageStorage->addImageFromImagick($imagick, 'user');

                        $imagick->clear();

                        $oldImageId = $uRow->img;
                        $uRow->img = $newImageId;
                        $uRow->save();
                        if ($oldImageId) {
                            $imageStorage->removeImage($oldImageId);
                        }
                    }
                }
            }

        } else {

            $uRow = $uaRow->findParentUsers();
            if (!$uRow) {
                throw new Exception('Not linked account row');
            }

        }

        $uaRow->setFromArray(array(
            'name' => $data->getName(),
            'link' => $data->getProfileUrl(),
        ));
        $uaRow->save();

        $url = $stateRow->url;

        $stateRow->delete();

        $adapter = new Project_Auth_Adapter_Id();
        $adapter->setIdentity($uRow->id);
        $authResult = Zend_Auth::getInstance()->authenticate($adapter);
        if ($authResult->isValid()) {
            return $this->_redirect($url);
        } else {
            // Invalid credentials
            throw new Exception('Error during login');
        }

    }
}