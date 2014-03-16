<?php
class Project_Form_Decorator_Unit extends Zend_Form_Decorator_Abstract
{
    protected $_unit;

    public function setUnit($unit)
    {
        $this->_unit = $unit;
        return $this;
    }

    public function getUnit()
    {
        if (null === $this->_unit) {
            if (null !== ($unit = $this->getOption('unit'))) {
                $this->setUnit($unit);
                $this->removeOption('unit');
            }
        }

        return $this->_unit;
    }

    /**
     * Render
     *
     * If no callback registered, returns callback. Otherwise, gets return
     * value of callback and either appends, prepends, or replaces passed in
     * content.
     *
     * @param  string $content
     * @return string
     */
    public function render($content)
    {
        $unit = $this->getUnit();
        if (null === $unit) {
            return $content;
        }

        $placement = $this->getPlacement();
        $separator = $this->getSeparator();

        $view = $this->getElement()->getView();

        $response = '<span class="input-group-addon" title="' . $view->escape($unit->name) . '" class="unit">' .
                        $view->escape($unit->abbr) .
                    '</span>';
        //call_user_func($callback, $content, $this->getElement(), $this->getOptions());

        switch ($placement) {
            case self::APPEND:
                return '<div class="input-group">' . $content . $separator . $response . '</div>';
            case self::PREPEND:
                return '<div class="input-group">' . $response . $separator . $content . '</div>';
            default:
                // replace content
                return $response;
        }
    }
}
