<?php

namespace Application\Controller\Api;

use Locale;

use Zend\Mvc\Controller\AbstractRestfulController;
use Zend\View\Model\JsonModel;

class LanguageController extends AbstractRestfulController
{
    /**
     * @var array
     */
    private $hosts;

    public function __construct(array $hosts)
    {
        $this->hosts = $hosts;
    }

    public function listAction()
    {
        $languages = [];
        foreach (array_keys($this->hosts) as $language) {
            $languages[$language] = Locale::getDisplayLanguage($language, $language);
        }

        return new JsonModel([
            'items' => $languages
        ]);
    }
}
