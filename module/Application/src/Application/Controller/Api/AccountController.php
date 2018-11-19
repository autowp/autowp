<?php

namespace Application\Controller\Api;

use Exception;

use Zend\Db\Sql;
use Zend\Db\TableGateway\TableGateway;
use Zend\Mvc\Controller\AbstractRestfulController;
use Zend\View\Model\JsonModel;

use Autowp\ExternalLoginService\PluginManager as ExternalLoginServices;

use Application\Model\UserAccount;

class AccountController extends AbstractRestfulController
{
    /**
     * @var UserAccount
     */
    private $userAccount;

    /**
     * @var ExternalLoginServices
     */
    private $externalLoginServices;

    /**
     * @var TableGateway
     */
    private $loginStateTable;

    public function __construct(
        UserAccount $userAccount,
        ExternalLoginServices $externalLoginServices,
        TableGateway $loginStateTable
    ) {
        $this->userAccount = $userAccount;
        $this->externalLoginServices = $externalLoginServices;
        $this->loginStateTable = $loginStateTable;
    }

    private function canRemoveAccount(int $id): bool
    {
        $user = $this->user()->get();
        if (! $user) {
            return false;
        }

        if ($user['e_mail']) {
            return true;
        }

        $haveAccounts = $this->userAccount->haveAccountsForOtherServices($user['id'], $id);
        if ($haveAccounts) {
            return true;
        }

        return false;
    }

    public function indexAction()
    {
        $user = $this->user()->get();

        if (! $user) {
            return $this->forbiddenAction();
        }

        $accounts = [];
        foreach ($this->userAccount->getAccounts($user['id']) as $row) {
            $accounts[] = array_replace($row, [
                'can_remove' => $this->canRemoveAccount($row['id'])
            ]);
        }

        return new JsonModel([
            'items' => $accounts
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

    /**
     * @suppress PhanDeprecatedFunction
     */
    public function startAction()
    {
        $user = $this->user()->get();

        if (! $user) {
            return $this->forbiddenAction();
        }

        $request = $this->getRequest();

        $params = $this->processBodyContent($request);

        $serviceId = isset($params['service']) ? $params['service'] : null;

        if (! $serviceId) {
            return $this->notFoundAction();
        }

        $service = $this->getExternalLoginService($serviceId);

        $loginUrl = $service->getLoginUrl();

        $this->loginStateTable->insert([
            'state'    => $service->getState(),
            'time'     => new Sql\Expression('now()'),
            'user_id'  => $user['id'],
            'language' => $this->language(),
            'service'  => $serviceId,
            'url'      => '/ng/account/accounts'
        ]);

        return new JsonModel([
            'url' => $loginUrl
        ]);
    }

    public function deleteAction()
    {
        $user = $this->user()->get();
        if (! $user) {
            return $this->forbiddenAction();
        }

        $id = (int)$this->params('id');

        if (! $this->canRemoveAccount($id)) {
            return $this->forward()->dispatch(self::class, [
                'action' => 'remove-account-failed'
            ]);
        }

        $this->userAccount->removeAccount($id);

        return $this->getResponse()->setStatusCode(204);
    }
}
