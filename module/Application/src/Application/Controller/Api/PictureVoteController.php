<?php

namespace Application\Controller\Api;

use Zend\Mvc\Controller\AbstractRestfulController;
use Zend\View\Model\JsonModel;

use Application\Model\PictureVote;

class PictureVoteController extends AbstractRestfulController
{
    /**
     * @var PictureVote
     */
    private $model;

    public function __construct(PictureVote $model)
    {
        $this->model = $model;
    }

    /**
     * Update an existing resource
     *
     * @param  mixed $id
     * @param  mixed $data
     * @return mixed
     */
    public function update($id, $data)
    {
        /* @phan-suppress-next-line PhanUndeclaredMethod */
        $currentUser = $this->user()->get();
        if (! $currentUser) {
            return $this->forbiddenAction();
        }

        $value = isset($data['value']) ? $data['value'] : null;

        $this->model->vote($id, $currentUser['id'], $value);

        /* @phan-suppress-next-line PhanUndeclaredMethod */
        $this->getResponse()->setStatusCode(200);

        return new JsonModel($this->model->getVote($id, $currentUser['id']));
    }
}
