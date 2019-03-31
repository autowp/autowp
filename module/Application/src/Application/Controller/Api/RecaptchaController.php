<?php

namespace Application\Controller\Api;

use Zend\Mvc\Controller\AbstractRestfulController;
use Zend\Session\Container;
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
     * @return mixed
     */
    public function getAction()
    {
        $namespace = new Container('Captcha');

        return new JsonModel([
            'publicKey' => $this->publicKey,
            'success'   => isset($namespace->success) && $namespace->success
        ]);
    }
}
