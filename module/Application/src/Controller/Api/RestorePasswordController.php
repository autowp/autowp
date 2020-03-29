<?php

namespace Application\Controller\Api;

use Application\HostManager;
use Application\Service\UsersService;
use Autowp\User\Model\User;
use Autowp\User\Model\UserPasswordRemind;
use Laminas\ApiTools\ApiProblem\ApiProblem;
use Laminas\ApiTools\ApiProblem\ApiProblemResponse;
use Laminas\InputFilter\InputFilter;
use Laminas\Mail;
use Laminas\Mvc\Controller\AbstractRestfulController;
use Laminas\Stdlib\ResponseInterface;
use Laminas\View\Model\JsonModel;
use Laminas\View\Model\ViewModel;
use ReCaptcha\ReCaptcha;

use function sprintf;

/**
 * @method ApiProblemResponse inputFilterResponse(InputFilter $inputFilter)
 * @method string translate(string $message, string $textDomain = 'default', $locale = null)
 */
class RestorePasswordController extends AbstractRestfulController
{
    private UsersService $service;

    private InputFilter $requestInputFilter;

    private InputFilter $newPasswordInputFilter;

    private Mail\Transport\TransportInterface $transport;

    private HostManager $hostManager;

    private UserPasswordRemind $userPasswordRemind;

    private User $userModel;

    private array $recaptcha;

    private bool $captchaEnabled;

    public function __construct(
        UsersService $service,
        InputFilter $requestInputFilter,
        InputFilter $newPasswordInputFilter,
        Mail\Transport\TransportInterface $transport,
        HostManager $hostManager,
        UserPasswordRemind $userPasswordRemind,
        User $userModel,
        array $recaptcha,
        bool $captchaEnabled
    ) {
        $this->service                = $service;
        $this->requestInputFilter     = $requestInputFilter;
        $this->newPasswordInputFilter = $newPasswordInputFilter;
        $this->transport              = $transport;
        $this->hostManager            = $hostManager;
        $this->userPasswordRemind     = $userPasswordRemind;
        $this->userModel              = $userModel;
        $this->recaptcha              = $recaptcha;
        $this->captchaEnabled         = $captchaEnabled;
    }

    /**
     * @return ViewModel|ResponseInterface|array
     */
    public function requestAction()
    {
        $request = $this->getRequest();
        if ($this->requestHasContentType($request, self::CONTENT_TYPE_JSON)) {
            $data = $this->jsonDecode($request->getContent());
        } else {
            /* @phan-suppress-next-line PhanUndeclaredMethod */
            $data = $request->getPost()->toArray();
        }

        if ($this->captchaEnabled) {
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
        }

        $this->requestInputFilter->setData($data);

        if (! $this->requestInputFilter->isValid()) {
            return $this->inputFilterResponse($this->requestInputFilter);
        }

        $values = $this->requestInputFilter->getValues();

        $user = $this->userModel->getRow([
            'email'       => (string) $values['email'],
            'not_deleted' => true,
        ]);

        if (! $user) {
            return $this->notFoundAction();
        }

        $code = $this->userPasswordRemind->createToken($user['id']);

        $uri = $this->hostManager->getUriByLanguage($user['language']);
        $uri->setPath('/restore-password/new');
        $uri->setQuery([
            'code' => $code,
        ]);

        $message = sprintf(
            $this->translate('restore-password/new-password/mail/body-%s', 'default', $user['language']),
            $uri->toString()
        );

        $mail = new Mail\Message();
        $mail
            ->setEncoding('utf-8')
            ->setBody($message)
            ->setFrom('no-reply@autowp.ru', 'robot autowp.ru')
            ->addTo($user['e_mail'], $user['name'])
            ->setSubject($this->translate(
                'restore-password/new-password/mail/subject',
                'default',
                $user['language']
            ));

        $this->transport->send($mail);

        /* @phan-suppress-next-line PhanUndeclaredMethod */
        $this->getResponse()->setStatusCode(201);

        return new JsonModel([
            'ok' => true,
        ]);
    }

    /**
     * @return ViewModel|ResponseInterface|array
     */
    public function newGetAction()
    {
        $code = (string) $this->params()->fromQuery('code');

        if (! $code) {
            return $this->notFoundAction();
        }

        $userId = $this->userPasswordRemind->getUserId($code);

        if (! $userId) {
            return $this->notFoundAction();
        }

        return new JsonModel([
            'code' => $code,
        ]);
    }

    /**
     * @return ViewModel|ResponseInterface|array
     */
    public function newPostAction()
    {
        $request = $this->getRequest();
        if ($this->requestHasContentType($request, self::CONTENT_TYPE_JSON)) {
            $data = $this->jsonDecode($request->getContent());
        } else {
            /* @phan-suppress-next-line PhanUndeclaredMethod */
            $data = $request->getPost()->toArray();
        }

        $this->newPasswordInputFilter->setData($data);

        if (! $this->newPasswordInputFilter->isValid()) {
            return $this->inputFilterResponse($this->newPasswordInputFilter);
        }

        $values = $this->newPasswordInputFilter->getValues();

        $code = (string) $values['code'];

        $userId = $this->userPasswordRemind->getUserId($code);

        if (! $userId) {
            return $this->notFoundAction();
        }

        $user = $this->userModel->getRow((int) $userId);

        if (! $user) {
            return $this->notFoundAction();
        }

        $this->service->setPassword($user, $values['password']);

        $this->userPasswordRemind->deleteToken($code);

        return new JsonModel([
            'username' => $user['e_mail'] ? $user['e_mail'] : $user['login'],
        ]);
    }
}
