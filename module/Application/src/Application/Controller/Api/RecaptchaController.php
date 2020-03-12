<?php

namespace Application\Controller\Api;

use Laminas\Mvc\Controller\AbstractRestfulController;
use Laminas\Session\Container;
use Laminas\Stdlib\ResponseInterface;
use Laminas\View\Model\JsonModel;
use Laminas\View\Model\ViewModel;

class RecaptchaController extends AbstractRestfulController
{
    /** @var string */
    private string $publicKey;

    public function __construct(string $publicKey)
    {
        $this->publicKey = $publicKey;
    }

    /**
     * Update an existing resource
     * @return ViewModel|ResponseInterface|array
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
