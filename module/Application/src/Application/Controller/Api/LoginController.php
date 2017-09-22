<?php

namespace Application\Controller\Api;

use Zend\Authentication\AuthenticationService;
use Zend\Db\TableGateway\TableGateway;
use Zend\InputFilter\InputFilter;
use Zend\Mvc\Controller\AbstractRestfulController;
use Zend\View\Model\JsonModel;

use ZF\ApiProblem\ApiProblemResponse;
use ZF\ApiProblem\ApiProblem;

use Autowp\User\Model\User;
use Autowp\User\Model\UserRemember;

use Application\Model\UserAccount;
use Application\Service\UsersService;

class LoginController extends AbstractRestfulController
{
    /**
     * @var UsersService
     */
    private $service;

    /**
     * @var InputFilter
     */
    private $loginInputFilter;

    /**
     * @var array
     */
    private $hosts = [];

    /**
     * @var UserRemember
     */
    private $userRemember;

    /**
     * @var UserAccount
     */
    private $userAccount;

    /**
     * @var TableGateway
     */
    private $loginStateTable;

    /**
     * @var User
     */
    private $userModel;

    public function __construct(
        UsersService $service,
        InputFilter $loginInputFilter,
        array $hosts,
        UserRemember $userRemember,
        UserAccount $userAccount,
        TableGateway $loginStateTable,
        User $userModel
    ) {

        $this->service = $service;
        $this->loginInputFilter = $loginInputFilter;
        $this->hosts = $hosts;
        $this->userRemember = $userRemember;
        $this->userAccount = $userAccount;
        $this->loginStateTable = $loginStateTable;
        $this->userModel = $userModel;
    }

    public function loginAction()
    {
        $request = $this->getRequest();
        if ($this->requestHasContentType($request, self::CONTENT_TYPE_JSON)) {
            $data = $this->jsonDecode($request->getContent());
        } else {
            $data = $request->getPost()->toArray();
        }

        $this->loginInputFilter->setData($data);

        if (! $this->loginInputFilter->isValid()) {
            return $this->inputFilterResponse($this->loginInputFilter);
        }

        $values = $this->loginInputFilter->getValues();

        $adapter = $this->service->getAuthAdapterLogin($values['login'], $values['password']);

        $auth = new AuthenticationService();
        $result = $auth->authenticate($adapter);

        if (! $result->isValid()) {
            return new ApiProblemResponse(
                new ApiProblem(400, 'Data is invalid. Check `detail`.', null, 'Validation error', [
                    'invalid_params' => [
                        'login' => [
                            'invalid' => $this->translate('login/login-or-password-is-incorrect')
                        ]
                    ]
                ])
            );
        }


        if ($values['remember']) {
            $token = $this->userRemember->createToken($this->user()->get()['id']);

            $this->service->setRememberCookie($token, $this->language());
        } else {
            $this->service->clearRememberCookie($this->language());
        }

        return $this->getResponse()->setStatusCode(201);
    }

    public function deleteAction()
    {
        $auth = new AuthenticationService();
        $auth->clearIdentity();
        $this->service->clearRememberCookie($this->language());

        return $this->getResponse()->setStatusCode(204);
    }

    public function servicesAction()
    {
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

        return new JsonModel([
            'items' => $services
        ]);
    }
}
