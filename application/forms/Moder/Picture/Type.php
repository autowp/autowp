<?php

class Application_Form_Moder_Picture_Type extends Project_Form
{
    public function init()
    {
        $this->addElements(array(
            array('select', 'type', array(
                'label'        => 'Тип',
                'filters'      => array('Int'),
                'class'        => 'form-control',
                'multioptions' => array(
                    Picture::UNSORTED_TYPE_ID => 'Несортировано',
                    Picture::CAR_TYPE_ID      => 'Автомобиль',
                    Picture::LOGO_TYPE_ID     => 'Логотип',
                    Picture::MIXED_TYPE_ID    => 'Разное',
                    Picture::ENGINE_TYPE_ID   => 'Двигатель',
                    Picture::INTERIOR_TYPE_ID => 'Интерьер'
                ),
            )),
            array('submit', 'send', array(
                'required'     => false,
                'ignore'       => true,
                'label'        => 'Ok',
                'class'        => 'btn btn-primary',
            ))
        ));
    }
}