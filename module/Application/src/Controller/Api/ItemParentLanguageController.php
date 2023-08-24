<?php

namespace Application\Controller\Api;

use Application\Hydrator\Api\AbstractRestHydrator;
use Application\Model\ItemParent;
use Autowp\User\Controller\Plugin\User;
use Exception;
use Laminas\ApiTools\ApiProblem\ApiProblem;
use Laminas\ApiTools\ApiProblem\ApiProblemResponse;
use Laminas\Db\TableGateway\TableGateway;
use Laminas\Http\PhpEnvironment\Response;
use Laminas\InputFilter\InputFilter;
use Laminas\InputFilter\InputFilterInterface;
use Laminas\Mvc\Controller\AbstractRestfulController;
use Laminas\Stdlib\ResponseInterface;
use Laminas\View\Model\ViewModel;

use function array_key_exists;
use function array_keys;

/**
 * @method User user($user = null)
 * @method ViewModel forbiddenAction()
 * @method ApiProblemResponse inputFilterResponse(InputFilterInterface $inputFilter)
 */
class ItemParentLanguageController extends AbstractRestfulController
{
    private ItemParent $itemParent;

    private InputFilter $putInputFilter;

    public function __construct(
        ItemParent $itemParent,
        InputFilter $putInputFilter
    ) {
        $this->itemParent     = $itemParent;
        $this->putInputFilter = $putInputFilter;
    }

    /**
     * @return ViewModel|ResponseInterface|array
     * @throws Exception
     */
    public function putAction()
    {
        if (! $this->user()->enforce('global', 'moderate')) {
            return $this->forbiddenAction();
        }

        $data = $this->processBodyContent($this->getRequest());

        $fields = [];
        foreach (array_keys($data) as $key) {
            if ($this->putInputFilter->has($key)) {
                $fields[] = $key;
            }
        }

        $this->putInputFilter->setValidationGroup($fields);

        if (! $fields) {
            return new ApiProblemResponse(new ApiProblem(400, 'Invalid request'));
        }

        $this->putInputFilter->setData($data);

        if (! $this->putInputFilter->isValid()) {
            return $this->inputFilterResponse($this->putInputFilter);
        }

        $data = $this->putInputFilter->getValues();

        $language = (string) $this->params('language');

        /** @psalm-suppress InvalidCast */
        $itemId = (int) $this->params('item_id');
        /** @psalm-suppress InvalidCast */
        $parentId = (int) $this->params('parent_id');

        if (array_key_exists('name', $data)) {
            $this->itemParent->setItemParentLanguage($parentId, $itemId, $language, [
                'name' => $data['name'],
            ], false);
        }

        /** @var Response $response */
        $response = $this->getResponse();
        return $response->setStatusCode(Response::STATUS_CODE_200);
    }
}
