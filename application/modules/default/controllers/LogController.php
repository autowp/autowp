<?php
class LogController extends My_Controller_Action
{
    const EVENTS_PER_PAGE = 40;

    public function indexAction()
    {
        if (!$this->_helper->user()->inheritsRole('moder') ) {
            return $this->_forward('forbidden', 'error');
        }

        $this->initPage(75);

        $logTable = new Log_Events();

        $select = $logTable->select(true)
            ->order('add_datetime DESC');

        if ($this->_getParam('article_id')) {
            $select->join('log_events_articles', 'log_events.id=log_events_articles.log_event_id', null)
                   ->where('log_events_articles.article_id = ?', $this->_getParam('article_id'));
        }

        if ($this->_getParam('brand_id')) {
            $select->join('log_events_brands', 'log_events.id=log_events_brands.log_event_id', null)
                   ->where('log_events_brands.brand_id = ?', $this->_getParam('brand_id'));
        }

        if ($this->_getParam('car_id')) {
            $select->join('log_events_cars', 'log_events.id=log_events_cars.log_event_id', null)
                   ->where('log_events_cars.car_id = ?', $this->_getParam('car_id'));
        }

        if ($this->_getParam('engine_id')) {
            $select->join('log_events_engines', 'log_events.id=log_events_engines.log_event_id', null)
                   ->where('log_events_engines.engine_id = ?', $this->_getParam('engine_id'));
        }

        if ($this->_getParam('model_id')) {
            $select->join('log_events_models', 'log_events.id=log_events_models.log_event_id', null)
                   ->where('log_events_models.model_id = ?', $this->_getParam('model_id'));
        }

        if ($this->_getParam('picture_id')) {
            $select->join('log_events_pictures', 'log_events.id=log_events_pictures.log_event_id', null)
                   ->where('log_events_pictures.picture_id = ?', $this->_getParam('picture_id'));
        }

        if ($this->_getParam('twins_group_id')) {
            $select->join('log_events_twins_groups', 'log_events.id=log_events_twins_groups.log_event_id', null)
                   ->where('log_events_twins_groups.twins_group_id = ?', $this->_getParam('twins_group_id'));
        }

        if ($this->_getParam('user_id')) {
            $select->where('log_events.user_id = ?', $this->_getParam('user_id'));
        }

        $paginator = Zend_Paginator::factory($select)
            ->setItemCountPerPage(self::EVENTS_PER_PAGE)
            ->setCurrentPageNumber($this->_getParam('page'));

        $this->view->paginator = $paginator;
    }


}