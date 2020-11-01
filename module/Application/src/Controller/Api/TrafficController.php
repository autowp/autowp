<?php

namespace Application\Controller\Api;

use Application\Hydrator\Api\AbstractRestHydrator;
use Autowp\Traffic\TrafficControl;
use Autowp\User\Controller\Plugin\User;
use Exception;
use Laminas\Http\PhpEnvironment\Response;
use Laminas\Mvc\Controller\AbstractRestfulController;
use Laminas\Stdlib\ResponseInterface;
use Laminas\View\Model\JsonModel;
use Laminas\View\Model\ViewModel;

use function trim;

/**
 * @method User user($user = null)
 * @method ViewModel forbiddenAction()
 * @method string language()
 */
class TrafficController extends AbstractRestfulController
{
    private TrafficControl $service;

    private AbstractRestHydrator $hydrator;

    public function __construct(TrafficControl $service, AbstractRestHydrator $hydrator)
    {
        $this->service  = $service;
        $this->hydrator = $hydrator;
    }

    /**
     * @return ViewModel|ResponseInterface|array
     */
    public function listAction()
    {
        if (! $this->user()->inheritsRole('moder')) {
            return $this->forbiddenAction();
        }

        $data = $this->service->getTopData();

        $this->hydrator->setOptions([
            'language' => $this->language(),
            'fields'   => [],
            //'user_id'  => $user ? $user['id'] : null
        ]);

        $result = [];
        foreach ($data as $row) {
            $result[] = $this->hydrator->extract($row);
        }

        return new JsonModel([
            'items' => $result,
        ]);
    }

    /**
     * @return ViewModel|ResponseInterface|array
     * @throws Exception
     */
    public function whitelistListAction()
    {
        if (! $this->user()->inheritsRole('moder')) {
            return $this->forbiddenAction();
        }

        $data = $this->service->getWhitelistData();

        /*foreach ($data as &$row) {
            $row['users'] = [];
            $users->fetchAll([
            'last_ip = INET_ATON(?)' => $row['ip']
            ]);
        }
        unset($row);*/

        return new JsonModel([
            'items' => $data,
        ]);
    }

    /**
     * @return ViewModel|ResponseInterface|array
     */
    public function whitelistCreateAction()
    {
        if (! $this->user()->inheritsRole('moder')) {
            return $this->forbiddenAction();
        }

        $data = $this->processBodyContent($this->getRequest());

        $ip = trim($data['ip']);

        if (! $ip) {
            /** @var Response $response */
            $response = $this->getResponse();
            return $response->setStatusCode(Response::STATUS_CODE_400);
        }

        $this->service->addToWhitelist($ip, 'manual click');

        /*$this->getResponse()->getHeaders()->addHeaderLine(
            'Location',
            $this->url()->fromRoute('api/traffic/whitelist/item/get', [
                'id' => $ip
            ])
        );*/
        /** @var Response $response */
        $response = $this->getResponse();
        return $response->setStatusCode(Response::STATUS_CODE_201);
    }

    /**
     * @return ViewModel|ResponseInterface|array
     */
    public function whitelistItemDeleteAction()
    {
        if (! $this->user()->inheritsRole('moder')) {
            return $this->forbiddenAction();
        }

        $this->service->deleteFromWhitelist($this->params('ip'));

        /** @var Response $response */
        $response = $this->getResponse();
        return $response->setStatusCode(Response::STATUS_CODE_204);
    }

    /**
     * @return ViewModel|ResponseInterface|array
     */
    public function blacklistCreateAction()
    {
        $canBan = $this->user()->isAllowed('user', 'ban');
        if (! $canBan) {
            return $this->forbiddenAction();
        }

        $data = $this->processBodyContent($this->getRequest());

        $ip = $data['ip'];

        if ($ip === null) {
            return $this->notFoundAction();
        }

        $this->service->ban(
            $ip,
            $data['period'] * 3600,
            $this->user()->get()['id'],
            (string) $data['reason']
        );

        /** @var Response $response */
        $response = $this->getResponse();
        return $response->setStatusCode(Response::STATUS_CODE_201);
    }

    /**
     * @return ViewModel|ResponseInterface|array
     */
    public function blacklistItemDeleteAction()
    {
        $canBan = $this->user()->isAllowed('user', 'ban');
        if (! $canBan) {
            return $this->forbiddenAction();
        }

        $ip = $this->params('ip');

        if ($ip === null) {
            return $this->notFoundAction();
        }

        $this->service->unban($ip);

        /** @var Response $response */
        $response = $this->getResponse();
        return $response->setStatusCode(Response::STATUS_CODE_204);
    }
}
