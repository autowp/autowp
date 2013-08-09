<?php

class Project_Filter_IntOrNull implements Zend_Filter_Interface
{
    /**
     * Defined by Zend_Filter_Interface
     *
     * Returns (int) $value
     *
     * @param  string $value
     * @return integer
     */
    public function filter($value)
    {
        return strlen($value) ? (int)$value : null;
    }
}