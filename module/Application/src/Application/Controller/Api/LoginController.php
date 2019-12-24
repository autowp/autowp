<?php

namespace Application\Controller\Api;

use Autowp\ExternalLoginService\AbstractService;
use Exception;
use Imagick;
use Zend\Authentication\AuthenticationService;
use Zend\Db\Sql;
use Zend\Db\TableGateway\TableGateway;
use Zend\InputFilter\InputFilter;
use Zend\Mvc\Controller\AbstractRestfulController;
use Zend\Uri\Http as HttpUri;
use Zend\View\Model\JsonModel;
use ZF\ApiProblem\ApiProblemResponse;
use ZF\ApiProblem\ApiProblem;
use Autowp\ExternalLoginService\PluginManager as ExternalLoginServices;
use Autowp\Image\Storage;
use Autowp\User\Auth\Adapter\Id as IdAuthAdapter;
use Autowp\User\Model\User;
use Autowp\User\Model\UserRemember;
use Application\Model\UserAccount;
use Application\Service\UsersService;

/**
 * Class LoginController
 * @package Application\Controller\Api
 *
 * @method \Autowp\User\Controller\Plugin\User user($user = null)
 * @method Storage imageStorage()
 * @method ApiProblemResponse inputFilterResponse(InputFilter $inputFilter)
 * @method string language()
 * @method string translate(string $message, string $textDomain = 'default', $locale = null)
 */
class LoginController extends AbstractRestfulController
{
    /**
     * @var UsersService
     */
    private $service;

    /**
     * @var ExternalLoginServices
     */
    private $externalLoginServices;

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
        ExternalLoginServices $externalLoginServices,
        InputFilter $loginInputFilter,
        array $hosts,
        UserRemember $userRemember,
        UserAccount $userAccount,
        TableGateway $loginStateTable,
        User $userModel
    ) {

        $this->service = $service;
        $this->externalLoginServices = $externalLoginServices;
        $this->loginInputFilter = $loginInputFilter;
        $this->hosts = $hosts;
        $this->userRemember = $userRemember;
        $this->userAccount = $userAccount;
        $this->loginStateTable = $loginStateTable;
        $this->userModel = $userModel;
    }

    /**
     * @param string $serviceId
     * @return AbstractService
     * @throws Exception
     */
    private function getExternalLoginService($serviceId)
    {
        $service = $this->externalLoginServices->get($serviceId);

        if (! $service) {
            throw new Exception("Service `$serviceId` not found");
        }
        return $service;
    }

    public function loginAction()
    {
        $request = $this->getRequest();
        if ($this->requestHasContentType($request, self::CONTENT_TYPE_JSON)) {
            $data = $this->jsonDecode($request->getContent());
        } else {
            /* @phan-suppress-next-line PhanUndeclaredMethod */
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

        /* @phan-suppress-next-line PhanUndeclaredMethod */
        return $this->getResponse()->setStatusCode(201);
    }

    public function deleteAction()
    {
        $auth = new AuthenticationService();
        $auth->clearIdentity();
        $this->service->clearRememberCookie($this->language());

        /* @phan-suppress-next-line PhanUndeclaredMethod */
        return $this->getResponse()->setStatusCode(204);
    }

    public function servicesAction()
    {
        $services = [
            'facebook'    => [
                'name'  => 'Facebook',
                'icon'  => 'fa-facebook',
                'color' => '#3b5998'
            ],
            'vk'          => [
                'name' => 'VK',
                'icon' => 'fa-vk',
                'color' => '#43648c'
            ],
            'google-plus' => [
                'name' => 'Google+',
                'icon' => 'fa-google',
                'color' => '#dd4b39'
            ],
            'twitter'     => [
                'name' => 'Twitter',
                'icon' => 'fa-twitter',
                'color' => '#55acee'
            ],
            'github'     => [
                'name' => 'Github',
                'icon' => 'fa-github',
                'color' => '#000000'
            ],
            'linkedin'     => [
                'name' => 'LinkedIn',
                'icon' => 'fa-linkedin',
                'color' => '#046293'
            ],
        ];

        return new JsonModel([
            'items' => $services
        ]);
    }

    /**
     * @suppress PhanDeprecatedFunction
     */
    public function startAction()
    {
        if ($this->user()->logedIn()) {
            return $this->redirect()->toUrl($this->url()->fromRoute('login'));
        }

        $serviceId = trim($this->params()->fromQuery('type'));

        $service = $this->getExternalLoginService($serviceId);

        $url = $service->getLoginUrl(); // must be called before getState

        $this->loginStateTable->insert([
            'state'    => $service->getState(),
            'time'     => new Sql\Expression('now()'),
            'user_id'  => null,
            'language' => $this->language(),
            'service'  => $serviceId,
            'url'      => '/ng/login'
        ]);

        return new JsonModel([
            'url' => $url
        ]);
    }

    public function callbackAction()
    {
        $state = (string)$this->params()->fromQuery('state');
        if (! $state) { // twitter workaround
            $state = (string)$this->params()->fromQuery('oauth_token');
        }

        $stateRow = $this->loginStateTable->select([
            'state' => $state
        ])->current();

        if (! $stateRow) {
            return $this->notFoundAction();
        }

        $params = $this->params()->fromQuery();

        if ($stateRow['language'] != $this->language()) {
            if (! isset($this->hosts[$stateRow['language']])) {
                throw new Exception("Host {$stateRow['language']} not found");
            }

            $url = $this->url()->fromRoute('login/callback', [], [
                'force_canonical' => true,
                'query'           => $params,
                'uri'             => new HttpUri('https://' . $this->hosts[$stateRow['language']]['hostname'])
            ]);
            return $this->redirect()->toUrl($url);
        }

        $service = $this->getExternalLoginService($stateRow['service']);
        $success = $service->callback($params);
        if (! $success) {
            throw new Exception("Error processing callback");
        }

        $data = $service->getData([
            'language' => $stateRow['language']
        ]);

        if (! $data) {
            throw new Exception("Error requesting data");
        }

        if (! $data->getExternalId()) {
            throw new Exception('external_id not found');
        }
        if (! $data->getName()) {
            throw new Exception('name not found');
        }

        $userId = $this->userAccount->getUserId($stateRow['service'], $data->getExternalId());

        if (! $userId) {
            if ($stateRow['user_id']) {
                $uRow = $this->userModel->getRow((int)$stateRow['user_id']);
                if (! $uRow) {
                    throw new Exception("Account `{$stateRow['user_id']}` not found");
                }
            } else {
                /* @phan-suppress-next-line PhanUndeclaredMethod */
                $ip = $this->getRequest()->getServer('REMOTE_ADDR');
                if (! $ip) {
                    $ip = '127.0.0.1';
                }

                $uRow = $this->service->addUser([
                    'email'    => null,
                    'password' => uniqid(),
                    'name'     => $data->getName(),
                    'ip'       => $ip
                ], $this->language());
            }

            if (! $uRow) {
                return $this->notFoundAction();
            }

            $this->userAccount->create($stateRow['service'], $data->getExternalId(), [
                'user_id'      => $uRow['id'],
                'used_for_reg' => $stateRow['user_id'] ? 0 : 1,
                'name'         => $data->getName(),
                'link'         => $data->getProfileUrl(),
            ]);

            if (! $stateRow['user_id']) { // first login
                $photoUrl = $data->getPhotoUrl();
                if ($photoUrl) {
                    $photo = file_get_contents($photoUrl);

                    if ($photo) {
                        $imageSampler = $this->imageStorage()->getImageSampler();

                        $imagick = new Imagick();
                        if (! $imagick->readImageBlob($photo)) {
                            throw new Exception("Error loading image");
                        }
                        $format = $this->imageStorage()->getFormat('photo');
                        $imageSampler->convertImagick($imagick, null, $format);

                        $newImageId = $this->imageStorage()->addImageFromImagick($imagick, 'user', [
                            's3' => true
                        ]);

                        $imagick->clear();

                        $oldImageId = $uRow['img'];

                        $this->userModel->getTable()->update([
                            'img' => $newImageId
                        ], [
                            'id' => $uRow['id']
                        ]);

                        if ($oldImageId) {
                            $this->imageStorage()->removeImage($oldImageId);
                        }
                    }
                }
            }
        } else {
            $uRow = $this->userModel->getRow((int)$userId);
            if (! $uRow) {
                throw new Exception('Not linked account row');
            }

            $this->userAccount->setAccountData(
                $stateRow['service'],
                $data->getExternalId(),
                [
                    'name' => $data->getName(),
                    'link' => $data->getProfileUrl(),
                ]
            );
        }

        $url = $stateRow['url'];

        $this->loginStateTable->delete([
            'state' => $stateRow['state']
        ]);

        $adapter = new IdAuthAdapter($this->userModel);
        $adapter->setIdentity($uRow['id']);
        $auth = new AuthenticationService();
        $authResult = $auth->authenticate($adapter);
        if ($authResult->isValid()) {
            return $this->redirect()->toUrl($url);
        } else {
            // Invalid credentials
            throw new Exception('Error during login');
        }
    }
}
