<?php


namespace Application\Form\View\Helper;

use Zend\Form\View\Helper\FormElement as ZendFormElement;

class FormElement extends ZendFormElement
{
    const DEFAULT_HELPER = 'forminput';

    /**
     * Instance map to view helper
     *
     * @var array
     */
    protected $classMap = [
        'Zend\Form\Element\Button'         => 'formbutton',
        'Zend\Form\Element\Captcha'        => 'formcaptcha',
        'Zend\Form\Element\Csrf'           => 'formhidden',
        'Zend\Form\Element\Collection'     => 'formcollection',
        'Zend\Form\Element\DateTimeSelect' => 'formdatetimeselect',
        'Zend\Form\Element\DateSelect'     => 'formdateselect',
        'Zend\Form\Element\MonthSelect'    => 'formmonthselect',
        'Application\Form\Element\PictureMultiCheckbox' => 'formpicturemulticheckbox'
    ];
}
