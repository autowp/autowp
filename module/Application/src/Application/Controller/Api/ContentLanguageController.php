<?php

namespace Application\Controller\Api;

use Zend\Mvc\Controller\AbstractRestfulController;
use Zend\View\Model\JsonModel;

use Autowp\User\Controller\Plugin\User;

/**
 * Class ContentLanguageController
 * @package Application\Controller\Api
 *
 * @method User user($user = null)
 */
class ContentLanguageController extends AbstractRestfulController
{
    /**
     * @var array
     */
    private $languages;

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
