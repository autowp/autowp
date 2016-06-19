<?php

class Project_View_Helper_Pictures extends Zend_View_Helper_Abstract
{
    const
        SCHEME_631 = '631',
        SCHEME_422 = '422';

    /**
     * @var Picture_View
     */
    protected $_pictureViewTable = null;

    /**
     * @var Comment_Topic
     */
    protected $_commentTopicTable = null;

    protected $_moderVoteTable = null;

    /**
     * @return Pictures_Moder_Votes
     */
    protected function getModerVoteTable()
    {
        return $this->_moderVoteTable
        ? $this->_moderVoteTable
        : $this->_moderVoteTable = new Pictures_Moder_Votes();
    }

    /**
     * @return Picture_View
     */
    protected function getPictureViewTable()
    {
        return $this->_pictureViewTable
            ? $this->_pictureViewTable
            : $this->_pictureViewTable = new Picture_View();
    }

    /**
     * @return Comment_Topic
     */
    protected function getCommentTopicTable()
    {
        return $this->_commentTopicTable
            ? $this->_commentTopicTable
            : $this->_commentTopicTable = new Comment_Topic();
    }

    protected function _isPictureModer()
    {
        $view = $this->view;

        $isModer = false;
        if ($view->user()->logedIn()) {
            $role = $view->user()->get()->role;
            $isModer = $role && $view->acl()->inheritsRole($role, 'pictures-moder');
        }

        return $isModer;
    }

    public function behaviour(Pictures_Row $picture)
    {
        return $this->_behaviour($picture, $this->_isPictureModer());
    }

    /**
     * @param array $picture
     * @param bool $isModer
     * @param bool $logedIn
     * @return string
     */
    private function _renderBehaviour(array $picture, $isModer)
    {
        return $this->view->partial('picture-behaviour.phtml', array(
            'isModer'        => $isModer,
            'resolution'     => $picture['width'].'×'.$picture['height'],
            'status'         => $picture['status'],
            'cropped'        => $picture['cropped'],
            'cropResolution' => $picture['crop_width'].'×'.$picture['crop_height'],
            'views'          => $picture['views'],
            'msgCount'       => $picture['msgCount'],
            'newMsgCount'    => $picture['newMsgCount'],
            'url'            => $picture['url']
        ));

        /*$view = $this->view;

        $url = $picture['url'];

        $resolution = $picture['width'].'×'.$picture['height'];

        if ($isModer && $picture['cropped']) {
            $cropResolution = $picture['crop_width'].'×'.$picture['crop_height'];
            $resolution = '<span class="label label-success" style="cursor:help" title="На картинке выделена область автомобиля ('.$cropResolution.')">'.$resolution.'</span>';
        }

        $msgCount = $picture['msgCount'];
        $newMsgCount = $picture['newMsgCount'];

        if ($newMsgCount > 0) {
            if ($msgCount > $newMsgCount) {
                $comments = ($msgCount-$newMsgCount).'<span class="new-comments">+'.$newMsgCount.'</span>';
            } else {
                $comments = '<span class="new-comments">+'.$newMsgCount.'</span>';
            }
        } elseif ($msgCount > 0) {
            $comments = $msgCount;
        } else {
            $comments = $view->translate('picture-preview/no-comments');
        }

        $views = $picture['views'];
        if ($views > 1000) {
            $views = round($views / 1000) . 'K';
        }

        $behaviour = array(
            array(
                'dt' => $view->escape($view->translate('Resolution')),
                'dd' => $resolution
            ),
            array(
                'dt' => $view->escape($view->translate('Views')),
                'dd' => $views
            ),

        );

        if ($isModer) {
            switch ($picture['status']) {
                case Picture::STATUS_ACCEPTED: $a2 = '<span class="label label-success">принято</span>'; break;
                case Picture::STATUS_NEW:      $a2 = '<span class="label label-warning">новое</span>'; break;
                case Picture::STATUS_INBOX:    $a2 = '<span class="label label-warning">входящее</span>'; break;
                case Picture::STATUS_REMOVED:  $a2 = '<span class="label label-danger">удалено</span>'; break;
                case Picture::STATUS_REMOVING: $a2 = '<span class="label label-danger">удаляется</span>'; break;
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

        return '<dl class="picture-behaviour">' . implode($behaviourHtml) . '</dl>';*/
    }

    protected function _behaviour(Pictures_Row $picture, $isModer)
    {
        $ctTable = $this->getCommentTopicTable();
        if ($this->view->user()->logedIn()) {
            $commentsStat = $ctTable->getTopicStatForUser(
                Comment_Message::PICTURES_TYPE_ID,
                $picture->id,
                $this->view->user()->get()->id
            );
            $msgCount = $commentsStat['messages'];
            $newMsgCount = $commentsStat['newMessages'];
        } else {
            $commentsStat = $ctTable->getTopicStat(
                Comment_Message::PICTURES_TYPE_ID,
                $picture->id
            );
            $msgCount = $commentsStat['messages'];
            $newMsgCount = 0;
        }

        $data = array(
            'url'         => $this->view->pic($picture)->url(),
            'cropped'     => $picture->cropParametersExists(),
            'width'       => $picture['width'],
            'height'      => $picture['height'],
            'crop_width'  => $picture->crop_width,
            'crop_height' => $picture->crop_height,
            'msgCount'    => $msgCount,
            'newMsgCount' => $newMsgCount,
            'views'       => $this->getPictureViewTable()->get($picture),
            'status'      => $picture->status,
        );

        return $this->_renderBehaviour($data, $isModer);
    }

    protected function _getModerVote(Pictures_Row $picture)
    {
        $moderVoteTable = $this->getModerVoteTable();
        $db = $moderVoteTable->getAdapter();

        $row = $db->fetchRow(
            $db->select()
                ->from($moderVoteTable->info('name'), array(
                    'vote'  => new Zend_Db_Expr('sum(if(vote, 1, -1))'),
                    'count' => 'count(1)'
                ))
                ->where('picture_id = ?', $picture->id)
        );

        if ($row['count'] > 0) {
            return (int)$row['vote'];
        } else {
            return null;
        }

        return $moderVote;
    }

    public function picture(Pictures_Row $picture)
    {
        $view = $this->view;

        $isModer = $this->_isPictureModer();

        $caption = $picture->getCaption(array(
            'language' => $view->language()->get()
        ));
        $escCaption = $view->escape($caption);

        $url = $view->pic($picture)->url();

        $imageHtml = $this->view->img($picture->getFormatRequest(), array(
            'format'  => 'picture-thumb',
            'alt'     => $caption,
            'title'   => $caption,
            'shuffle' => true
        ));

        if ($isModer && $picture->name) {
            $escCaption = '<span style="color:darkgreen" title="Картинке задано особое название">'.$escCaption.'</span>';
        }

        $moderVote = $this->_getModerVote($picture);

        $classes = array('picture-preview');
        if ($moderVote !== null) {
            if ($moderVote > 0) {
                $classes[] = 'vote-accept';
            } elseif ($moderVote < 0) {
                $classes[] = 'vote-remove';
            } else {
                $classes[] = 'vote-neutral';
            }
        }

        return '<div class="'.implode(' ', $classes).'">' .
                    '<div class="thumbnail">' .
                        $view->htmlA($url, $imageHtml, false) .
                        '<p>' . $view->htmlA($url, $escCaption, false) . '</p>' .
                        $this->_behaviour($picture, $isModer) .
                    '</div>' .
                '</div>';
    }

    protected function _splitByScheme(array $items, $scheme)
    {
        switch ($scheme) {
            case self::SCHEME_422:
                return $this->_splitBy3Level($items, 4, 2);
                break;

            case self::SCHEME_631:
                return $this->_splitBy3Level($items, 6, 3);
                break;

            default:
                throw new Exception("Unknown scheme '$scheme'");
        }
    }

    protected function _splitBy3Level(array $items, $perLine, $perSuperCol)
    {
        $html = array();

        $itemsCount = count($items);
        $lineCount = ceil($itemsCount / $perLine);
        $superColCount = $perLine / $perSuperCol;
        for ($line=0; $line<$lineCount; $line++) {
            $html[] = '<div class="row">';
            for ($superCol=0; $superCol<$superColCount; $superCol++) {
                $html[] = '<div class="col-lg-6 col-md-6 col-sm-12 col-12">';
                $html[] = '<div class="row">';
                for ($col=0; $col<$perSuperCol; $col++) {
                    $index = $line * $perLine + $superCol * $perSuperCol + $col;
                    if ($index < $itemsCount) {
                        $html[] = $items[$index];
                    }
                }
                $html[] = '</div>';
                $html[] = '</div>';
            }
            $html[] = '</div>';
        }
        return implode($html);
    }
}