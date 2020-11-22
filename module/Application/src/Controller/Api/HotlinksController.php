<?php

namespace Application\Controller\Api;

use Application\Model\Referer;
use Autowp\User\Controller\Plugin\User;
use Laminas\ApiTools\ApiProblem\ApiProblem;
use Laminas\ApiTools\ApiProblem\ApiProblemResponse;
use Laminas\Http\PhpEnvironment\Response;
use Laminas\Mvc\Controller\AbstractRestfulController;
use Laminas\Stdlib\ResponseInterface;
use Laminas\View\Model\JsonModel;
use Laminas\View\Model\ViewModel;

/**
 * @method User user($user = null)
 * @method ViewModel forbiddenAction()
 */
class HotlinksController extends AbstractRestfulController
{
    private Referer $referer;

    public function __construct(Referer $referer)
    {
        $this->referer = $referer;
    }

    /**
     * @return ViewModel|ResponseInterface|array
     */
    public function hostsAction()
    {
        if (! $this->user()->enforce('hotlinks', 'view')) {
            return new ApiProblemResponse(new ApiProblem(403, 'Forbidden'));
        }

        return new JsonModel([
            'items' => $this->referer->getData(),
        ]);
    }

    /**
     * @return ViewModel|ResponseInterface|array
     */
    public function hostsDeleteAction()
    {
        if (! $this->user()->enforce('hotlinks', 'manage')) {
            return new ApiProblemResponse(new ApiProblem(403, 'Forbidden'));
        }

        $this->referer->flush();

        /** @var Response $response */
        $response = $this->getResponse();
        return $response->setStatusCode(Response::STATUS_CODE_204);
    }

    /**
     * @return ViewModel|ResponseInterface|array
     */
    public function hostDeleteAction()
    {
        if (! $this->user()->enforce('hotlinks', 'manage')) {
            return new ApiProblemResponse(new ApiProblem(403, 'Forbidden'));
        }

        $this->referer->flushHost((string) $this->params('host'));

        /** @var Response $response */
        $response = $this->getResponse();
        return $response->setStatusCode(Response::STATUS_CODE_204);
    }

    /**
     * @return ViewModel|ResponseInterface|array
     */
    public function whitelistPostAction()
    {
        if (! $this->user()->enforce('hotlinks', 'manage')) {
            return $this->forbiddenAction();
        }

        $data = $this->processBodyContent($this->getRequest());

        $host = isset($data['host']) ? (string) $data['host'] : null;

        if (! $host) {
            return new ApiProblemResponse(new ApiProblem(400, 'Validation error'));
        }

        $this->referer->addToWhitelist($host);

        /** @var Response $response */
        $response = $this->getResponse();
        return $response->setStatusCode(Response::STATUS_CODE_201);
    }

    /**
     * @return ViewModel|ResponseInterface|array
     */
    public function blacklistPostAction()
    {
        if (! $this->user()->enforce('hotlinks', 'manage')) {
            return $this->forbiddenAction();
        }

        $data = $this->processBodyContent($this->getRequest());

        $host = isset($data['host']) ? (string) $data['host'] : null;

        if (! $host) {
            return new ApiProblemResponse(new ApiProblem(400, 'Validation error'));
        }

        $this->referer->addToBlacklist($host);

        /** @var Response $response */
        $response = $this->getResponse();
        return $response->setStatusCode(Response::STATUS_CODE_201);
    }
}
