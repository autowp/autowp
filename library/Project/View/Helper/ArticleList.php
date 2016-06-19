<?php

class Project_View_Helper_ArticleList extends Zend_View_Helper_Abstract
{
    public function articleList(Zend_Db_Table_Rowset $list)
    {
        $view = $this->view;

        $items = array();
        foreach ($list as $article) {
            $url = $view->url(array(
                'action'          => 'article',
                'article_catname' => $article->catname
            ), 'articles', true, true);

            $date = $article->getDate('first_enabled_datetime');
            $author = $article->findParentUsers();

            $html = '<p>'.$view->htmlA(array('href' => $url), $article->name).'</p>'.
                    '<p>'.
                        $view->escape($article->description).
                        ' '.
                        $view->htmlA($url, 'подробнее').
                    '<p>'.
                        ($author ? $view->user($author) . ', ' : '').
                        ($date ? '<span class="date">'. $view->humanDate($date). '</span>' : '') .
                    '</p>';

            if ($article->previewExists())
                $html = $view->htmlA(
                            array('href' => $url),
                            $view->htmlImg(array(
                                'src'   => $article->getPreviewUrl(),
                                'style' => 'float:left;margin:0 10px 4px 0'
                            )),
                            false
                        ).
                        $html;

            $items[] = $html;
        }
        return $view->htmlList($items, false, array('class' => 'articleList'), false);
    }
}