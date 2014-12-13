<?php

class Engine_Parent_Cache extends Project_Db_Table
{
    protected $_name = 'engine_parent_cache';
    protected $_primary = array('engine_id', 'parent_id');

    protected $_referenceMap    = array(
        'Engine' => array(
            'columns'       => array('engine_id'),
            'refTableClass' => 'Engines',
            'refColumns'    => array('id')
        ),
        'Parent' => array(
            'columns'       => array('parent_id'),
            'refTableClass' => 'Engines',
            'refColumns'    => array('id')
        ),
    );

    public function rebuild()
    {
        $this->delete(array());

        $table = new Engines();

        $this->_rebuild($table, array(0));
    }

    protected function _rebuild(Engines $table, $id)
    {
        $select = $table->getAdapter()->select()
            ->from($table->info('name'), 'id');
        if ($id[0] == 0) {
            $select->where('parent_id is null');
        } else {
            $select->where('parent_id = ?', $id[0]);
        }

        foreach ($table->getAdapter()->fetchCol($select) as $cat_id) {
            $this->insert(array(
                'engine_id' => intval($cat_id),
                'parent_id' => intval($cat_id),
            ));

            $this->_rebuild($table, array_merge(array($cat_id), $id));
        }

        foreach ($id as $tid) {
            if ( $tid && ( $id[0] != $tid ) ) {

                $this->insert(array(
                    'engine_id' => $id[0],
                    'parent_id' => $tid,
                ));
            }
        }
    }

    /**
     * @param Engines_Row $engine
     */
    public function rebuildOnRemoveParent(Engines_Row $engine)
    {
        $table = new Engines();

        // collect child engines
        $subTreeEngines = $table->fetchAll(
            $table->select(true)
                ->join('engine_parent_cache', 'engines.id = engine_parent_cache.engine_id', null)
                ->where('engine_parent_cache.parent_id = ?', $engine->id)
        );

        $subTreeIds = array($engine->id);
        foreach ($subTreeEngines as $subTreeEngine) {
            $subTreeIds[] = $subTreeEngine->id;
        }

        $this->delete(array(
            'engine_id in (?)'     => $subTreeIds,
            'parent_id not in (?)' => $subTreeIds
        ));
    }

    public function rebuildOnCreate(Engines_Row $engine)
    {
        // self
        $this->insert(array(
            'engine_id' => $engine->id,
            'parent_id' => $engine->id,
        ));
    }

    /**
     * @param Engines_Row $engine
     */
    public function rebuildOnAddParent(Engines_Row $engine)
    {
        $table = new Engines();

        // collect parent engines
        $currentEngine = $engine;
        while ($parentEngine = $table->find($currentEngine->parent_id)->current()) {
            $parentEngines[] = $parentEngine;
            $currentEngine = $parentEngine;
        }

        // move up
        foreach ($parentEngines as $parentEngine) {
            $this->insert(array(
                'engine_id' => $engine->id,
                'parent_id' => $parentEngine->id,
            ));
        }

        // collect child engines
        $childEngines = $table->fetchAll(
            $table->select(true)
                ->join('engine_parent_cache', 'engines.id = engine_parent_cache.engine_id', null)
                ->where('engine_parent_cache.parent_id = ?', $engine->id)
                ->where('engine_parent_cache.parent_id <> engine_parent_cache.engine_id')
        );

        foreach ($childEngines as $childEngine) {
            foreach ($parentEngines as $parentEngine) {
                $this->insert(array(
                    'engine_id' => $childEngine->id,
                    'parent_id' => $parentEngine->id,
                ));
            }
        }
    }
}