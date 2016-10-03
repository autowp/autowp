<?php

namespace Application\View\Helper;

use Zend\View\Helper\AbstractHelper;

use Application\Model\DbTable\Comment\Message as CommentMessage;
use Application\Model\DbTable\Comment\Topic as CommentTopic;
use Application\Model\DbTable\Picture\ModerVote as PictureModerVote;
use Application\Model\DbTable\Picture\Row as PictureRow;
use Application\Model\DbTable\Picture\View as PictureView;

use Zend_Db_Expr;

class Pictures extends AbstractHelper
{
    const
        SCHEME_631 = '631',
        SCHEME_422 = '422';

    /**
     * @var PictureView
     */
    private $pictureViewTable = null;

    /**
     * @var CommentTopic
     */
    private $commentTopicTable = null;

    /**
     * @var PictureModerVote
     */
    private $moderVoteTable = null;

    /**
     * @return PictureModerVote
     */
    private function getModerVoteTable()
    {
        return $this->moderVoteTable
            ? $this->moderVoteTable
            : $this->moderVoteTable = new PictureModerVote();
    }

    /**
     * @return PictureView
     */
    private function getPictureViewTable()
    {
        return $this->pictureViewTable
            ? $this->pictureViewTable
            : $this->pictureViewTable = new PicturView();
    }

    /**
     * @return CommentTopic
     */
    private function getCommentTopicTable()
    {
        return $this->commentTopicTable
            ? $this->commentTopicTable
            : $this->commentTopicTable = new CommentTopic();
    }

    private function isPictureModer()
    {
        return $this->view->user()->inheritsRole('pictures-moder');
    }


    public function behaviour(PictureRow $picture)
    {
        return $this->_behaviour($picture, $this->isPictureModer());
    }

    /**
     * @param array $picture
     * @param bool $isModer
     * @param bool $logedIn
     * @return string
     */
    private function renderBehaviour(array $picture, $isModer)
    {
        return $this->view->partial('application/picture-behaviour', [
            'isModer'        => $isModer,
            'resolution'     => $picture['width'].'×'.$picture['height'],
            'status'         => $picture['status'],
            'cropped'        => $picture['cropped'],
            'cropResolution' => $picture['crop_width'].'×'.$picture['crop_height'],
            'views'          => $picture['views'],
            'msgCount'       => $picture['msgCount'],
            'newMsgCount'    => $picture['newMsgCount'],
            'url'            => $picture['url']
        ]);
    }


    private function _behaviour(PictureRow $picture, $isModer)
    {
        $ctTable = $this->getCommentTopicTable();
        if ($this->view->user()->logedIn()) {
            $commentsStat = $ctTable->getTopicStatForUser(
                CommentMessage::PICTURES_TYPE_ID,
                $picture->id,
                $this->view->user()->get()->id
            );
            $msgCount = $commentsStat['messages'];
            $newMsgCount = $commentsStat['newMessages'];
        } else {
            $commentsStat = $ctTable->getTopicStat(
                CommentMessage::PICTURES_TYPE_ID,
                $picture->id
            );
            $msgCount = $commentsStat['messages'];
            $newMsgCount = 0;
        }

        $data = [
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
        ];

        return $this->renderBehaviour($data, $isModer);
    }


    private function getModerVote(PictureRow $picture)
    {
        $moderVoteTable = $this->getModerVoteTable();
        $db = $moderVoteTable->getAdapter();

        $row = $db->fetchRow(
            $db->select()
                ->from($moderVoteTable->info('name'), [
                    'vote'  => new Zend_Db_Expr('sum(if(vote, 1, -1))'),
                    'count' => 'count(1)'
                ])
                ->where('picture_id = ?', $picture->id)
        );

        if ($row['count'] > 0) {
            return (int)$row['vote'];
        } else {
            return null;
        }

        return $moderVote;
    }


    public function picture(PictureRow $picture)
    {
        $view = $this->view;

        $isModer = $this->isPictureModer();

        $caption = $view->pic()->name($picture, $this->view->language());
        $escCaption = $view->escape($caption);

        $url = $view->pic($picture)->url();

        $imageHtml = $this->view->img($picture->getFormatRequest(), [
            'format'  => 'picture-thumb',
            'alt'     => $caption,
            'title'   => $caption,
            'shuffle' => true
        ]);

        if ($isModer && $picture->name) {
            $escCaption = '<span style="color:darkgreen" title="'.$this->view->escapeHtmlAttr($this->view->translate('picture-preview/special-name')).'">' .
                    $escCaption .
                '</span>';
        }

        $moderVote = $this->getModerVote($picture);

        $classes = ['picture-preview'];
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


    private function splitByScheme(array $items, $scheme)
    {
        switch ($scheme) {
            case self::SCHEME_422:
                return $this->splitBy3Level($items, 4, 2);
                break;

            case self::SCHEME_631:
                return $this->splitBy3Level($items, 6, 3);
                break;

            default:
                throw new Exception("Unknown scheme '$scheme'");
        }
    }


    private function splitBy3Level(array $items, $perLine, $perSuperCol)
    {
        $html = [];

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