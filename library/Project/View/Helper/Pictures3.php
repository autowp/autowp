<?php

class Project_View_Helper_Pictures3 extends Zend_View_Helper_Abstract
{
    /**
     * @var Picture_View
     */
    protected $_pictureViewTable = null;

    /**
     * @return Picture_View
     */
    protected function getPictureViewTable()
    {
        return $this->_pictureViewTable
            ? $this->_pictureViewTable
            : $this->_pictureViewTable = new Picture_View();
    }

    public function behaviour(Pictures_Row $picture)
    {
        $view = $this->view;

        $isModer = isset($view->user) && $view->user->role && $view->acl()->inheritsRole($view->user->role, 'pictures-moder');

        return $this->_behaviour($picture, $isModer);
    }

    protected function _behaviour(Pictures_Row $picture, $isModer)
    {
        $view = $this->view;

        /*$url = $view->url(array(
            'controller'    => 'picture',
            'action'        => 'index',
            'picture_id'    => $picture->id
        ), 'picture', true);*/

        $url = $view->pic($picture)->url();

        $resolution = $picture->width.'×'.$picture->height;

        if ($isModer && $picture->cropParametersExists()) {
            $resolution = '<span class="label label-success" style="cursor:help" title="На картинке выделена область автомобиля ('.$picture->crop_width.'×'.$picture->crop_height.')">'.$resolution.'</span>';
        }

        if ($picture->ratio > 0)
            $ratio = $view->float($picture->ratio, array(
                'precision' => 1
            ));
        else
            $ratio = $view->translate('picture-preview/no-ratio');

        if ($picture->active_comments > 0) {
            if ($picture->comments > $picture->active_comments)
                $comments = intval($picture->comments-$picture->active_comments).'<span class="new-comments">+'.intval($picture->active_comments).'</span>';
            else
                $comments = '<span class="new-comments">+'.intval($picture->active_comments).'</span>';
        }
        elseif ($picture->comments > 0)
            $comments = intval($picture->comments);
        else
            $comments = $view->translate('picture-preview/no-comments');

        $pictureViews = $this->getPictureViewTable()->get($picture);
        if ($pictureViews > 1000) {
            $pictureViews = round($pictureViews / 1000) . 'K';
        }

        $behaviour = array(
            array(
                'dt' => $view->escape($view->translate('Resolution')),
                'dd' => $resolution
            ),
            array(
                'dt' => $view->escape($view->translate('Ratio')),
                'dd' => $view->escape($ratio)
            ),
            array(
                'dt' => $view->escape($view->translate('Views')),
                'dd' => $pictureViews
            ),

        );

        if ($isModer) {
            switch ($picture->status) {
                case Pictures::STATUS_ACCEPTED:    $a2 = '<span class="label label-success">принято</span>'; break;
                case Pictures::STATUS_NEW:        $a2 = '<span class="label label-warning">новое</span>'; break;
                case Pictures::STATUS_INBOX:    $a2 = '<span class="label label-warning">входящее</span>'; break;
                case Pictures::STATUS_REMOVED:    $a2 = '<span class="label label-important">удалено</span>'; break;
                case Pictures::STATUS_REMOVING:    $a2 = '<span class="label label-important">удаляется</span>'; break;
            }

            $behaviour[] = array(
                'dt' => 'Статус',
                'dd' => $a2
            );
        }

        $behaviour[] = array(
            'dt' => $view->htmlA($url. '#comments', $view->translate('Comments count')),
            'dd' => $comments
        );

        $behaviourHtml = array();
        foreach ($behaviour as $row) {
            $behaviourHtml[] = '<dt>' . $row['dt'] . '</dt><dd>' . $row['dd'] . '</dd>';
        }

        return '<dl class="picture-behaviour">' . implode($behaviourHtml) . '</dl>';
    }

    public function picture(Pictures_Row $picture)
    {
        $view = $this->view;

        $isModer = isset($view->user) && $view->user->role && $view->acl()->inheritsRole($view->user->role, 'pictures-moder');

        $showModerVotes = $isModer;

        $caption = $picture->getCaption(array(
            'language' => $view->language()->get()
        ));
        $escCaption = $view->escape($caption);

        $url = $view->pic($picture)->url();

        $image = $this->view->image($picture, 'file_name', array(
            'format'  => 6,
            'alt'     => $caption,
            'title'   => $caption,
            'shuffle' => true
        ));

        if ($isModer && $picture->name) {
            $escCaption = '<span style="color:darkgreen" title="Картинке задано особое название">'.$escCaption.'</span>';
        }


        $moderVotes = '';
        if ($showModerVotes) {
            foreach ($picture->findPictures_Moder_Votes() as $vote) {
                if ($user = $vote->findParentUsers()) {
                    $moderVotes .= $user->getShortHtml() . ' ';
                }
                $moderVotes .= '<span style="color:' . ($vote->vote ? 'green' : 'red') . '">&#xa0;' . $this->view->escape($vote->reason) . '</span><br />';
            }
            if ($moderVotes) {
                $moderVotes = '<div class="moder-votes">'.$moderVotes.'</div>';
            }
        }

        return '<div class="picture-preview">' .
                    '<h3>' . $view->htmlA($url, $escCaption, false) . '</h3>' .
                    $view->htmlA($url, $image->exists() ? $image : '', false) .
                    $this->_behaviour($picture, $isModer) .
                    $moderVotes .
                '</div>';
    }

    public function pictures3(Zend_Db_Table_Rowset $list = null, $width = 3)
    {
        if (!$list) {
            return $this;
        }

        $view = $this->view;

        $isModer = isset($view->user) && $view->user->role && $view->acl()->inheritsRole($view->user->role, 'pictures-moder');

        $showModerVotes = $isModer;

        $items = array();

        $colClass = 'col-lg-' . (12 / $width);

        foreach ($list as $picture) {

            $caption = $picture->getCaption(array(
                'language' => $view->language()->get()
            ));
            $escCaption = $view->escape($caption);

            $url = $view->pic($picture)->url();

            $image = $this->view->image($picture, 'file_name', array(
                'format'  => 6,
                'alt'     => $caption,
                'title'   => $caption,
                'shuffle' => true
            ));


            if ($isModer && $picture->name)
                $escCaption = '<span style="color:darkgreen" title="Картинке задано особое название">'.$escCaption.'</span>';


            $moderVotes = '';
            if ($showModerVotes) {
                foreach ($picture->findPictures_Moder_Votes() as $vote) {
                    if ($user = $vote->findParentUsers()) {
                        $moderVotes .= $user->getShortHtml() . ' ';
                    }
                    $moderVotes .= '<span style="color:' . ($vote->vote ? 'green' : 'red') . '">&#xa0;' . $this->view->escape($vote->reason) . '</span><br />';
                }
                if ($moderVotes) {
                    $moderVotes = '<div class="moder-votes">'.$moderVotes.'</div>';
                }
            }

            $items[] = '<div class="picture-preview '.$colClass.'">' .
                           '<div class="thumbnail">' .
                               $view->htmlA($url, $image->exists() ? $image : '', false) .
                               '<div class="caption">' .
                                   '<p>' . $view->htmlA($url, $escCaption, false) . '</p>' .
                                   $this->_behaviour($picture, $isModer) .
                               '</div>' .
                               $moderVotes .
                           '</div>' .
                       '</div>';
        }
        $result = array();

        while ($current = array_splice($items, 0, $width)) {
            $result[] = '<div class="row">' . implode($current) . '</div>';
        }

        return implode($result);
    }
}