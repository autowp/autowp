<?php

class Application_Form_Moder_Picture_DecreaseResolution extends Project_Form
{
    private $resolutions = array();

    /**
     * Set form state from options array
     *
     * @param  array $options
     * @return Zend_Form
     */
    public function setResolutions(array $resolutions)
    {
        $this->resolutions = $resolutions;

        return $this;
    }

    public function init()
    {
        $this->addAttribs(array(
            'onsubmit' => "return window.confirm('ВЫ УВЕРЕНЫ? НАЗАД ПУТИ НЕ БУДЕТ')"
        ));

        $options = array(
            '' => '--'
        );
        foreach ($this->resolutions as $resolution)
        {
            $id = $resolution['width'] . 'x' . $resolution['height'];
            $size = ($resolution['width'] * $resolution['height'] / 1024 / 1024);
            $size = Zend_Locale_Format::toFloat($size, array(
                'precision' => 1
            ));
            $label = $id . ' (' . $size . ' MPx)';
            $options[$id] = $label;
        }

        $this->addElements(array (
            array('select', 'resolution', array(
                'label'         => 'Разрешение',
                'required'      => true,
                'multioptions'  => $options,
                'class'         => 'form-control'
            )),
            array('submit', 'decrease-resultion-send', array (
                'required' => false,
                'ignore'   => true,
                'label'    => 'Уменьшить',
                'class'    => 'btn btn-warning'
            ))
        ));
    }
}