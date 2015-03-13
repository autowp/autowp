<?php

class Project_Controller_Action_Helper_Pic extends Zend_Controller_Action_Helper_Abstract
{
    /**
     * @var Picture_View
     */
    protected $_pictureViewTable = null;

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

    public function url($id, $identity)
    {
        $urlHelper = $this->getActionController()->getHelper('Url');

        return $urlHelper->url(array(
            'module'     => 'default',
            'controller' => 'picture',
            'action'     => 'index',
            'picture_id' => $identity ? $identity : $id
        ), 'picture', true);
    }

    public function listData($pictures, array $options = array())
    {
        $defaults = array(
            'width'            => null,
            'disableBehaviour' => false
        );

        $options = array_replace($defaults, $options);

        $colClass = '';
        $width = null;

        if ($options['width']) {
            $width = (int)$options['width'];
            if (!$colClass) {
                $colClass = 'col-lg-' . (12 / $width) . ' col-md-' . (12 / $width);
            }
        }

        $controller = $this->getActionController();
        $userHelper = $controller->getHelper('user');
        $isModer = $userHelper->inheritsRole('pictures-moder');
        $userId = null;
        if ($userHelper->logedIn()) {
            $user = $userHelper->get();
            $userId = $user ? $user->id : null;
        }

        $language = $controller->getHelper('language')->direct();

        $ids = array();

        if (is_array($pictures)) {

            $rows = array();
            foreach ($pictures as $picture) {
                $ids[] = $picture['id'];
                $rows[] = $picture->toArray();
            }

            // moder votes
            $moderVotes = array();
            if (count($ids)) {
                $moderVoteTable = $this->getModerVoteTable();
                $db = $moderVoteTable->getAdapter();

                $voteRows = $db->fetchAll(
                    $db->select()
                        ->from($moderVoteTable->info('name'), array(
                            'picture_id',
                            'vote'  => new Zend_Db_Expr('sum(if(vote, 1, -1))'),
                            'count' => 'count(1)'
                        ))
                        ->where('picture_id in (?)', $ids)
                        ->group('picture_id')
                );

                foreach ($voteRows as $row) {
                    $moderVotes[$row['picture_id']] = array(
                        'moder_votes'       => (int)$row['vote'],
                        'moder_votes_count' => (int)$row['count']
                    );
                }
            }

            // views
            $views = array();
            if (!$options['disableBehaviour']) {
                $views = $this->getPictureViewTable()->getValues($ids);
            }

            foreach ($rows as &$row) {
                $id = $row['id'];
                if (isset($moderVotes[$id])) {
                    $vote = $moderVotes[$id];
                    $row['moder_votes'] = $vote['moder_votes'];
                    $row['moder_votes_count'] = $vote['moder_votes_count'];
                } else {
                    $row['moder_votes'] = null;
                    $row['moder_votes_count'] = 0;
                }
                if (!$options['disableBehaviour']) {
                    if (isset($views[$id])) {
                        $row['views'] = $views[$id];
                    } else {
                        $row['views'] = 0;
                    }
                }
            }
            unset($row);

        } elseif ($pictures instanceof Zend_Db_Table_Select) {

            $table = $pictures->getTable();
            $db = $table->getAdapter();

            $select = clone $pictures;
            $bind = array();

            $select
                ->reset(Zend_Db_Select::COLUMNS)
                ->setIntegrityCheck(false)
                ->columns(array(
                    'pictures.id', 'pictures.identity', 'pictures.name',
                    'pictures.width', 'pictures.height',
                    'pictures.crop_left', 'pictures.crop_top', 'pictures.crop_width', 'pictures.crop_height',
                    'pictures.status', 'pictures.image_id',
                    'pictures.brand_id', 'pictures.car_id', 'pictures.engine_id',
                    'pictures.perspective_id', 'pictures.type', 'pictures.factory_id'
                ));

            $select
                ->group('pictures.id')
                ->joinLeft('pictures_moder_votes', 'pictures.id = pictures_moder_votes.picture_id', array(
                    'moder_votes'       => 'sum(if(pictures_moder_votes.vote, 1, -1))',
                    'moder_votes_count' => 'count(pictures_moder_votes.picture_id)'
                ));

            if (!$options['disableBehaviour']) {
                $select
                    ->joinLeft('picture_view', 'pictures.id = picture_view.picture_id', 'views')
                    ->joinLeft(array('ct' => 'comment_topic'), 'ct.type_id = :type_id and ct.item_id = pictures.id', 'messages');

                $bind['type_id'] = Comment_Message::PICTURES_TYPE_ID;
            }

            $rows = $db->fetchAll($select, $bind);


            foreach ($rows as $idx => $picture) {
                $ids[] = (int)$picture['id'];
            }

        } else {
            throw new Exception("Unexpected type of pictures");
        }

        //print $select;

        // prefetch
        $requests = array();
        foreach ($rows as $idx => $picture) {
            $requests[$idx] = Pictures_Row::buildFormatRequest($picture);
        }

        // images
        $imageStorage = Zend_Controller_Front::getInstance()->getParam('bootstrap')->getResource('imagestorage');

        $imagesInfo = $imageStorage->getFormatedImages($requests, 'picture-thumb');

        // names
        $pictureTable = new Picture();
        $names = $pictureTable->getNames($rows, array(
            'language' => $language
        ));

        // comments
        if (!$options['disableBehaviour']) {
            if ($userId) {
                $ctTable = new Comment_Topic();
                $newMessages = $ctTable->getNewMessages(
                    Comment_Message::PICTURES_TYPE_ID,
                    $ids,
                    $userId
                );
            }
        }


        $items = array();
        foreach ($rows as $idx => $row) {

            $id = (int)$row['id'];

            $name = isset($names[$id]) ? $names[$id] : null;

            $item = array(
                'id'        => $id,
                'name'      => $name,
                'url'       => $this->url($row['id'], $row['identity']),
                'src'       => isset($imagesInfo[$idx]) ? $imagesInfo[$idx]->getSrc() : null,
                'moderVote' => $row['moder_votes_count'] > 0 ? $row['moder_votes'] : null,
            );

            if (!$options['disableBehaviour']) {
                $msgCount = $row['messages'];
                $newMsgCount = 0;
                if ($userId) {
                    $newMsgCount = isset($newMessages[$id]) ? $newMessages[$id] : $msgCount;
                }

                $item = array_replace($item, array(
                    'resolution'     => (int)$row['width'] . '×' . (int)$row['height'],
                    'cropped'        => Pictures_Row::checkCropParameters($row),
                    'cropResolution' => $row['crop_width'] . '×' . $row['crop_height'],
                    'status'         => $row['status'],
                    'views'          => (int)$row['views'],
                    'msgCount'       => $msgCount,
                    'newMsgCount'    => $newMsgCount,
                ));
            }



            $items[] = $item;
        }

        return array(
            'items'            => $items,
            'colClass'         => $colClass,
            'disableBehaviour' => $options['disableBehaviour'],
            'isModer'          => $isModer,
            'width'            => $width
        );
    }
}