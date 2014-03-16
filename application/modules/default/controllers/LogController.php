<?php
class LogController extends Zend_Controller_Action
{
    const EVENTS_PER_PAGE = 40;

    public function indexAction()
    {
        if (!$this->_helper->user()->inheritsRole('moder') ) {
            return $this->_forward('forbidden', 'error');
        }

        $logTable = new Log_Events();

        $select = $logTable->select(true)
            ->order('add_datetime DESC');

        $articleId = (int)$this->_getParam('article_id');
        if ($articleId) {
            $select->join('log_events_articles', 'log_events.id=log_events_articles.log_event_id', null)
                   ->where('log_events_articles.article_id = ?', $articleId);
        }

        $brandId = (int)$this->_getParam('brand_id');
        if ($brandId) {
            $select->join('log_events_brands', 'log_events.id=log_events_brands.log_event_id', null)
                   ->where('log_events_brands.brand_id = ?', $brandId);
        }

        $carId = (int)$this->_getParam('car_id');
        if ($carId) {
            $select->join('log_events_cars', 'log_events.id=log_events_cars.log_event_id', null)
                   ->where('log_events_cars.car_id = ?', $carId);
        }

        $engineId = (int)$this->_getParam('engine_id');
        if ($engineId) {
            $select->join('log_events_engines', 'log_events.id=log_events_engines.log_event_id', null)
                   ->where('log_events_engines.engine_id = ?', $engineId);
        }

        $pictureId = (int)$this->_getParam('picture_id');
        if ($pictureId) {
            $select->join('log_events_pictures', 'log_events.id=log_events_pictures.log_event_id', null)
                   ->where('log_events_pictures.picture_id = ?', $pictureId);
        }

        $groupId = (int)$this->_getParam('twins_group_id');
        if ($groupId) {
            $select->join('log_events_twins_groups', 'log_events.id=log_events_twins_groups.log_event_id', null)
                   ->where('log_events_twins_groups.twins_group_id = ?', $groupId);
        }

        $userId = (int)$this->_getParam('user_id');
        if ($userId) {
            $select->where('log_events.user_id = ?', $userId);
        }

        $paginator = Zend_Paginator::factory($select)
            ->setItemCountPerPage(self::EVENTS_PER_PAGE)
            ->setCurrentPageNumber($this->_getParam('page'));

        $events = array();
        foreach ($paginator->getCurrentItems() as $event) {
            $events[] = array(
                'user' => $event->findParentUsers(),
                'date' => $event->getDate('add_datetime'),
                'desc' => $event->description
            );
        }

        $this->view->assign(array(
            'paginator' => $paginator,
            'events'    => $events
        ));
    }


}