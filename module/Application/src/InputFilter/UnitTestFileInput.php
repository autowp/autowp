<?php

namespace Application\InputFilter;

use Laminas\InputFilter\FileInput;

class UnitTestFileInput extends FileInput
{
    /** @var bool */
    protected $autoPrependUploadValidator = false;
}
