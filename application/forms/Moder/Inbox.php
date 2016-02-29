<?php

class Application_Form_Moder_Inbox extends Project_Form
{
    private $_perspectiveMultioptions = array();

    private $_brandMultioptions = array();

    private $_resolutionMultioptions = array();
    
    /**
     * @var Car_Types
     */
    private $carTypeTable = null;

    public function setPerspectiveMultioptions($options)
    {
        $this->_perspectiveMultioptions = $options;
    }

    public function setBrandMultioptions($options)
    {
        $this->_brandMultioptions = $options;
    }

    public function setResolutionMultioptions($options)
    {
        $this->_resolutionMultioptions = $options;
    }

    public function init()
    {
        parent::init();
        
        $carTypeOptions = $this->getCarTypeOptions();
        
        $carTypeOptions = array('' => '-') + $carTypeOptions;

        $this->setOptions(array(
            'decorators' => array(
                'PrepareElements',
                array('viewScript', array(
                    'viewScript' => 'forms/bootstrap-vertical.phtml'
                )),
                'Form'
            ),

            'method'     => 'post',
            'elements'   => array(
                array('select', 'status', array(
                    'required'     => false,
                    'label'        => 'Статус',
                    'multioptions' => array(
                        ''                       => 'любой',
                        Picture::STATUS_INBOX    => 'инбокс',
                        Picture::STATUS_NEW      => 'немодерированые (old)',
                        Picture::STATUS_ACCEPTED => 'принятый',
                        Picture::STATUS_REMOVING => 'в очереди на удаление',
                        'custom1'                => 'все, кроме удалённых'
                    ),
                    'decorators'   => array('ViewHelper')
                )),
                array('select', 'car_type_id', array(
                    'label'        => 'Тип кузова',
                    'required'     => false,
                    'decorators'   => array('ViewHelper'),
                    'multioptions' => $carTypeOptions,
                )),
                array('select', 'perspective_id', array(
                    'required'     => false,
                    'label'        => 'Ракурс',
                    'multioptions' => $this->_perspectiveMultioptions,
                    'decorators'   => array('ViewHelper')
                )),
                array('select', 'brand_id', array(
                    'required'     => false,
                    'label'        => 'Бренд',
                    'multioptions' => $this->_brandMultioptions,
                    'decorators'   => array('ViewHelper')
                )),
                array('text', 'car_id', array(
                    'required'     => false,
                    'label'        => 'Автомобиль (id)',
                    'decorators'   => array('ViewHelper')
                )),
                array('select', 'type_id', array(
                    'required'     => false,
                    'label'        => 'Тип',
                    'multioptions' => array(
                        ''                        => 'любой',
                        Picture::CAR_TYPE_ID      => 'автомобиль',
                        Picture::LOGO_TYPE_ID     => 'логотип',
                        Picture::MIXED_TYPE_ID    => 'разное',
                        Picture::UNSORTED_TYPE_ID => 'несортировано',
                        Picture::ENGINE_TYPE_ID   => 'двигатель',
                        Picture::FACTORY_TYPE_ID  => 'завод'
                    ),
                    'decorators'   => array('ViewHelper')
                )),
                array('select', 'resolution', array(
                    'required'     => false,
                    'label'        => 'Разрешение',
                    'multioptions' => $this->_resolutionMultioptions,
                    'decorators'   => array('ViewHelper')
                )),
                array('select', 'comments', array(
                    'required'     => false,
                    'label'        => 'Комментарии',
                    'multioptions' => array(
                        ''  => 'не важно',
                        '1' => 'есть',
                        '0' => 'нет',
                    ),
                    'decorators'   => array('ViewHelper')
                )),
                array('text', 'owner_id', array(
                    'required'     => false,
                    'label'        => 'Добавил',
                    'decorators'   => array('ViewHelper'),
                )),
                array('select', 'replace', array(
                    'required'     => false,
                    'label'        => 'Замена',
                    'multioptions' => array(
                        ''  => 'не важно',
                        '1' => 'замена',
                        '0' => 'кроме замен',
                    ),
                    'decorators'   => array('ViewHelper')
                )),
                array('select', 'requests', array(
                    'required'     => false,
                    'label'        => 'Заявки на принятие/удаление',
                    'multioptions' => array(
                        ''  => 'не важно',
                        '0' => 'нет',
                        '1' => 'есть на принятие',
                        '2' => 'есть на удаление',
                        '3' => 'есть любые',
                    ),
                    'decorators'   => array('ViewHelper')
                )),
                array('checkbox', 'special_name', array(
                    'required'     => false,
                    'label'        => 'Только с особым названием',
                    'value'        => '1',
                    'decorators'   => array('ViewHelper')
                )),
                array('checkbox', 'lost', array(
                    'label'        => 'Без привязки',
                    'decorators'   => array(
                        'ViewHelper'
                    )
                )),
                array('select', 'order', array(
                    'required'     => false,
                    'label'        => 'Сортировать по',
                    'value'        => '1',
                    'multioptions' => array(
                        1 => 'Дата добавления (новые)',
                        2 => 'Дата добавления (старые)',
                        3 => 'Разрешение (большие)',
                        4 => 'Разрешение (маленькие)',
                        5 => 'Размер (большие)',
                        6 => 'Размер (маленькие)',
                        7 => 'Комментируемые',
                        8 => 'Просмотры',
                        9 => 'Заявки на принятие/удаление'
                    ),
                    'decorators'   => array('ViewHelper')
                )),
            )
        ));
    }
    
    /**
     * @return Car_Types
     */
    private function getCarTypeTable()
    {
        return $this->carTypeTable
            ? $this->carTypeTable
            : $this->carTypeTable = new Car_Types();
    }
    
    private function getCarTypeOptions($parentId = null)
    {
        if ($parentId) {
            $filter = array(
                'parent_id = ?' => $parentId
            );
        } else {
            $filter = 'parent_id is null';
        }
    
        $translate = Zend_Registry::get('Zend_Translate');
    
        $rows = $this->getCarTypeTable()->fetchAll($filter, 'position');
        $result = array();
        foreach ($rows as $row) {
            $result[$row->id] = $row->name;
    
            foreach ($this->getCarTypeOptions($row->id) as $key => $value) {
                $result[$key] = '...' . $translate->translate($value);
            }
        }
    
        return $result;
    }
}