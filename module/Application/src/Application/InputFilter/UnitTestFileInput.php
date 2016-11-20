<?php

namespace Application\InputFilter;

class UnitTestFileInput extends \Zend\InputFilter\FileInput
{
    /**
     * @var bool
     */
    protected $autoPrependUploadValidator = false;
}