<?php

namespace Application\Controller;

use Exception;
use Imagick;

use Zend\Authentication\AuthenticationService;
use Zend\Db\Sql;
use Zend\Db\TableGateway\TableGateway;
use Zend\Mvc\Controller\AbstractActionController;
use Zend\Uri\Http as HttpUri;

use Autowp\ExternalLoginService\PluginManager as ExternalLoginServices;
use Autowp\User\Auth\Adapter\Id as IdAuthAdapter;
use Autowp\User\Model\User;
use Autowp\User\Model\UserRemember;

use Application\Model\UserAccount;
use Application\Service\UsersService;

class LoginController extends AbstractActionController
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
        array $hosts,
        UserRemember $userRemember,
        UserAccount $userAccount,
        TableGateway $loginStateTable,
        User $userModel
    ) {

        $this->service = $service;
        $this->externalLoginServices = $externalLoginServices;
        $this->hosts = $hosts;
        $this->userRemember = $userRemember;
        $this->userAccount = $userAccount;
        $this->loginStateTable = $loginStateTable;
        $this->userModel = $userModel;
    }

    public function logoutAction()
    {
        $auth = new AuthenticationService();
        $auth->clearIdentity();
        $this->service->clearRememberCookie($this->language());
        return $this->redirect()->toRoute('ng', [
            'path' => 'login'
        ]);
    }

    /**
     * @param string $serviceId
     * @return \Autowp\ExternalLoginService\AbstractService
     */
    private function getExternalLoginService($serviceId)
    {
        $service = $this->externalLoginServices->get($serviceId);

        if (! $service) {
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

        $this->loginStateTable->insert([
            'state'    => $service->getState(),
            'time'     => new Sql\Expression('now()'),
            'user_id'  => null,
            'language' => $this->language(),
            'service'  => $serviceId,
            'url'      => '/ng/login'
        ]);

        return $this->redirect()->toUrl($loginUrl);
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
                'uri'             => new HttpUri('http://' . $this->hosts[$stateRow['language']]['hostname'])
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
                if ($photoUrl = $data->getPhotoUrl()) {
                    $photo = file_get_contents($photoUrl);

                    if ($photo) {
                        $imageSampler = $this->imageStorage()->getImageSampler();

                        $imagick = new Imagick();
                        if (! $imagick->readImageBlob($photo)) {
                            throw new Exception("Error loading image");
                        }
                        $format = $this->imageStorage()->getFormat('photo');
                        $imageSampler->convertImagick($imagick, $format);

                        $newImageId = $this->imageStorage()->addImageFromImagick($imagick, 'user');

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
