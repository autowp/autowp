<?php

namespace Application\Controller\Api;

use Application\Model\PictureVote;
use Autowp\User\Controller\Plugin\User;
use Laminas\Http\PhpEnvironment\Response;
use Laminas\Mvc\Controller\AbstractRestfulController;
use Laminas\View\Model\JsonModel;
use Laminas\View\Model\ViewModel;

/**
 * @method User user($user = null)
 * @method ViewModel forbiddenAction()
 */
class PictureVoteController extends AbstractRestfulController
{
    private PictureVote $model;

    public function __construct(PictureVote $model)
    {
        $this->model = $model;
    }

    /**
     * Update an existing resource
     *
     * @param  mixed $id
     * @param  mixed $data
     */
    public function update($id, $data): ViewModel
    {
        $currentUser = $this->user()->get();
        if (! $currentUser) {
            return $this->forbiddenAction();
        }

        $value = $data['value'] ?? null;

        $this->model->vote($id, $currentUser['id'], $value);

        /** @var Response $response */
        $response = $this->getResponse();
        $response->setStatusCode(Response::STATUS_CODE_200);

        return new JsonModel($this->model->getVote($id, $currentUser['id']));
    }
}
