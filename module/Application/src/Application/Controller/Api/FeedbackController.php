<?php

namespace Application\Controller\Api;

use ReCaptcha\ReCaptcha;
use Zend\InputFilter\InputFilter;
use Zend\Mail;
use Zend\Mvc\Controller\AbstractRestfulController;
use Zend\Session\Container;
use ZF\ApiProblem\ApiProblemResponse;
use ZF\ApiProblem\ApiProblem;

/**
 * Class FeedbackController
 * @package Application\Controller\Api
 *
 * @method ApiProblemResponse inputFilterResponse(InputFilter $inputFilter)
 */
class FeedbackController extends AbstractRestfulController
{
    /**
     * @var InputFilter
     */
    private $postInputFilter;

    private $transport;

    /**
     * @var array
     */
    private $options;

    /**
     * @var array
     */
    private $recaptcha;

    /**
     * @var bool
     */
    private $captchaEnabled;

    public function __construct(
        InputFilter $postInputFilter,
        $transport,
        array $options,
        array $recaptcha,
        bool $captchaEnabled
    ) {
        $this->postInputFilter = $postInputFilter;
        $this->transport = $transport;
        $this->options = $options;
        $this->recaptcha = $recaptcha;
        $this->captchaEnabled = $captchaEnabled;
    }

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
            $verified = isset($namespace->success) && $namespace->success;

            if (! $verified) {
                $recaptcha = new ReCaptcha($this->recaptcha['privateKey']);

                $captchaResponse = null;
                if (isset($data['captcha'])) {
                    $captchaResponse = (string)$data['captcha'];
                }

                /* @phan-suppress-next-line PhanUndeclaredMethod */
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


        $this->postInputFilter->setData($data);

        if (! $this->postInputFilter->isValid()) {
            return $this->inputFilterResponse($this->postInputFilter);
        }

        $data = $this->postInputFilter->getValues();


        $message = sprintf(
            "Имя: %s\nE-mail: %s\nСообщение:\n%s",
            $data['name'],
            $data['email'],
            $data['message']
        );

        $mail = new Mail\Message();
        $mail
            ->setEncoding('utf-8')
            ->setBody($message)
            ->setFrom($this->options['from'], $this->options['fromname'])
            ->addTo($this->options['to'])
            ->setSubject($this->options['subject']);

        if ($data['email']) {
            $mail->setReplyTo($data['email'], $data['name']);
        }

        $this->transport->send($mail);

        /* @phan-suppress-next-line PhanUndeclaredMethod */
        return $this->getResponse()->setStatusCode(200);
    }
}
