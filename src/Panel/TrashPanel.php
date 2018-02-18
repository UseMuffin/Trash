<?php
namespace Muffin\Trash\Panel;

use Cake\Core\Configure;
use Cake\Event\Event;
use Cake\ORM\TableRegistry;
use DebugKit\DebugPanel;
use RuntimeException;

/**
 * TrashPanel
 *
 * Lists how many records were `Trashed` from the configured tables  
 * 
 */
class TrashPanel extends DebugPanel
{
    /**
     * @var array Array containing names of tables to check for trashed records
     */
    protected $_tables = [];

    /**
     * Initialize the TrashPanel
     *
     * @return void
     */
    public function initialize()
    {
        $this->_data = ['trashed' => []];
        $this->_tables = Configure::read('Muffin/Trash.panel.tables');
        if (empty($this->_tables)) {
            $this->_tables = [];
        }
        foreach ($this->_tables as $table) {
            if ($table instanceof \Cake\ORM\Table) {
                $this->_data['trashed'][$table->table()] = 0;
            } else {
                $this->_data['trashed'][$table] = 0;
            }
        }
    }

    /**
     * Counts the number of records trashed from a table
     *
     * @param string|\Cake\ORM\Table $tableOrName A table object or table name
     * 
     * @return int number of records trashed
     */
    public function countTrashed($tableOrName)
    {
        $Table = $tableOrName;
        if (is_string($tableOrName)) {
            $Table = TableRegistry::get($tableOrName);
        }
        if (! $tableOrName instanceof \Cake\ORM\Table) {
            // TODO: log that w failed to find the table here
            return -1;
        }
        // We need to disable TrashBehavior here enabled will give us zero results
        if ($Table->hasBehavior('Muffin/Trash.Trash')) {
            $Table->behaviors()->unload('Muffin/Trash.Trash');
        }

        return $Table->countTrashed();
    }

    /**
     * Summary of trashed records
     * 
     * @return string string of how many trashed records there are
     */
    public function summary()
    {
        if (!isset($this->_data['totalTrashed'])) {
            return 0;
        }
        return __("{0} trashed", $this->_data['totalTrashed']);
    }

    /**
     * Shutdown event hook
     *
     * @param Cake\Event\Event $event A panel event
     * 
     * @return void
     */
    public function shutdown(Event $event)
    {
        $this->_data = [
            'totalTrashed' => 0,
            'trashed' => []
        ];

        foreach ($this->_tables as $table) {
            $count = $this->countTrashed($table);
            if ($table instanceof \Cake\ORM\Table) {
                $this->_data['trashed'][$table->table()] = $count;
            } else {
                $this->_data['trashed'][$table] = $count;
            }
        }
        $this->_data['totalTrashed'] = array_sum($this->_data['trashed']);
    }
}