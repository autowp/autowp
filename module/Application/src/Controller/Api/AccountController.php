<?php

namespace Application\Controller\Api;

use Application\Model\UserAccount;
use Autowp\User\Controller\Plugin\User;
use Exception;
use Laminas\Http\PhpEnvironment\Response;
use Laminas\Mvc\Controller\AbstractRestfulController;
use Laminas\Stdlib\ResponseInterface;
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
    private UserAccount $userAccount;

    public function __construct(UserAccount $userAccount)
    {
        $this->userAccount = $userAccount;
    }

    /**
     * @throws Exception
     */
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

    /**
     * @return ViewModel|ResponseInterface|array
     */
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
     * @return ViewModel|ResponseInterface|array
     */
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

        /** @var Response $response */
        $response = $this->getResponse();
        return $response->setStatusCode(Response::STATUS_CODE_204);
    }
}
