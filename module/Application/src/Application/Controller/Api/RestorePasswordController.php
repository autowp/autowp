<?php

namespace Application\Controller\Api;

use Zend\Authentication\AuthenticationService;
use Zend\InputFilter\InputFilter;
use Zend\Mail;
use Zend\Mvc\Controller\AbstractRestfulController;
use Zend\View\Model\JsonModel;

use ReCaptcha\ReCaptcha;
use ZF\ApiProblem\ApiProblemResponse;
use ZF\ApiProblem\ApiProblem;

use Autowp\User\Auth\Adapter\Id as IdAuthAdapter;
use Autowp\User\Model\User;
use Autowp\User\Model\UserPasswordRemind;

use Application\HostManager;
use Application\Service\UsersService;

class RestorePasswordController extends AbstractRestfulController
{
    /**
     * @var UsersService
     */
    private $service;

    /**
     * @var InputFilter
     */
    private $requestInputFilter;

    /**
     * @var InputFilter
     */
    private $newPasswordInputFilter;

    private $transport;

    /**
     * @var HostManager
     */
    private $hostManager;

    /**
     * @var UserPasswordRemind
     */
    private $userPasswordRemind;

    /**
     * @var User
     */
    private $userModel;

    /**
     * @var array
     */
    private $recaptcha;

    /**
     * @var bool
     */
    private $captchaEnabled;

    public function __construct(
        UsersService $service,
        InputFilter $requestInputFilter,
        InputFilter $newPasswordInputFilter,
        $transport,
        HostManager $hostManager,
        UserPasswordRemind $userPasswordRemind,
        User $userModel,
        array $recaptcha,
        bool $captchaEnabled
    ) {
        $this->service = $service;
        $this->requestInputFilter = $requestInputFilter;
        $this->newPasswordInputFilter = $newPasswordInputFilter;
        $this->transport = $transport;
        $this->hostManager = $hostManager;
        $this->userPasswordRemind = $userPasswordRemind;
        $this->userModel = $userModel;
        $this->recaptcha = $recaptcha;
        $this->captchaEnabled = $captchaEnabled;
    }

    public function requestAction()
    {
        $request = $this->getRequest();
        if ($this->requestHasContentType($request, self::CONTENT_TYPE_JSON)) {
            $data = $this->jsonDecode($request->getContent());
        } else {
            $data = $request->getPost()->toArray();
        }

        if ($this->captchaEnabled) {
            $namespace = new \Zend\Session\Container('Captcha');
            $verified = isset($namespace->success) && $namespace->success;

            if (! $verified) {
                $recaptcha = new ReCaptcha($this->recaptcha['privateKey']);

                $captchaResponse = null;
                if (isset($data['captcha'])) {
                    $captchaResponse = (string)$data['captcha'];
                }

                $result = $recaptcha->verify($captchaResponse, $this->getRequest()->getServer('REMOTE_ADDR'));

                if (! $result->isSuccess()) {
                    return new ApiProblemResponse(
                        new ApiProblem(400, 'Data is invalid. Check `detail`.', null, 'Validation error', [
                            'invalid_params' => [
                                'captcha' => [
                                    'invalid' => 'Captcha is invalid'
                                ]
                            ]
                        ])
                    );
                }

                $namespace->success = true;
            }
        }

        $this->requestInputFilter->setData($data);

        if (! $this->requestInputFilter->isValid()) {
            return $this->inputFilterResponse($this->requestInputFilter);
        }

        $values = $this->requestInputFilter->getValues();

        $user = $this->userModel->getRow([
            'email'       => (string)$values['email'],
            'not_deleted' => true
        ]);

        if (! $user) {
            return $this->notFoundAction();
        }

        $code = $this->userPasswordRemind->createToken($user['id']);

        $uri = $this->hostManager->getUriByLanguage($user['language']);

        $url = $this->url()->fromRoute('ng', [
            'path' => 'restore-password/new'
        ], [
            'force_canonical' => true,
            'uri'             => $uri,
            'query'           => [
                'code' => $code
            ]
        ]);

        $message = sprintf(
            $this->translate('restore-password/new-password/mail/body-%s', 'default', $user['language']),
            $url
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

        $this->getResponse()->setContent($url);

        return $this->getResponse()->setStatusCode(201);
    }

    public function newGetAction()
    {
        $code = (string)$this->params()->fromQuery('code');

        if (! $code) {
            return $this->notFoundAction();
        }

        $userId = $this->userPasswordRemind->getUserId($code);

        if (! $userId) {
            return $this->notFoundAction();
        }

        return new JsonModel([
            'code' => $code
        ]);
    }

    public function newPostAction()
    {
        $request = $this->getRequest();
        if ($this->requestHasContentType($request, self::CONTENT_TYPE_JSON)) {
            $data = $this->jsonDecode($request->getContent());
        } else {
            $data = $request->getPost()->toArray();
        }

        $this->newPasswordInputFilter->setData($data);

        if (! $this->newPasswordInputFilter->isValid()) {
            return $this->inputFilterResponse($this->newPasswordInputFilter);
        }

        $values = $this->newPasswordInputFilter->getValues();

        $code = (string)$values['code'];

        $userId = $this->userPasswordRemind->getUserId($code);

        if (! $userId) {
            return $this->notFoundAction();
        }

        $user = $this->userModel->getRow((int)$userId);

        if (! $user) {
            return $this->notFoundAction();
        }

        $this->service->setPassword($user, $values['password']);

        $this->userPasswordRemind->deleteToken($code);

        $adapter = new IdAuthAdapter($this->userModel);
        $adapter->setIdentity($user['id']);

        $auth = new AuthenticationService();
        $auth->authenticate($adapter);

        return $this->getResponse()->setStatusCode(200);
    }
}
