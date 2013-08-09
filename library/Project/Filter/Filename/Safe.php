<?php
/**
 * @see Zend_Filter_Interface
 */
require_once 'Zend/Filter/Interface.php';

class Project_Filter_Filename_Safe implements Zend_Filter_Interface
{
    protected $_replace = array (
        "№" => "N",
        " " => '_',
        '"' => '_',
        "/" => '_',
        '*' => '_',
        '`' => '_',
        '#' => '_',
        '&' => '_',
        '\\' => '_',
        '!' => '_',
        '@' => '_',
        '$' => 's',
        '%' => '_',
        '^' => '_',
        '=' => '-',
        '|' => '_',
        '?' => '_',
        '„' => ',',
        '“' => '_',
        '”' => '_',
        '{' => '(',
        '}' => ')',
        ':' => '-',
        ';' => '_',
        '-' => '-',
    );


    /**
     * Defined by Zend_Filter_Interface
     *
     * @param  string $value
     * @return string
     */
    public function filter($value)
    {
        $transliteration = new Project_Filter_Transliteration();

        $value = $transliteration->filter($value);
        $value = mb_strtolower($value);

        $value = strtr($value, $this->_replace);

        $value = trim($value, '_-');

        $value = preg_replace('|[^A-Za-z0-9.(){}_-]|isu', '_', $value);

        do {
            $oldLength = strlen($value);
            $value = str_replace('__', '_', $value);
        } while ($oldLength != strlen($value));

        if (strlen($value) == 0) {
            $value = '_';
        }

        return $value;
    }

}
