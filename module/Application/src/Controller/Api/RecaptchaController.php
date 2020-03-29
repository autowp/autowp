<?php

namespace Application\Controller\Api;

use Laminas\Mvc\Controller\AbstractRestfulController;
use Laminas\Session\Container;
use Laminas\View\Model\JsonModel;

class RecaptchaController extends AbstractRestfulController
{
    private string $publicKey;

    public function __construct(string $publicKey)
    {
        $this->publicKey = $publicKey;
    }

    /**
     * Update an existing resource
     */
    public function getAction(): JsonModel
    {
        $namespace = new Container('Captcha');

        return new JsonModel([
            'publicKey' => $this->publicKey,
            'success'   => isset($namespace->success) && $namespace->success,
        ]);
    }
}
