<?php

class Moder_IndexController extends Zend_Controller_Action
{
    public function moderMenuAction()
    {
        if (!$this->_helper->user()->inheritsRole('moder') ) {
            return $this->_forward('forbidden', 'error', 'default');
        }

        $items = array();

        if ($this->_helper->user()->inheritsRole('moder')) {

            $urlHelper = $this->_helper->url;

            $pTable = $this->_helper->catalogue()->getPictureTable();
            $inboxCount = $pTable->getAdapter()->fetchOne(
                $pTable->getAdapter()->select()
                    ->from($pTable->info('name'), 'count(1)')
                    ->where('status = ?', Picture::STATUS_INBOX)
            );

            $items[] = array(
                'href'  => '/moder/pictures/index/order/1/status/inbox',
                'label' => 'Инбокс',
                'count' => $inboxCount,
                'icon'  => 'fa fa-th'
            );

            $cmTable = new Comment_Message();
            $attentionCount = $cmTable->getAdapter()->fetchOne(
                $cmTable->getAdapter()->select()
                    ->from($cmTable->info('name'), 'count(1)')
                    ->where('moderator_attention = ?', Comment_Message::MODERATOR_ATTENTION_REQUIRED)
            );

            $items[] = array(
                'href'  => $urlHelper->url(array(
                    'module'              => 'moder',
                    'controller'          => 'comments',
                    'action'              => 'index',
                    'moderator_attention' => Comment_Message::MODERATOR_ATTENTION_REQUIRED
                ), 'default', true),
                'label' => 'Комментарии',
                'count' => $attentionCount,
                'icon'  => 'fa fa-comment'
            );

            if ($this->_helper->user()->inheritsRole('pages-moder')) {

                $items[] = array(
                    'href'  => $urlHelper->url(array(
                        'module'     => 'moder',
                        'controller' => 'pages',
                        'action'     => 'index'
                    ), 'default', true),
                    'label' => 'Страницы сайта',
                    'icon'  => 'fa fa-book'
                );
            }

            $items[] = array(
                'href'  => $urlHelper->url(array(
                    'module'     => 'moder',
                    'controller' => 'cars',
                    'action'     => 'index'
                ), 'default', true),
                'label' => 'Автомобили',
                'icon'  => 'fa fa-car'
            );
        }

        $this->view->items = $items;

        $this->_helper->viewRenderer->setResponseSegment('moderatorMenu');
    }
}