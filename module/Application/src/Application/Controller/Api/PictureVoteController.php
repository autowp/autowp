<?php

namespace Application\Controller\Api;

use Zend\Mvc\Controller\AbstractRestfulController;
use Zend\View\Model\JsonModel;
use Autowp\User\Controller\Plugin\User;
use Application\Controller\Plugin\ForbiddenAction;
use Application\Model\PictureVote;

/**
 * Class PictureVoteController
 * @package Application\Controller\Api
 *
 * @method User user($user = null)
 * @method ForbiddenAction forbiddenAction()
 */
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
