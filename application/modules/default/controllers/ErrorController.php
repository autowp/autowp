<?php

class ErrorController extends Zend_Controller_Action
{
    public function errorAction()
    {
        $errors = $this->_getParam('error_handler');

        $this->view->exceptions = array();

        switch ($errors->type)
        {
            case Zend_Controller_Plugin_ErrorHandler::EXCEPTION_NO_CONTROLLER:
            case Zend_Controller_Plugin_ErrorHandler::EXCEPTION_NO_ACTION:
                // 404 error -- controller or action not found
                return $this->_forward('notfound');
                break;
            default:
                if ($errors['exception'] instanceof NotFoundException)
                    return $this->_forward('notfound');

                $this->view->exceptions = $this->getResponse()->getException();
                break;
        }

        $message =  '';
        foreach ($this->getResponse()->getException() as $e) {
            $message .= $e->getMessage() . PHP_EOL .
                        'in file '.$e->getFile().' at line '.$e->getLine(). PHP_EOL .
                        PHP_EOL .
                        $this->getRequest()->getServer('REQUEST_URI') .
                        PHP_EOL .
                        $e->getTraceAsString() . PHP_EOL . PHP_EOL;
        }
    }

    public function notfoundAction()
    {
        $this->getResponse()->setRawHeader('HTTP/1.1 404 Not Found');
    }

    public function forbiddenAction()
    {
        $this->getResponse()->setRawHeader('HTTP/1.1 403 Forbidden');
    }
}