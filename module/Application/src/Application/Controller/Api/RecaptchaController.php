<?php

namespace Application\Controller\Api;

use Zend\Mvc\Controller\AbstractRestfulController;
use Zend\View\Model\JsonModel;

class RecaptchaController extends AbstractRestfulController
{
    /**
     * @var string
     */
    private $publicKey;

    public function __construct(string $publicKey)
    {
        $this->publicKey = $publicKey;
    }

    /**
     * Update an existing resource
     *
     * @param  mixed $id
     * @param  mixed $data
     * @return mixed
     */
    public function getAction()
    {
        return new JsonModel([
            'publicKey' => $this->publicKey
        ]);
    }
}
