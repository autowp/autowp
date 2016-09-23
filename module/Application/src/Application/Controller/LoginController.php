<?php

namespace Application\Controller;

use Zend\Form\Form;
use Zend\Mvc\Controller\AbstractActionController;
use Zend\Uri\Http as HttpUri;
use Zend\View\Model\ViewModel;

use Application\Auth\Adapter\Id as IdAuthAdapter;
use Application\Service\UsersService;

use Autowp\ExternalLoginService\Factory as ExternalLoginServiceFactory;

use Zend_Auth;
use Zend_Db_Expr;

use Exception;
use Imagick;

use LoginState;
use User_Account;
use Users;

class LoginController extends AbstractActionController
{
    /**
     * @var UsersService
     */
    private $service;

    /**
     * @var Form
     */
    private $form;

    private $translator;

    /**
     * @var ExternalLoginServiceFactory
     */
    private $externalLoginFactory;

    /**
     * @var array
     */
    private $hosts = [];

    public function __construct(UsersService $service, Form $form, $translator,
            ExternalLoginServiceFactory $externalLoginFactory, array $hosts)
    {
        $this->service = $service;
        $this->form = $form;
        $this->translator = $translator;
        $this->externalLoginFactory = $externalLoginFactory;
        $this->hosts = $hosts;
    }

    public function indexAction()
    {
        if ($this->user()->logedIn()) {
            $viewModel = new ViewModel();
            return $viewModel->setTemplate('application/login/loginsuccess');
        }

        $errorMessage = '';

        $this->form->setAttribute('action', $this->url()->fromRoute('login'));

        $request = $this->getRequest();

        if ($request->isPost()) {
            $this->form->setData($this->params()->fromPost());
            if ($this->form->isValid()) {
                $values = $this->form->getData();

                $adapter = $this->service->getAuthAdapterLogin($values['login'], $values['password']);

                $result = Zend_Auth::getInstance()->authenticate($adapter);

                if ($result->isValid()) {
                    if ($values['remember']) {

                        $token = $this->service->createRememberToken($this->user()->get()->id);

                        $this->user()->setRememberCookie($token);
                    } else {
                        $this->user()->clearRememberCookie();
                    }

                    if ($url = $request->getServer('REQUEST_URI')) {
                        return $this->redirect()->toUrl($url);
                    }

                    $viewModel = new ViewModel();
                    return $viewModel->setTemplate('application/login/loginsuccess');
                } else {
                    // Invalid credentials
                    $errorMessage = $this->translator->translate('login/login-or-password-is-incorrect');
                }
            }
        }

        $services = [
            'facebook'    => [
                'name' => 'Facebook',
                'icon' => 'fa-facebook'
            ],
            'vk'          => [
                'name' => 'VK',
                'icon' => 'fa-vk'
            ],
            'google-plus' => [
                'name' => 'Google+',
                'icon' => 'fa-google-plus'
            ],
            'twitter'     => [
                'name' => 'Twitter',
                'icon' => 'fa-twitter'
            ],
            'github'     => [
                'name' => 'Github',
                'icon' => 'fa-github'
            ],
            'linkedin'     => [
                'name' => 'LinkedIn',
                'icon' => 'fa-linkedin'
            ],
        ];

        foreach ($services as $serviceId => &$service) {
            $service['url'] = $this->url()->fromRoute('login/start', [
                'type' => $serviceId
            ]);
        }
        unset($service);


        return [
            'errorMessage' => $errorMessage,
            'form'         => $this->form,
            'services'     => $services
        ];
    }

    public function logoutAction()
    {
        Zend_Auth::getInstance()->clearIdentity();
        $this->user()->clearRememberCookie();
        return $this->redirect()->toUrl(
            $this->url()->fromRoute('login')
        );
    }

    /**
     * @param string $serviceId
     * @return Autowp_ExternalLoginService_Abstract
     */
    private function getExternalLoginService($serviceId)
    {
        $serviceOptionsKey = $serviceId;

        $service = $this->externalLoginFactory->getService($serviceId, $serviceOptionsKey, [
            'redirect_uri' => 'http://en.wheelsage.org/login/callback'
        ]);

        if (!$service) {
            throw new Exception("Service `$serviceId` not found");
        }
        return $service;
    }

    public function startAction()
    {
        if ($this->user()->logedIn()) {
            return $this->redirect()->toUrl($this->url()->fromRoute('login'));
        }

        $serviceId = trim($this->params('type'));

        $service = $this->getExternalLoginService($serviceId);

        $loginUrl = $service->getLoginUrl();

        $table = new LoginState();
        $row = $table->createRow([
            'state'    => $service->getState(),
            'time'     => new Zend_Db_Expr('now()'),
            'user_id'  => null,
            'language' => $this->language(),
            'service'  => $serviceId,
            'url'      => $this->url()->fromRoute('login')
        ]);

        $row->save();

        return $this->redirect()->toUrl($loginUrl);
    }

    public function callbackAction()
    {
        $table = new LoginState();

        $state = (string)$this->params()->fromQuery('state');
        if (!$state) { // twitter workaround
            $state = (string)$this->params()->fromQuery('oauth_token');
        }

        $stateRow = $table->fetchRow([
            'state = ?' => $state
        ]);

        if (!$stateRow) {
            return $this->notFoundAction();
        }

        $params = $this->params()->fromQuery();

        if ($stateRow->language != $this->language()) {

            if (!isset($this->hosts[$stateRow->language])) {
                throw new Exception("Host {$stateRow->language} not found");
            }

            $url = $this->url()->fromRoute('login/callback', [], [
                'force_canonical' => true,
                'query'           => $params,
                'uri'             => new HttpUri('http://' . $this->hosts[$stateRow->language]['hostname'])
            ]);
            return $this->redirect()->toUrl($url);
        }

        $service = $this->getExternalLoginService($stateRow->service);
        $success = $service->callback($params);
        if (!$success) {
            throw new Exception("Error processing callback");
        }

        $data = $service->getData([
            'language' => $stateRow->language
        ]);

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

        $uaRow = $uaTable->fetchRow([
            'service_id = ?'  => $stateRow->service,
            'external_id = ?' => $data->getExternalId(),
        ]);

        if (!$uaRow) {

            $uTable = new Users();

            if ($stateRow->user_id) {
                $uRow = $uTable->find($stateRow->user_id)->current();
                if (!$uRow) {
                    throw new Exception("Account `{$stateRow->user_id}` not found");
                }
            } else {
                $uRow = $this->service->addUser([
                    'email'    => null,
                    'password' => uniqid(),
                    'name'     => $data->getName(),
                    'ip'       => $this->getRequest()->getServer('REMOTE_ADDR')
                ], $this->language());
            }

            if (!$uRow) {
                return $this->notFoundAction();
            }

            $uaRow = $uaTable->fetchNew();
            $uaRow->setFromArray([
                'service_id'   => $stateRow->service,
                'external_id'  => $data->getExternalId(),
                'user_id'      => $uRow->id,
                'used_for_reg' => $stateRow->user_id ? 0 : 1
            ]);

            if (!$stateRow->user_id) { // first login
                if ($photoUrl = $data->getPhotoUrl()) {
                    $photo = file_get_contents($photoUrl);

                    if ($photo) {
                        $imageSampler = $this->imageStorage()->getImageSampler();

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

        $uaRow->setFromArray([
            'name' => $data->getName(),
            'link' => $data->getProfileUrl(),
        ]);
        $uaRow->save();

        $url = $stateRow->url;

        $stateRow->delete();

        $adapter = new IdAuthAdapter();
        $adapter->setIdentity($uRow->id);
        $authResult = Zend_Auth::getInstance()->authenticate($adapter);
        if ($authResult->isValid()) {
            return $this->redirect()->toUrl($url);
        } else {
            // Invalid credentials
            throw new Exception('Error during login');
        }

    }
}