<?php

namespace Application\Controller\Api;

use Zend\Mvc\Controller\AbstractRestfulController;
use Zend\View\Model\JsonModel;

use ZF\ApiProblem\ApiProblem;
use ZF\ApiProblem\ApiProblemResponse;

use Application\Model\Referer;

class HotlinksController extends AbstractRestfulController
{
    /**
     * @var Referer
     */
    private $referer;

    public function __construct(Referer $referer)
    {
        $this->referer = $referer;
    }

    public function hostsAction()
    {
        if (! $this->user()->isAllowed('hotlinks', 'view')) {
            return new ApiProblemResponse(new ApiProblem(403, 'Forbidden'));
        }

        return new JsonModel([
            'items' => $this->referer->getData()
        ]);
    }

    public function hostsDeleteAction()
    {
        if (! $this->user()->isAllowed('hotlinks', 'manage')) {
            return new ApiProblemResponse(new ApiProblem(403, 'Forbidden'));
        }

        $this->referer->flush();

        return $this->getResponse()->setStatusCode(204);
    }

    public function hostDeleteAction()
    {
        if (! $this->user()->isAllowed('hotlinks', 'manage')) {
            return new ApiProblemResponse(new ApiProblem(403, 'Forbidden'));
        }

        $this->referer->flushHost((string)$this->params('host'));

        return $this->getResponse()->setStatusCode(204);
    }

    public function whitelistPostAction()
    {
        if (! $this->user()->isAllowed('hotlinks', 'manage')) {
            return $this->forbiddenAction();
        }

        $data = $this->processBodyContent($this->getRequest());

        $host = isset($data['host']) ? (string)$data['host'] : null;

        if (! $host) {
            return new ApiProblemResponse(new ApiProblem(400, 'Validation error'));
        }

        $this->referer->addToWhitelist($host);

        return $this->getResponse()->setStatusCode(201);
    }

    public function blacklistPostAction()
    {
        if (! $this->user()->isAllowed('hotlinks', 'manage')) {
            return $this->forbiddenAction();
        }

        $data = $this->processBodyContent($this->getRequest());

        $host = isset($data['host']) ? (string)$data['host'] : null;

        if (! $host) {
            return new ApiProblemResponse(new ApiProblem(400, 'Validation error'));
        }

        $this->referer->addToBlacklist($host);

        return $this->getResponse()->setStatusCode(201);
    }
}
