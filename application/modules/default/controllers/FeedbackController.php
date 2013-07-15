<?php
class FeedbackController extends My_Controller_Action
{
    public function indexAction()
    {
        $this->initPage(89);

        $form = new Application_Form_Feedback();

        $request = $this->getRequest();

        if ($request->isPost() && $form->isValid($request->getPost())) {
            $values = $form->getValues();

            $bootstrap = $this->getInvokeArg('bootstrap');
            $options = $bootstrap->getOptions();
            if (!isset($options['feedback'])) {
                throw new Exception("Feedback options not found");
            }
            $feedbackOptions = $options['feedback'];

            $message =  'Имя: ' . $values['name'] . PHP_EOL .
                        'E-mail: ' . $values['email'] . PHP_EOL .
                        'Сообщение: ' . PHP_EOL . $values['message'] . PHP_EOL;

            $mail = new Zend_Mail('utf-8');
            $mail->setBodyText($message)
                ->setFrom($feedbackOptions['from'], $feedbackOptions['fromname'])
                ->addTo($feedbackOptions['to'])
                ->setSubject($feedbackOptions['subject']);

            if ($values['email']) {
                $mail->setReplyTo($values['email'], $values['name']);
            }

            $mail->send();

            $this->_forward('sent');
        }

        $this->view->form = $form;
    }

    public function sentAction()
    {
        $this->initPage(93);
    }
}