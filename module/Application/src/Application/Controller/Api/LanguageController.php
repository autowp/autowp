<?php

namespace Application\Controller\Api;

use Laminas\Mvc\Controller\AbstractRestfulController;
use Laminas\Stdlib\ResponseInterface;
use Laminas\View\Model\JsonModel;
use Laminas\View\Model\ViewModel;
use Locale;

use function array_keys;

class LanguageController extends AbstractRestfulController
{
    private array $hosts;

    public function __construct(array $hosts)
    {
        $this->hosts = $hosts;
    }

    /**
     * @return ViewModel|ResponseInterface|array
     */
    public function listAction()
    {
        $languages = [];
        foreach (array_keys($this->hosts) as $language) {
            $languages[$language] = Locale::getDisplayLanguage($language, $language);
        }

        return new JsonModel([
            'items' => $languages,
        ]);
    }
}
