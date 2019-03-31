<?php

namespace Application\Hydrator\Api;

use Exception;
use Traversable;

use Zend\Db\TableGateway\TableGateway;
use Zend\Hydrator\Exception\InvalidArgumentException;
use Zend\Hydrator\Strategy\DateTimeFormatterStrategy;

use Autowp\Commons\Db\Table\Row;
use Autowp\User\Model\User;

use Application\Controller\Api\ArticleController;

class ArticleHydrator extends RestHydrator
{
    /**
     * @var User
     */
    private $userModel;

    /**
     * @var TableGateway
     */
    private $htmlTable;

    public function __construct(
        $serviceManager
    ) {
        parent::__construct();

        $this->userModel = $serviceManager->get(User::class);

        $strategy = new Strategy\User($serviceManager);
        $this->addStrategy('author', $strategy);

        $strategy = new DateTimeFormatterStrategy();
        $this->addStrategy('date', $strategy);

        $tables = $serviceManager->get('TableManager');
        $this->htmlTable = $tables->get('htmls');
    }

    /**
     * @param  array|Traversable $options
     * @return RestHydrator
     * @throws InvalidArgumentException
     */
    public function setOptions($options)
    {
        parent::setOptions($options);

        return $this;
    }

    public function extract($object)
    {
        $previewUrl = null;
        if ($object['preview_filename']) {
            $previewUrl = ArticleController::PREVIEW_CAT_PATH . $object['preview_filename'];
        }

        $date = Row::getDateTimeByColumnType('timestamp', $object['first_enabled_datetime']);

        $result = [
            'id'          => (int)$object['id'],
            'author_id'   => (int)$object['author_id'],
            'catname'     => $object['catname'],
            'preview_url' => $previewUrl,
            'name'        => $object['name'],
            'date'        => $this->extractValue('date', $date),
        ];

        if ($this->filterComposite->filter('author')) {
            $user = $this->userModel->getRow((int)$object['author_id']);

            $result['author'] = $user ? $this->extractValue('author', $user) : null;
        }

        if ($this->filterComposite->filter('description')) {
            $result['description'] = $object['description'];
        }

        if ($this->filterComposite->filter('html')) {
            $htmlRow = $this->htmlTable->select([
                'id' => (int)$object['html_id']
            ])->current();
            $result['html'] = $htmlRow ? $htmlRow['html'] : null;
        }

        return $result;
    }

    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @param array $data
     * @param $object
     * @throws Exception
     */
    public function hydrate(array $data, $object)
    {
        throw new Exception("Not supported");
    }
}
