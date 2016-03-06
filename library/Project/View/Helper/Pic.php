<?php

class Project_View_Helper_Pic extends Zend_View_Helper_HtmlElement
{
    /**
     * @var Pictures_Row
     */
    private $picture = null;

    public function pic(Pictures_Row $picture = null)
    {
        $this->picture = $picture;

        return $this;
    }

    public function url()
    {
        if ($this->picture) {
            $identity = $this->picture->identity ? $this->picture->identity : $this->picture->id;

            return $this->view->url(array(
                'controller' => 'picture',
                'action'     => 'index',
                'picture_id' => $identity
            ), 'picture', true);
        }
        return false;
    }

    private static function mbUcfirst($str)
    {
        return mb_strtoupper(mb_substr($str, 0, 1)) . mb_substr($str, 1);
    }

    public function htmlTitle(array $picture)
    {
        $view = $this->view;

        if (isset($picture['name']) && $picture['name']) {
            return $view->escape($picture['name']);
        }

        switch ($picture['type']) {
            case Picture::CAR_TYPE_ID:
                return
                    ($picture['perspective'] ? $view->escape(self::mbUcfirst($view->translate($picture['perspective']))) . ' ' : '') .
                    $view->car()->htmlTitle($picture['car']);
                break;

            case Picture::ENGINE_TYPE_ID:
            case Picture::LOGO_TYPE_ID:
            case Picture::MIXED_TYPE_ID:
            case Picture::UNSORTED_TYPE_ID:
            case Picture::FACTORY_TYPE_ID:
                return $view->escape($this->textTitle($picture));
                break;
        }

        return 'Picture';
    }

    public function textTitle(array $picture)
    {
        $view = $this->view;

        if (isset($picture['name']) && $picture['name']) {
            return $picture['name'];
        }

        switch ($picture['type']) {
            case Picture::CAR_TYPE_ID:
                return
                    ($picture['perspective'] ? self::mbUcfirst($view->translate($picture['perspective'])) . ' ' : '') .
                    $view->car()->textTitle($picture['car']);
                break;

            case Picture::ENGINE_TYPE_ID:
                if ($picture['engine']) {
                    return sprintf($view->translate('picturelist/engine-%s'), $picture['engine']);
                } else {
                    return $view->translate('picturelist/engine');
                }
                break;

            case Picture::LOGO_TYPE_ID:
                if ($picture['brand']) {
                    return sprintf($view->translate('picturelist/logotype-%s'), $picture['brand']);
                } else {
                    return $view->translate('picturelist/logotype');
                }
                break;

            case Picture::MIXED_TYPE_ID:
                if ($picture['brand']) {
                    return sprintf($view->translate('picturelist/mixed-%s'), $picture['brand']);
                } else {
                    return $view->translate('picturelist/mixed');
                }
                break;

            case Picture::UNSORTED_TYPE_ID:
                if ($picture['brand']) {
                    return sprintf($view->translate('picturelist/unsorted-%s'), $picture['brand']);
                } else {
                    return $view->translate('picturelist/unsorted');
                }
                break;

            case Picture::FACTORY_TYPE_ID:
                return $picture['factory'];
                break;
        }

        return 'Picture';
    }
}