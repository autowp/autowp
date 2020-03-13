<?php

namespace Application\Controller\Api;

use Application\Hydrator\Api\AbstractRestHydrator;
use Application\Service\UsersService;
use Autowp\Commons\Db\Table\Row;
use Autowp\Image\Storage;
use Autowp\User\Auth\Adapter\Id as IdAuthAdapter;
use Autowp\User\Controller\Plugin\User as UserPlugin;
use Autowp\User\Model\User;
use Autowp\User\Model\UserRename;
use DateInterval;
use DateTime;
use DateTimeZone;
use Exception;
use Imagick;
use Laminas\ApiTools\ApiProblem\ApiProblem;
use Laminas\ApiTools\ApiProblem\ApiProblemResponse;
use Laminas\Authentication\AuthenticationService;
use Laminas\InputFilter\InputFilter;
use Laminas\Mvc\Controller\AbstractRestfulController;
use Laminas\Permissions\Acl\Acl;
use Laminas\Session\Container;
use Laminas\Stdlib\ResponseInterface;
use Laminas\View\Model\JsonModel;
use Laminas\View\Model\ViewModel;
use ReCaptcha\ReCaptcha;

use function array_key_exists;
use function array_keys;
use function get_object_vars;
use function in_array;
use function is_array;
use function sprintf;

/**
 * @method UserPlugin user($user = null)
 * @method ApiProblemResponse inputFilterResponse(InputFilter $inputFilter)
 * @method ViewModel forbiddenAction()
 * @method string language()
 * @method void log(string $message, array $objects)
 * @method Storage imageStorage()
 * @method string translate(string $message, string $textDomain = 'default', $locale = null)
 */
class UserController extends AbstractRestfulController
{
    private Acl $acl;

    private AbstractRestHydrator $hydrator;

    private InputFilter $itemInputFilter;

    private InputFilter $listInputFilter;

    private InputFilter $putInputFilter;

    private UsersService $userService;

    private User $userModel;

    private InputFilter $postInputFilter;

    private InputFilter $postPhotoInputFilter;

    private array $recaptcha;

    private bool $captchaEnabled;

    private UserRename $userRename;

    private array $hosts;

    public function __construct(
        Acl $acl,
        AbstractRestHydrator $hydrator,
        InputFilter $itemInputFilter,
        InputFilter $listInputFilter,
        InputFilter $postInputFilter,
        InputFilter $putInputFilter,
        InputFilter $postPhotoInputFilter,
        UsersService $userService,
        User $userModel,
        array $recaptcha,
        bool $captchaEnabled,
        UserRename $userRename,
        array $hosts
    ) {
        $this->acl                  = $acl;
        $this->hydrator             = $hydrator;
        $this->itemInputFilter      = $itemInputFilter;
        $this->listInputFilter      = $listInputFilter;
        $this->postInputFilter      = $postInputFilter;
        $this->putInputFilter       = $putInputFilter;
        $this->postPhotoInputFilter = $postPhotoInputFilter;
        $this->userService          = $userService;
        $this->userModel            = $userModel;
        $this->recaptcha            = $recaptcha;
        $this->captchaEnabled       = $captchaEnabled;
        $this->userRename           = $userRename;
        $this->hosts                = $hosts;
    }

    /**
     * @return ViewModel|ResponseInterface|array
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

        $limit = $data['limit'] ? $data['limit'] : 1;

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
            $validators[0]['instance']->setHaystack($languages);
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
            $validators[0]['instance']->setHaystack($list);
        }

        $this->putInputFilter->setValidationGroup($fields);

        $this->putInputFilter->setData($data);
        if (! $this->putInputFilter->isValid()) {
            return $this->inputFilterResponse($this->putInputFilter);
        }

        $values = $this->putInputFilter->getValues();

        if (array_key_exists('deleted', $values)) {
            $can = $this->user()->isAllowed('user', 'delete');

            if (! $can) {
                if (! isset($values['password_old'])) {
                    return new ApiProblemResponse(
                        new ApiProblem(400, 'Data is invalid. Check `detail`.', null, 'Validation error', [
                            'invalid_params' => [
                                'password_old' => [
                                    'invalid' => 'Old password is required',
                                ],
                            ],
                        ])
                    );
                }

                $correct = $this->userService->checkPassword($row['id'], $values['password_old']);

                if (! $correct) {
                    return new ApiProblemResponse(
                        new ApiProblem(400, 'Data is invalid. Check `detail`.', null, 'Validation error', [
                            'invalid_params' => [
                                'password_old' => [
                                    'invalid' => $this->translate('account/access/self-delete/password-is-incorrect'),
                                ],
                            ],
                        ])
                    );
                }

                $can = true;
            }

            if (! $can) {
                return $this->forbiddenAction();
            }

            if ($values['deleted'] && ! $row['deleted']) {
                $this->userService->markDeleted($row['id']);

                $this->log(sprintf(
                    'Удаление пользователя №%s',
                    $row['id']
                ), [
                    'users' => $row['id'],
                ]);
            }

            if ($user['id'] === $row['id']) { // self-delete
                $auth = new AuthenticationService();
                $auth->clearIdentity();
                $this->userService->clearRememberCookie($this->language());
            }
        }

        if (array_key_exists('name', $values)) {
            if ($user['id'] !== $row['id']) {
                return $this->forbiddenAction();
            }

            $oldName = $user['name'];

            $this->userModel->getTable()->update([
                'name' => $values['name'],
            ], [
                'id' => $user['id'],
            ]);

            $newName = $values['name'];

            if ($oldName !== $newName) {
                $this->userRename->add($user['id'], $oldName, $newName);
            }
        }

        if (array_key_exists('language', $values)) {
            if ($user['id'] !== $row['id']) {
                return $this->forbiddenAction();
            }

            $this->userModel->getTable()->update([
                'language' => $values['language'],
            ], [
                'id' => $row['id'],
            ]);
        }

        if (array_key_exists('timezone', $values)) {
            if ($user['id'] !== $row['id']) {
                return $this->forbiddenAction();
            }

            $this->userModel->getTable()->update([
                'timezone' => $values['timezone'],
            ], [
                'id' => $row['id'],
            ]);
        }

        if (array_key_exists('email', $values)) {
            $this->userService->changeEmailStart($user, $values['email'], $this->language());
        }

        if (array_key_exists('password', $values)) {
            if (! isset($values['password_old'])) {
                return new ApiProblemResponse(
                    new ApiProblem(400, 'Data is invalid. Check `detail`.', null, 'Validation error', [
                        'invalid_params' => [
                            'password_old' => [
                                'invalid' => 'Old password is required',
                            ],
                        ],
                    ])
                );
            }

            if (! isset($values['password_confirm'])) {
                return new ApiProblemResponse(
                    new ApiProblem(400, 'Data is invalid. Check `detail`.', null, 'Validation error', [
                        'invalid_params' => [
                            'password_confirm' => [
                                'invalid' => 'Confirm password is required',
                            ],
                        ],
                    ])
                );
            }

            $correct = $this->userService->checkPassword($row['id'], $values['password_old']);

            if (! $correct) {
                return new ApiProblemResponse(
                    new ApiProblem(400, 'Data is invalid. Check `detail`.', null, 'Validation error', [
                        'invalid_params' => [
                            'password_old' => [
                                'invalid' => $this->translate(
                                    'account/access/change-password/current-password-is-incorrect'
                                ),
                            ],
                        ],
                    ])
                );
            }

            $this->userService->setPassword($row, $values['password']);
        }

        /* @phan-suppress-next-line PhanUndeclaredMethod */
        return $this->getResponse()->setStatusCode(200);
    }

    /**
     * @return ViewModel|ResponseInterface|array
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

        $can = $this->user()->isAllowed('user', 'ban');
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

            $this->imageStorage()->removeImage($oldImageId);
        }

        $this->log(sprintf(
            'Удаление фотографии пользователя №%s',
            $row['id']
        ), [
            'users' => $row['id'],
        ]);

        /* @phan-suppress-next-line PhanUndeclaredMethod */
        return $this->getResponse()->setStatusCode(204);
    }

    /**
     * @return ViewModel|ResponseInterface|array
     */
    public function postAction()
    {
        $request = $this->getRequest();
        if ($this->requestHasContentType($request, self::CONTENT_TYPE_JSON)) {
            $data = $this->jsonDecode($request->getContent());
        } else {
            /* @phan-suppress-next-line PhanUndeclaredMethod */
            $data = $request->getPost()->toArray();
        }

        if ($this->captchaEnabled) {
            $namespace = new Container('Captcha');
            $verified  = isset($namespace->success) && $namespace->success;

            if (! $verified) {
                $recaptcha = new ReCaptcha($this->recaptcha['privateKey']);

                $captchaResponse = null;
                if (isset($data['captcha'])) {
                    $captchaResponse = (string) $data['captcha'];
                }

                /* @phan-suppress-next-line PhanUndeclaredMethod */
                $result = $recaptcha->verify($captchaResponse, $this->getRequest()->getServer('REMOTE_ADDR'));

                if (! $result->isSuccess()) {
                    return new ApiProblemResponse(
                        new ApiProblem(400, 'Data is invalid. Check `detail`.', null, 'Validation error', [
                            'invalid_params' => [
                                'captcha' => [
                                    'invalid' => 'Captcha is invalid',
                                ],
                            ],
                        ])
                    );
                }

                $namespace->success = true;
            }
        }

        $this->postInputFilter->setData($data);
        if (! $this->postInputFilter->isValid()) {
            return $this->inputFilterResponse($this->postInputFilter);
        }

        $values = $this->postInputFilter->getValues();

        /* @phan-suppress-next-line PhanUndeclaredMethod */
        $ip = $request->getServer('REMOTE_ADDR');
        if (! $ip) {
            $ip = '127.0.0.1';
        }

        $user = $this->userService->addUser([
            'email'    => $values['email'],
            'password' => $values['password'],
            'name'     => $values['name'],
            'ip'       => $ip,
        ], $this->language());

        $url = $this->url()->fromRoute('api/user/user/item', [
            'id' => $user['id'],
        ]);
        $this->getResponse()->getHeaders()->addHeaderLine('Location', $url);

        /* @phan-suppress-next-line PhanUndeclaredMethod */
        return $this->getResponse()->setStatusCode(201);
    }

    public function onlineAction(): JsonModel
    {
        $rows = $this->userModel->getRows([
            'online' => true,
        ]);

        $result = [];
        foreach ($rows as $row) {
            $deleted = (bool) $row['deleted'];

            if ($deleted) {
                $result[] = [
                    'id'       => null,
                    'name'     => null,
                    'deleted'  => $deleted,
                    'url'      => null,
                    'longAway' => false,
                    'green'    => false,
                ];
            } else {
                $longAway   = false;
                $lastOnline = Row::getDateTimeByColumnType('timestamp', $row['last_online']);
                if ($lastOnline) {
                    $date = new DateTime();
                    $date->sub(new DateInterval('P6M'));
                    if ($date > $lastOnline) {
                        $longAway = true;
                    }
                } else {
                    $longAway = true;
                }

                $isGreen = $row['role'] && $this->acl->isAllowed($row['role'], 'status', 'be-green');

                $result[] = [
                    'id'        => $row['id'],
                    'name'      => $row['name'],
                    'deleted'   => $deleted,
                    'url'       => '/users/' . ($row['identity'] ? $row['identity'] : 'user' . $row['id']),
                    'long_away' => $longAway,
                    'green'     => $isGreen,
                    'identity'  => $row['identity'],
                ];
            }
        }

        return new JsonModel([
            'items' => $result,
        ]);
    }

    /**
     * @return ViewModel|ResponseInterface|array
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

        $data = $this->getRequest()->getFiles()->toArray(); // @phan-suppress-current-line PhanUndeclaredMethod

        $this->postPhotoInputFilter->setData($data);
        if (! $this->postPhotoInputFilter->isValid()) {
            return $this->inputFilterResponse($this->postPhotoInputFilter);
        }

        $values = $this->postPhotoInputFilter->getValues();

        $imageStorage = $this->imageStorage();
        $imageSampler = $imageStorage->getImageSampler();

        $imagick = new Imagick();
        if (! $imagick->readImage($values['file']['tmp_name'])) {
            throw new Exception("Error loading image");
        }
        $format = $imageStorage->getFormat('photo');
        $imageSampler->convertImagick($imagick, null, $format);

        $newImageId = $imageStorage->addImageFromImagick($imagick, 'user', [
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
            $imageStorage->removeImage($oldImageId);
        }

        /* @phan-suppress-next-line PhanUndeclaredMethod */
        return $this->getResponse()->setStatusCode(201);
    }

    /**
     * @return ViewModel|ResponseInterface|array
     */
    public function emailcheckAction()
    {
        $request = $this->getRequest();
        if ($this->requestHasContentType($request, self::CONTENT_TYPE_JSON)) {
            $data = $this->jsonDecode($request->getContent());
        } else {
            /* @phan-suppress-next-line PhanUndeclaredMethod */
            $data = $request->getPost()->toArray();
        }

        $code = isset($data['code']) ? (string) $data['code'] : '';
        $user = $this->userService->emailChangeFinish($code);

        if (! $user) {
            return new ApiProblemResponse(new ApiProblem(400, 'Code is invalid'));
        }

        if (! $this->user()->logedIn()) {
            $adapter = new IdAuthAdapter($this->userModel);
            $adapter->setIdentity($user['id']);
            $auth = new AuthenticationService();
            $auth->authenticate($adapter);
        }

        /* @phan-suppress-next-line PhanUndeclaredMethod */
        return $this->getResponse()->setStatusCode(200);
    }
}
