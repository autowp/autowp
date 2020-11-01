<?php

namespace Application\View\Helper;

use ArrayObject;
use Laminas\Db\TableGateway\TableGateway;
use Laminas\I18n\View\Helper\Translate;
use Laminas\View\Helper\AbstractHelper;
use Laminas\View\Renderer\PhpRenderer;

use function Autowp\Commons\currentFromResultSetInterface;
use function is_array;
use function is_numeric;

class Page extends AbstractHelper
{
    private TableGateway $pageTable;

    /** @var null|array|ArrayObject */
    private $doc;

    private array $pages = [];

    public function __construct(TableGateway $pageTable)
    {
        $this->doc       = null;
        $this->pageTable = $pageTable;
    }

    /**
     * @param array|ArrayObject|int $value
     */
    public function __invoke($value): self
    {
        if ($value) {
            $doc = null;

            if ($value instanceof ArrayObject || is_array($value)) {
                $doc = $value;
            } elseif (is_numeric($value)) {
                $doc = $this->getPageById($value);
            }

            $this->doc = $doc;
        }

        return $this;
    }

    public function __get(string $name): string
    {
        if (! $this->doc) {
            return '';
        }
        switch ($name) {
            case 'name':
            case 'title':
            case 'breadcrumbs':
                $key = 'page/' . $this->doc['id'] . '/' . $name;

                /** @var PhpRenderer $view */
                $view = $this->view;
                /** @var Translate $translateHelper */
                $translateHelper = $view->getHelperPluginManager()->get('translate');

                $result = $translateHelper($key);
                if (! $result || $result === $key) {
                    $result = $translateHelper($key, null, 'en');
                }

                if ((! $result || $result === $key) && ($name !== 'name')) {
                    $key = 'page/' . $this->doc['id'] . '/name';

                    $result = $translateHelper($key);
                    if (! $result || $result === $key) {
                        $result = $translateHelper($key, null, 'en');
                    }
                }

                return $result;
        }

        return '';
    }

    /**
     * @return array|ArrayObject
     */
    private function getPageById(int $id)
    {
        if (isset($this->pages[$id])) {
            return $this->pages[$id];
        }

        $row = currentFromResultSetInterface($this->pageTable->select([
            'id' => $id,
        ]));

        $this->pages[$id] = $row;

        return $row;
    }
}
