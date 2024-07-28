<?php

namespace Application\Controller\Api;

use Autowp\User\Controller\Plugin\User;
use Autowp\Votings;
use Exception;
use Laminas\ApiTools\ApiProblem\ApiProblemResponse;
use Laminas\Http\PhpEnvironment\Response;
use Laminas\Hydrator\Strategy\DateTimeFormatterStrategy;
use Laminas\InputFilter\InputFilterInterface;
use Laminas\Mvc\Controller\AbstractRestfulController;
use Laminas\Stdlib\ResponseInterface;
use Laminas\View\Model\JsonModel;
use Laminas\View\Model\ViewModel;

/**
 * @method User user($user = null)
 * @method ViewModel forbiddenAction()
 * @method ApiProblemResponse inputFilterResponse(InputFilterInterface $inputFilter)
 * @method string language()
 */
class VotingController extends AbstractRestfulController
{
    private Votings\Votings $service;

    public function __construct(Votings\Votings $service)
    {
        $this->service = $service;
    }

    /**
     * @return array|JsonModel
     * @throws Exception
     */
    public function getItemAction()
    {
        /** @psalm-suppress InvalidCast */
        $id = (int) $this->params('id');
        /** @psalm-suppress InvalidCast */
        $filter = (int) $this->params()->fromQuery('filter');

        $user = $this->user()->get();

        $data = $this->service->getVoting($id, $filter, $user ? (int) $user['id'] : 0);

        if (! $data) {
            return $this->notFoundAction();
        }

        $strategy = new DateTimeFormatterStrategy();

        $data['begin_date'] = $strategy->extract($data['begin_date']);
        $data['end_date']   = $strategy->extract($data['end_date']);

        return new JsonModel($data);
    }

    /**
     * @return ViewModel|ResponseInterface|array
     * @throws Exception
     */
    public function getVoteListAction()
    {
        $result = [];
        foreach ($this->service->getVotes($this->params('id')) as $row) {
            $result[] = [
                'user_id' => $row['user_id'],
            ];
        }

        return new JsonModel([
            'items' => $result,
        ]);
    }

    /**
     * @return ViewModel|ResponseInterface|array
     * @throws Exception
     */
    public function patchItemAction()
    {
        $user = $this->user()->get();
        if (! $user) {
            return $this->forbiddenAction();
        }

        /** @psalm-suppress InvalidCast */
        $id = (int) $this->params('id');

        $data    = $this->processBodyContent($this->getRequest());
        $variant = isset($data['vote']) ? (array) $data['vote'] : [];

        $success = $this->service->vote(
            $id,
            $variant,
            $user['id']
        );
        if (! $success) {
            return $this->notFoundAction();
        }

        /** @var Response $response */
        $response = $this->getResponse();
        return $response->setStatusCode(Response::STATUS_CODE_200);
    }
}
