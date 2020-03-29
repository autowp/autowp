<?php

namespace Application\Controller\Api;

use Laminas\ApiTools\ApiProblem\ApiProblem;
use Laminas\ApiTools\ApiProblem\ApiProblemResponse;
use Laminas\InputFilter\InputFilter;
use Laminas\Mail;
use Laminas\Mail\Transport\TransportInterface;
use Laminas\Mvc\Controller\AbstractRestfulController;
use Laminas\Session\Container;
use Laminas\Stdlib\ResponseInterface;
use Laminas\View\Model\ViewModel;
use ReCaptcha\ReCaptcha;

use function sprintf;

/**
 * @method ApiProblemResponse inputFilterResponse(InputFilter $inputFilter)
 */
class FeedbackController extends AbstractRestfulController
{
    private InputFilter $postInputFilter;

    private TransportInterface $transport;

    private array $options;

    private array $recaptcha;

    private bool $captchaEnabled;

    public function __construct(
        InputFilter $postInputFilter,
        TransportInterface $transport,
        array $options,
        array $recaptcha,
        bool $captchaEnabled
    ) {
        $this->postInputFilter = $postInputFilter;
        $this->transport       = $transport;
        $this->options         = $options;
        $this->recaptcha       = $recaptcha;
        $this->captchaEnabled  = $captchaEnabled;
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
