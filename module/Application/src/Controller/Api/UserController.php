<?php

namespace Application\Controller\Api;

use Application\Hydrator\Api\AbstractRestHydrator;
use Autowp\Commons\Db\Table\Row;
use Autowp\Image\Storage;
use Autowp\User\Controller\Plugin\User as UserPlugin;
use Autowp\User\Model\User;
use Casbin\Enforcer;
use Casbin\Exceptions\CasbinException;
use DateInterval;
use DateTime;
use DateTimeZone;
use Exception;
use Imagick;
use Laminas\ApiTools\ApiProblem\ApiProblem;
use Laminas\ApiTools\ApiProblem\ApiProblemResponse;
use Laminas\Http\PhpEnvironment\Request;
use Laminas\Http\PhpEnvironment\Response;
use Laminas\InputFilter\InputFilter;
use Laminas\InputFilter\InputFilterInterface;
use Laminas\Mvc\Controller\AbstractRestfulController;
use Laminas\Stdlib\ResponseInterface;
use Laminas\Validator\InArray;
use Laminas\View\Model\JsonModel;
use Laminas\View\Model\ViewModel;

use function array_key_exists;
use function array_keys;
use function get_object_vars;
use function in_array;
use function is_array;
use function sprintf;

/**
 * @method UserPlugin user($user = null)
 * @method ApiProblemResponse inputFilterResponse(InputFilterInterface $inputFilter)
 * @method ViewModel forbiddenAction()
 * @method string language()
 * @method void log(string $message, array $objects)
 * @method string translate(string $message, string $textDomain = 'default', $locale = null)
 */
class UserController extends AbstractRestfulController
{
    private Enforcer $acl;

    private AbstractRestHydrator $hydrator;

    private InputFilter $itemInputFilter;

    private InputFilter $listInputFilter;

    private InputFilter $putInputFilter;

    private User $userModel;

    private InputFilter $postPhotoInputFilter;

    /** @var array<string, mixed> */
    private array $hosts;

    private Storage $imageStorage;

    public function __construct(
        Enforcer $acl,
        AbstractRestHydrator $hydrator,
        InputFilter $itemInputFilter,
        InputFilter $listInputFilter,
        InputFilter $putInputFilter,
        InputFilter $postPhotoInputFilter,
        User $userModel,
        array $hosts,
        Storage $imageStorage
    ) {
        $this->acl                  = $acl;
        $this->hydrator             = $hydrator;
        $this->itemInputFilter      = $itemInputFilter;
        $this->listInputFilter      = $listInputFilter;
        $this->putInputFilter       = $putInputFilter;
        $this->postPhotoInputFilter = $postPhotoInputFilter;
        $this->userModel            = $userModel;
        $this->hosts                = $hosts;
        $this->imageStorage         = $imageStorage;
    }

    /**
     * @return ViewModel|ResponseInterface|array
     * @throws Exception
     */
    public function indexAction()
    {
        $user = $this->user()->get();

        $this->listInputFilter->setData($this->params()->fromQuery());

        if (! $this->listInputFilter->isValid()) {
            return $this->inputFilterResponse($this->listInputFilter);
        }

        $data = $this->listInputFilter->getValues();

        $filter = [
            'not_deleted' => true,
        ];

        $search = $data['search'];
        if ($search) {
            $filter['search'] = $search . '%';
        }

        if ($data['id']) {
            $filter['id'] = is_array($data['id']) ? $data['id'] : (int) $data['id'];
        }

        if ($data['identity']) {
            $filter['identity'] = $data['identity'];
        }

        $paginator = $this->userModel->getPaginator($filter);

        $limit = $data['limit'] ?: 1;

        $paginator
            ->setItemCountPerPage($limit)
            ->setCurrentPageNumber($data['page']);

        $this->hydrator->setOptions([
            'language' => $this->language(),
            'fields'   => $data['fields'],
            'user_id'  => $user ? $user['id'] : null,
        ]);

        $items = [];
        foreach ($paginator->getCurrentItems() as $row) {
            $items[] = $this->hydrator->extract($row);
        }

        return new JsonModel([
            'paginator' => get_object_vars($paginator->getPages()),
            'items'     => $items,
        ]);
    }

    /**
     * @return ViewModel|ResponseInterface|array
     * @throws Exception
     */
    public function itemAction()
    {
        $this->itemInputFilter->setData($this->params()->fromQuery());

        if (! $this->itemInputFilter->isValid()) {
            return $this->inputFilterResponse($this->itemInputFilter);
        }

        $data = $this->itemInputFilter->getValues();

        $user = $this->user()->get();

        $id = $this->params('id');

        if ($id === 'me') {
            if (! $user) {
                return new ApiProblemResponse(new ApiProblem(401, 'Not authorized'));
            }
            $id = $user['id'];
        }

        $row = $this->userModel->getRow((int) $id);
        if (! $row) {
            return $this->notFoundAction();
        }

        $this->hydrator->setOptions([
            'language' => $this->language(),
            'fields'   => $data['fields'],
            'user_id'  => $user ? $user['id'] : null,
        ]);

        return new JsonModel($this->hydrator->extract($row));
    }

    /**
     * @return ViewModel|ResponseInterface|array
     * @throws Exception
     */
    public function putAction()
    {
        $user = $this->user()->get();

        if (! $user) {
            return new ApiProblemResponse(new ApiProblem(401, 'Not authorized'));
        }

        $id = $this->params('id');
        if ($id === 'me') {
            $id = $user['id'];
        }

        $row = $this->userModel->getRow((int) $id);
        if (! $row) {
            return new ApiProblemResponse(new ApiProblem(404, 'Entity not found'));
        }

        $request = $this->getRequest();
        $data    = $this->processBodyContent($request);

        $fields = [];
        foreach (array_keys($data) as $key) {
            if ($this->putInputFilter->has($key)) {
                $fields[] = $key;
            }
        }

        if (! $fields) {
            return new ApiProblemResponse(new ApiProblem(400, 'No fields provided'));
        }

        if (in_array('language', $fields)) {
            // preload filter options
            $languages = [];
            foreach (array_keys($this->hosts) as $language) {
                $languages[] = $language;
            }
            $validators = $this->putInputFilter->get('language')->getValidatorChain()->getValidators();
            /** @var InArray $validator */
            $validator = $validators[0]['instance'];
            $validator->setHaystack($languages);
        }

        if (in_array('timezone', $fields)) {
            // preload filter options
            $list = [];
            foreach (DateTimeZone::listAbbreviations() as $group) {
                foreach ($group as $timeZone) {
                    $tzId = $timeZone['timezone_id'];
                    if ($tzId) {
                        $list[] = $tzId;
                    }
                }
            }

            $validators = $this->putInputFilter->get('timezone')->getValidatorChain()->getValidators();
            /** @var InArray $validator */
            $validator = $validators[0]['instance'];
            $validator->setHaystack($list);
        }

        $this->putInputFilter->setValidationGroup($fields);

        $this->putInputFilter->setData($data);
        if (! $this->putInputFilter->isValid()) {
            return $this->inputFilterResponse($this->putInputFilter);
        }

        $values = $this->putInputFilter->getValues();

        if (array_key_exists('language', $values)) {
            if ((int) $user['id'] !== (int) $row['id']) {
                return $this->forbiddenAction();
            }

            $this->userModel->getTable()->update([
                'language' => $values['language'],
            ], [
                'id' => $row['id'],
            ]);
        }

        if (array_key_exists('timezone', $values)) {
            if ((int) $user['id'] !== (int) $row['id']) {
                return $this->forbiddenAction();
            }

            $this->userModel->getTable()->update([
                'timezone' => $values['timezone'],
            ], [
                'id' => $row['id'],
            ]);
        }

        /** @var Response $response */
        $response = $this->getResponse();
        return $response->setStatusCode(Response::STATUS_CODE_200);
    }

    /**
     * @return ViewModel|ResponseInterface|array
     * @throws Exception
     */
    public function deletePhotoAction()
    {
        $user = $this->user()->get();

        $id = $this->params('id');
        if ($id === 'me') {
            if (! $user) {
                return new ApiProblemResponse(new ApiProblem(401, 'Not authorized'));
            }
            $id = $user['id'];
        }

        $row = $this->userModel->getRow((int) $id);
        if (! $row) {
            return new ApiProblemResponse(new ApiProblem(404, 'Entity not found'));
        }

        $can = $this->user()->enforce('user', 'ban');
        if (! $can) {
            return $this->forbiddenAction();
        }

        $oldImageId = $row['img'];
        if ($oldImageId) {
            $this->userModel->getTable()->update([
                'img' => null,
            ], [
                'id' => $row['id'],
            ]);

            $this->imageStorage->removeImage($oldImageId);
        }

        $this->log(sprintf(
            'Удаление фотографии пользователя №%s',
            $row['id']
        ), [
            'users' => $row['id'],
        ]);

        /** @var Response $response */
        $response = $this->getResponse();
        return $response->setStatusCode(Response::STATUS_CODE_204);
    }

    /**
     * @return ViewModel|ResponseInterface|array
     * @throws Exception
     */
    public function postPhotoAction()
    {
        $user = $this->user()->get();

        if (! $user) {
            return $this->forbiddenAction();
        }

        $id = $this->params('id');
        if ($id === 'me') {
            $id = $user['id'];
        }

        $row = $this->userModel->getRow((int) $id);
        if (! $row) {
            return $this->notFoundAction();
        }

        /** @var Request $request */
        $request = $this->getRequest();

        $data = $request->getFiles()->toArray();

        $this->postPhotoInputFilter->setData($data);
        if (! $this->postPhotoInputFilter->isValid()) {
            return $this->inputFilterResponse($this->postPhotoInputFilter);
        }

        $values = $this->postPhotoInputFilter->getValues();

        $imageSampler = $this->imageStorage->getImageSampler();

        $imagick = new Imagick();
        if (! $imagick->readImage($values['file']['tmp_name'])) {
            throw new Exception("Error loading image");
        }
        $format = $this->imageStorage->getFormat('photo');
        $imageSampler->convertImagick($imagick, null, $format);

        $newImageId = $this->imageStorage->addImageFromImagick($imagick, 'user', [
            's3' => true,
        ]);

        $imagick->clear();

        $oldImageId = $row['img'];

        $this->userModel->getTable()->update([
            'img' => $newImageId,
        ], [
            'id' => $row['id'],
        ]);

        if ($oldImageId) {
            $this->imageStorage->removeImage($oldImageId);
        }

        /** @var Response $response */
        $response = $this->getResponse();
        return $response->setStatusCode(Response::STATUS_CODE_201);
    }
}
