<?php

class Application_Form_Moder_Picture_Unvote extends Project_Form
{
    public function init()
    {
        $this->addElements(array (
            array ('submit', 'send', array (
                'required' => false,
                'ignore'   => true,
                'label'    => 'Отменить мою заявку',
            ))
        ));
    }
}