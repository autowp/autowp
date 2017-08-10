<?php

namespace Application\View\Helper;

use Zend\View\Helper\AbstractHelper;

use Autowp\Comments;

use Application\Model\DbTable;
use Application\Model\PictureModerVote;
use Application\Model\PictureView;

class Pictures extends AbstractHelper
{
    /**
     * @var PictureView
     */
    private $pictureView;

    /**
     * @var Comments\CommentsService
     */
    private $comments;

    /**
     * @var PictureModerVote
     */
    private $pictureModerVote;

    /**
     * @var DbTable\Picture
     */
    private $pictureTable;

    public function __construct(
        Comments\CommentsService $comments,
        PictureView $pictureView,
        PictureModerVote $pictureModerVote,
        DbTable\Picture $pictureTable
    ) {
        $this->comments = $comments;
        $this->pictureView = $pictureView;
        $this->pictureModerVote = $pictureModerVote;
        $this->pictureTable = $pictureTable;
    }

    private function isPictureModer()
    {
        return $this->view->user()->inheritsRole('pictures-moder');
    }


    public function behaviour($picture)
    {
        return $this->userBehaviour($picture, $this->isPictureModer());
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


    private function userBehaviour($picture, $isModer)
    {
        if ($picture instanceof \Zend_Db_Table_Row_Abstract) {
            $picture = $picture->toArray();
        } elseif ($picture instanceof \ArrayObject) {
            $picture= (array)$picture;
        }

        if ($this->view->user()->logedIn()) {
            $commentsStat = $this->comments->getTopicStatForUser(
                \Application\Comments::PICTURES_TYPE_ID,
                $picture['id'],
                $this->view->user()->get()['id']
            );
            $msgCount = $commentsStat['messages'];
            $newMsgCount = $commentsStat['newMessages'];
        } else {
            $commentsStat = $this->comments->getTopicStat(
                \Application\Comments::PICTURES_TYPE_ID,
                $picture['id']
            );
            $msgCount = $commentsStat['messages'];
            $newMsgCount = 0;
        }

        $data = [
            'url'         => $this->view->pic($picture)->url(),
            'cropped'     => $this->pictureTable->cropParametersExists($picture),
            'width'       => $picture['width'],
            'height'      => $picture['height'],
            'crop_width'  => $picture['crop_width'],
            'crop_height' => $picture['crop_height'],
            'msgCount'    => $msgCount,
            'newMsgCount' => $newMsgCount,
            'views'       => $this->pictureView->get($picture['id']),
            'status'      => $picture['status'],
        ];

        return $this->renderBehaviour($data, $isModer);
    }


    private function getModerVote(int $pictureId)
    {
        $row = $this->pictureModerVote->getVoteCount($pictureId);

        if ($row['count'] > 0) {
            return (int)$row['vote'];
        }

        return null;
    }


    public function picture(\Zend_Db_Table_Row_Abstract $picture)
    {
        $view = $this->view;

        $isModer = $this->isPictureModer();

        $name = $view->pic()->name($picture, $this->view->language());
        $escName = $view->escape($name);

        $url = $view->pic($picture)->url();

        $imageHtml = $this->view->img($this->pictureTable->getFormatRequest($picture), [
            'format'  => 'picture-thumb',
            'alt'     => $name,
            'title'   => $name,
            'shuffle' => true
        ]);

        if ($isModer && $picture['name']) {
            $title = $this->view->escapeHtmlAttr($this->view->translate('picture-preview/special-name'));
            $escName = '<span style="color:darkgreen" title="'.$title.'">' .
                              $escName .
                          '</span>';
        }

        $moderVote = $this->getModerVote($picture['id']);

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
                        '<p>' . $view->htmlA($url, $escName, false) . '</p>' .
                        $this->userBehaviour($picture, $isModer) .
                    '</div>' .
                '</div>';
    }
}
