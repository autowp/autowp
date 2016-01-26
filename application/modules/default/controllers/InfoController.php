<?php
class InfoController extends Zend_Controller_Action
{
    private function _loadSpecs($table, $parentId)
    {
        if ($parentId) {
            $filter = array('parent_id = ?' => $parentId);
        } else {
            $filter = array('parent_id is null');
        }

        $result = [];
        foreach ($table->fetchAll($filter, 'short_name') as $row) {
            $result[] = array(
                'id'         => $row->id,
                'short_name' => $row->short_name,
                'name'       => $row->name,
                'childs'     => $this->_loadSpecs($table, $row->id)
            );
        }

        return $result;
    }

    public function specAction()
    {
        $table = new Spec();

        $this->view->assign(array(
            'items' => $this->_loadSpecs($table, null)
        ));
    }

    public function textAction()
    {
        $textId = (int)$this->getParam('id');
        $revision = (int)$this->getParam('revision');

        $textStorage = $this->_helper->textStorage();

        $text = $textStorage->getTextInfo($textId);
        if ($text === null) {
            return $this->_forward('notfound', 'error');
        }

        if ($revision) {
            $current = $textStorage->getRevisionInfo($textId, $revision);
        } else {
            $current = $textStorage->getRevisionInfo($textId, $text['revision']);
        }
        if ($current === null) {
            return $this->_forward('notfound', 'error');
        }

        $prevText = $textStorage->getRevisionInfo($textId, $current['revision']-1);

        $nextUrl = null;
        if ($current['revision'] + 1 <= $text['revision']) {
            $nextUrl = $this->_helper->url->url(array(
                'revision' => $current['revision'] + 1
            ));
        }

        $prevUrl = null;
        if ($current['revision'] - 1 > 0) {
            $prevUrl = $this->_helper->url->url(array(
                'revision' => $current['revision'] - 1
            ));
        }

        $userTable = new Users();
        $currentUser = $userTable->find($current['user_id'])->current();
        $prevUser = $userTable->find($prevText['user_id'])->current();

        $this->view->assign(array(
            'current'     => $current,
            'prev'        => $prevText,
            'prevUrl'     => $prevUrl,
            'nextUrl'     => $nextUrl,
            'currentUser' => $currentUser,
            'prevUser'    => $prevUser
        ));
    }
}