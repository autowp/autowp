<?php

use Application\Db\Table;

class Engine_Parent_Cache extends Table
{
    protected $_name = 'engine_parent_cache';
    protected $_primary = ['engine_id', 'parent_id'];

    protected $_referenceMap = [
        'Engine' => [
            'columns'       => ['engine_id'],
            'refTableClass' => 'Engines',
            'refColumns'    => ['id']
        ],
        'Parent' => [
            'columns'       => ['parent_id'],
            'refTableClass' => 'Engines',
            'refColumns'    => ['id']
        ],
    ];

    public function rebuild()
    {
        $this->delete([]);

        $table = new Engines();

        $this->_rebuild($table, [0]);
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
            $this->insert([
                'engine_id' => intval($cat_id),
                'parent_id' => intval($cat_id),
            ]);

            $this->_rebuild($table, array_merge([$cat_id], $id));
        }

        foreach ($id as $tid) {
            if ( $tid && ( $id[0] != $tid ) ) {

                $this->insert([
                    'engine_id' => $id[0],
                    'parent_id' => $tid,
                ]);
            }
        }
    }

    /**
     * @param Engine_Row $engine
     */
    public function rebuildOnRemoveParent(Engine_Row $engine)
    {
        $table = new Engines();

        // collect child engines
        $subTreeEngines = $table->fetchAll(
            $table->select(true)
                ->join('engine_parent_cache', 'engines.id = engine_parent_cache.engine_id', null)
                ->where('engine_parent_cache.parent_id = ?', $engine->id)
        );

        $subTreeIds = [$engine->id];
        foreach ($subTreeEngines as $subTreeEngine) {
            $subTreeIds[] = $subTreeEngine->id;
        }

        $this->delete([
            'engine_id in (?)'     => $subTreeIds,
            'parent_id not in (?)' => $subTreeIds
        ]);
    }

    public function rebuildOnCreate(Engine_Row $engine)
    {
        // self
        $this->insert([
            'engine_id' => $engine->id,
            'parent_id' => $engine->id,
        ]);
    }

    /**
     * @param Engine_Row $engine
     */
    public function rebuildOnAddParent(Engine_Row $engine)
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
            $this->insert([
                'engine_id' => $engine->id,
                'parent_id' => $parentEngine->id,
            ]);
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
                $this->insert([
                    'engine_id' => $childEngine->id,
                    'parent_id' => $parentEngine->id,
                ]);
            }
        }
    }
}