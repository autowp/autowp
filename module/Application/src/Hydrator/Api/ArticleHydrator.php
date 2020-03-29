<?php

namespace Application\Hydrator\Api;

use Application\Controller\Api\ArticleController;
use ArrayAccess;
use Autowp\Commons\Db\Table\Row;
use Autowp\User\Model\User;
use Exception;
use Laminas\Db\TableGateway\TableGateway;
use Laminas\Hydrator\Exception\InvalidArgumentException;
use Laminas\Hydrator\Strategy\DateTimeFormatterStrategy;
use Laminas\ServiceManager\ServiceLocatorInterface;
use Traversable;

class ArticleHydrator extends AbstractRestHydrator
{
    private User $userModel;

    private TableGateway $htmlTable;

    public function __construct(ServiceLocatorInterface $serviceManager)
    {
        parent::__construct();

        $this->userModel = $serviceManager->get(User::class);

        $strategy = new Strategy\User($serviceManager);
        $this->addStrategy('author', $strategy);

        $strategy = new DateTimeFormatterStrategy();
        $this->addStrategy('date', $strategy);

        $tables          = $serviceManager->get('TableManager');
        $this->htmlTable = $tables->get('htmls');
    }

    /**
     * @param  array|Traversable $options
     * @throws InvalidArgumentException
     */
    public function setOptions($options): self
    {
        parent::setOptions($options);

        return $this;
    }

    /**
     * @param array|ArrayAccess $object
     */
    public function extract($object): ?array
    {
        $previewUrl = null;
        if ($object['preview_filename']) {
            $previewUrl = ArticleController::PREVIEW_CAT_PATH . $object['preview_filename'];
        }

        $date = Row::getDateTimeByColumnType('timestamp', $object['first_enabled_datetime']);

        $result = [
            'id'          => (int) $object['id'],
            'author_id'   => (int) $object['author_id'],
            'catname'     => $object['catname'],
            'preview_url' => $previewUrl,
            'name'        => $object['name'],
            'date'        => $this->extractValue('date', $date),
        ];

        if ($this->filterComposite->filter('author')) {
            $user = $this->userModel->getRow((int) $object['author_id']);

            $result['author'] = $user ? $this->extractValue('author', $user) : null;
        }

        if ($this->filterComposite->filter('description')) {
            $result['description'] = $object['description'];
        }

        if ($this->filterComposite->filter('html')) {
            $htmlRow        = $this->htmlTable->select([
                'id' => (int) $object['html_id'],
            ])->current();
            $result['html'] = $htmlRow ? $htmlRow['html'] : null;
        }

        return $result;
    }

    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @param object $object
     * @throws Exception
     */
    public function hydrate(array $data, $object)
    {
        throw new Exception("Not supported");
    }
}
