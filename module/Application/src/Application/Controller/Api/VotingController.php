<?php

namespace Application\Controller\Api;

use Application\Hydrator\Api\AbstractRestHydrator;
use Autowp\User\Controller\Plugin\User;
use Autowp\Votings;
use Exception;
use Laminas\ApiTools\ApiProblem\ApiProblemResponse;
use Laminas\Hydrator\Strategy\DateTimeFormatterStrategy;
use Laminas\InputFilter\InputFilter;
use Laminas\Mvc\Controller\AbstractRestfulController;
use Laminas\Stdlib\ResponseInterface;
use Laminas\View\Model\JsonModel;
use Laminas\View\Model\ViewModel;

/**
 * @method User user($user = null)
 * @method ViewModel forbiddenAction()
 * @method ApiProblemResponse inputFilterResponse(InputFilter $inputFilter)
 * @method string language()
 */
class VotingController extends AbstractRestfulController
{
    private Votings\Votings $service;

    private InputFilter $variantVoteInputFilter;

    private AbstractRestHydrator $variantVoteHydrator;

    public function __construct(
        Votings\Votings $service,
        InputFilter $variantVoteInputFilter,
        AbstractRestHydrator $variantVoteHydrator
    ) {
        $this->service                = $service;
        $this->variantVoteInputFilter = $variantVoteInputFilter;
        $this->variantVoteHydrator    = $variantVoteHydrator;
    }

    /**
     * @return array|JsonModel
     * @throws Exception
     */
    public function getItemAction()
    {
        $id     = (int) $this->params('id');
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
     */
    public function getVoteListAction()
    {
        $this->variantVoteInputFilter->setData($this->params()->fromQuery());

        if (! $this->variantVoteInputFilter->isValid()) {
            return $this->inputFilterResponse($this->variantVoteInputFilter);
        }

        $values = $this->variantVoteInputFilter->getValues();

        $rows = $this->service->getVotes($this->params('id'));

        $this->variantVoteHydrator->setOptions([
            'language' => $this->language(),
            'fields'   => $values['fields'] ?? [],
        ]);

        $result = [];
        foreach ($rows as $row) {
            $result[] = $this->variantVoteHydrator->extract($row);
        }

        return new JsonModel([
            'items' => $result,
        ]);
    }

    /**
     * @return ViewModel|ResponseInterface|array
     */
    public function patchItemAction()
    {
        $user = $this->user()->get();
        if (! $user) {
            return $this->forbiddenAction();
        }

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

        /* @phan-suppress-next-line PhanUndeclaredMethod */
        return $this->getResponse()->setStatusCode(200);
    }
}
