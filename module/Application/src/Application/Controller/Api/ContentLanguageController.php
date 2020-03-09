<?php

namespace Application\Controller\Api;

use Autowp\User\Controller\Plugin\User;
use Laminas\Mvc\Controller\AbstractRestfulController;
use Laminas\View\Model\JsonModel;

/**
 * @method User user($user = null)
 */
class ContentLanguageController extends AbstractRestfulController
{
    private array $languages;

    public function __construct(array $languages)
    {
        $this->languages = $languages;
    }

    public function indexAction()
    {
        if (! $this->user()->inheritsRole('moder')) {
            return $this->forbiddenAction();
        }

        return new JsonModel([
            'items' => $this->languages,
        ]);
    }
}
