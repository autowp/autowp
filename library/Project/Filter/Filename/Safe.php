<?php

use Autowp\Filter\Filename\Safe;

class Project_Filter_Filename_Safe implements Zend_Filter_Interface
{
    public function filter($value)
    {
        $filter = new Safe();
        return $filter->filter($value);
    }
}