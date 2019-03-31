<?php

namespace Application\InputFilter;

use Zend\InputFilter\FileInput;

class UnitTestFileInput extends FileInput
{
    /**
     * @var bool
     */
    protected $autoPrependUploadValidator = false;
}
