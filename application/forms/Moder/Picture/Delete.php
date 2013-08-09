<?php

class Application_Form_Moder_Picture_Delete extends Project_Form
{
    public function init()
    {
        $this->addElements(array (
            array ('submit', 'send', array (
                'required' => false,
                'ignore'   => true,
                'class'    => 'btn btn-danger',
                'label'    => 'Удалить',
            ))
        ));
    }
}