<?php

namespace Application\Controller\Api;

use Application\Model\UserAccount;
use Autowp\ExternalLoginService\AbstractService;
use Autowp\ExternalLoginService\PluginManager as ExternalLoginServices;
use Autowp\User\Controller\Plugin\User;
use Exception;
use Laminas\Db\Sql;
use Laminas\Db\TableGateway\TableGateway;
use Laminas\Mvc\Controller\AbstractRestfulController;
use Laminas\View\Model\JsonModel;
use Laminas\View\Model\ViewModel;

use function array_replace;

/**
 * @method User user($user = null)
 * @method ViewModel forbiddenAction()
 * @method string language()
 */
class AccountController extends AbstractRestfulController
{
    /** @var UserAccount */
    private UserAccount $userAccount;

    /** @var ExternalLoginServices */
    private ExternalLoginServices $externalLoginServices;

    /** @var TableGateway */
    private TableGateway $loginStateTable;

    public function __construct(
        UserAccount $userAccount,
        ExternalLoginServices $externalLoginServices,
        TableGateway $loginStateTable
    ) {
        $this->userAccount           = $userAccount;
        $this->externalLoginServices = $externalLoginServices;
        $this->loginStateTable       = $loginStateTable;
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
                'can_remove' => $this->canRemoveAccount($row['id']),
            ]);
        }

        return new JsonModel([
            'items' => $accounts,
        ]);
    }

    /**
     * @throws Exception
     */
    private function getExternalLoginService(string $serviceId): AbstractService
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

        $serviceId = $params['service'] ?? null;

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
            'url'      => '/account/accounts',
        ]);

        return new JsonModel([
            'url' => $loginUrl,
        ]);
    }

    public function deleteAction()
    {
        $user = $this->user()->get();
        if (! $user) {
            return $this->forbiddenAction();
        }

        $id = (int) $this->params('id');

        if (! $this->canRemoveAccount($id)) {
            return $this->forward()->dispatch(self::class, [
                'action' => 'remove-account-failed',
            ]);
        }

        $this->userAccount->removeAccount($id);

        /* @phan-suppress-next-line PhanUndeclaredMethod */
        return $this->getResponse()->setStatusCode(204);
    }
}
