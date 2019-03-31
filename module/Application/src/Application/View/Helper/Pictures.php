<?php

namespace Application\View\Helper;

use ArrayObject;
use Zend\View\Helper\AbstractHelper;

use Autowp\Comments;

use Application\Model\Picture;
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
     * @var Picture
     */
    private $picture;

    public function __construct(
        Comments\CommentsService $comments,
        PictureView $pictureView,
        PictureModerVote $pictureModerVote,
        Picture $picture
    ) {
        $this->comments = $comments;
        $this->pictureView = $pictureView;
        $this->pictureModerVote = $pictureModerVote;
        $this->picture = $picture;
    }

    private function isPictureModer()
    {
        /* @phan-suppress-next-line PhanUndeclaredMethod */
        return $this->view->user()->inheritsRole('pictures-moder');
    }


    public function behaviour($picture)
    {
        return $this->userBehaviour($picture, $this->isPictureModer());
    }

    /**
     * @param array $picture
     * @param bool $isModer
     * @return string
     */
    private function renderBehaviour(array $picture, bool $isModer)
    {
        $data = [
            'isModer'         => $isModer,
            'resolution'      => $picture['width'].'×'.$picture['height'],
            'status'          => $picture['status'],
            'views'           => $picture['views'],
            'msgCount'        => $picture['msgCount'],
            'newMsgCount'     => $picture['newMsgCount'],
            'url'             => $picture['url']
        ];

        if ($isModer) {
            $data['cropped'] = $picture['cropped'];
            $data['crop_resolution'] = $picture['crop_width'] . '×' . $picture['crop_height'];
        }

        /* @phan-suppress-next-line PhanUndeclaredMethod */
        return $this->view->partial('application/picture-behaviour', $data);
    }


    private function userBehaviour($picture, bool $isModer)
    {
        if ($picture instanceof ArrayObject) {
            $picture = (array)$picture;
        }

        /* @phan-suppress-next-line PhanUndeclaredMethod */
        if ($this->view->user()->logedIn()) {
            $commentsStat = $this->comments->getTopicStatForUser(
                \Application\Comments::PICTURES_TYPE_ID,
                $picture['id'],
                /* @phan-suppress-next-line PhanUndeclaredMethod */
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
            /* @phan-suppress-next-line PhanUndeclaredMethod */
            'url'         => $this->view->pic($picture)->url(),
            'width'       => $picture['width'],
            'height'      => $picture['height'],
            'msgCount'    => $msgCount,
            'newMsgCount' => $newMsgCount,
            'views'       => $this->pictureView->get($picture['id']),
            'status'      => $picture['status'],
        ];

        if ($isModer) {
            $crop = $this->imageStrorage()->getImageCrop($picture['image_id']);

            $data['cropped'] = (bool)$crop;
            $data['crop_width'] = $crop ? $crop['width'] : null;
            $data['crop_height'] = $crop ? $crop['height'] : null;
        }

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


    public function picture($picture)
    {
        $view = $this->view;

        $isModer = $this->isPictureModer();

        /* @phan-suppress-next-line PhanUndeclaredMethod */
        $name = $view->pic()->name($picture, $this->view->language());
        /* @phan-suppress-next-line PhanUndeclaredMethod */
        $escName = $view->escape($name);

        /* @phan-suppress-next-line PhanUndeclaredMethod */
        $url = $view->pic($picture)->url();

        /* @phan-suppress-next-line PhanUndeclaredMethod */
        $imageHtml = $this->view->img($picture['image_id'], [
            'format'  => 'picture-thumb',
            'alt'     => $name,
            'title'   => $name,
            'shuffle' => true
        ]);

        if ($isModer && $picture['name']) {
            /* @phan-suppress-next-line PhanUndeclaredMethod */
            $title = $this->view->escapeHtmlAttr($this->view->translate('picture-preview/special-name'));
            $escName = '<span style="color:darkgreen" title="'.$title.'">' . $escName . '</span>';
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
                        /* @phan-suppress-next-line PhanUndeclaredMethod */
                        $view->htmlA($url, $imageHtml, false) .
                        /* @phan-suppress-next-line PhanUndeclaredMethod */
                        '<p>' . $view->htmlA($url, $escName, false) . '</p>' .
                        $this->userBehaviour($picture, $isModer) .
                    '</div>' .
                '</div>';
    }
}
