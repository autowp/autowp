<?php

namespace Application\Controller\Api;

use Zend\Mvc\Controller\AbstractRestfulController;
use Zend\View\Model\JsonModel;
use ZF\ApiProblem\ApiProblem;
use ZF\ApiProblem\ApiProblemResponse;
use Autowp\User\Controller\Plugin\User;
use Application\Controller\Plugin\ForbiddenAction;
use Application\Model\Referer;

/**
 * Class HotlinksController
 * @package Application\Controller\Api
 *
 * @method User user($user = null)
 * @method ForbiddenAction forbiddenAction()
 */
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

        /* @phan-suppress-next-line PhanUndeclaredMethod */
        return $this->getResponse()->setStatusCode(204);
    }

    public function hostDeleteAction()
    {
        if (! $this->user()->isAllowed('hotlinks', 'manage')) {
            return new ApiProblemResponse(new ApiProblem(403, 'Forbidden'));
        }

        $this->referer->flushHost((string)$this->params('host'));

        /* @phan-suppress-next-line PhanUndeclaredMethod */
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

        /* @phan-suppress-next-line PhanUndeclaredMethod */
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

        /* @phan-suppress-next-line PhanUndeclaredMethod */
        return $this->getResponse()->setStatusCode(201);
    }
}
