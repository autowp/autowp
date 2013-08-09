<?php


class Project_Validate_GreaterThanOrEquals extends Zend_Validate_Abstract
{

    const NOT_GREATER_OR_EQUALS = 'notGreaterThanOrEquals';

    /**
     * @var array
     */
    protected $_messageTemplates = array(
        self::NOT_GREATER_OR_EQUALS => "'%value%' is not greater than '%min%' or equals"
    );

    /**
     * @var array
     */
    protected $_messageVariables = array(
        'min' => '_min'
    );

    /**
     * Minimum value
     *
     * @var mixed
     */
    protected $_min;

    /**
     * Sets validator options
     *
     * @param  mixed $min
     * @return void
     */
    public function __construct($min)
    {
        $this->setMin($min);
    }

    /**
     * Returns the min option
     *
     * @return mixed
     */
    public function getMin()
    {
        return $this->_min;
    }

    /**
     * Sets the min option
     *
     * @param  mixed $min
     * @return Zend_Validate_GreaterThan Provides a fluent interface
     */
    public function setMin($min)
    {
        $this->_min = $min;
        return $this;
    }

    /**
     * Defined by Zend_Validate_Interface
     *
     * Returns true if and only if $value is greater than min option
     *
     * @param  mixed $value
     * @return boolean
     */
    public function isValid($value)
    {
        $this->_setValue($value);

        if ($this->_min > $value) {
            $this->_error();
            return false;
        }
        return true;
    }

}
