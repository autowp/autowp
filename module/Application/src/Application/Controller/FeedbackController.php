<?php

namespace Application\Controller;

use Zend\Mvc\Controller\AbstractActionController;

use Zend\Mail;

class FeedbackController extends AbstractActionController
{
    private $form;

    private $transport;

    /**
     * @var array
     */
    private $options;

    public function __construct($form, $transport, $options)
    {
        $this->form = $form;
        $this->transport = $transport;
        $this->options = $options;
    }

    public function indexAction()
    {
        if ($this->getRequest()->isPost()) {
            $this->form->setData($this->params()->fromPost());

            if ($this->form->isValid()) {

                $values = $this->form->getData();

                $message =  'Имя: ' . $values['name'] . PHP_EOL .
                            'E-mail: ' . $values['email'] . PHP_EOL .
                            'Сообщение: ' . PHP_EOL . $values['message'] . PHP_EOL;

                $mail = new Mail\Message();
                $mail
                    ->setEncoding('utf-8')
                    ->setBody($message)
                    ->setFrom($this->options['from'], $this->options['fromname'])
                    ->addTo($this->options['to'])
                    ->setSubject($this->options['subject']);

                if ($values['email']) {
                    $mail->setReplyTo($values['email'], $values['name']);
                }

                $this->transport->send($mail);

                return $this->forward()->dispatch(self::class, [
                    'action' => 'sent'
                ]);
            }
        }

        return [
            'form' => $this->form
        ];
    }

    public function sentAction()
    {
    }
}