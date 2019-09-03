<?php
namespace Muffin\Trash\Panel;

use Cake\Core\Configure;
use Cake\Event\Event;
use Cake\ORM\Table;
use Cake\ORM\TableRegistry;
use Cake\Log\Log;
use DebugKit\DebugPanel;

/**
 * TrashPanel
 *
 * A DebugKit Panel that shows how many records were `Trashed`
 */
class TrashPanel extends DebugPanel
{
    /**
     * @var array Array containing names of tables to check for trashed records
     */
    protected $tables = [];

    /**
     * Initialize the TrashPanel
     *
     * @return void
     */
    public function initialize()
    {
        $this->_data = ['trashed' => []];
        $this->tables = Configure::read('Muffin/Trash.panel.tables');
        if (empty($this->tables)) {
            $this->tables = [];
        }
        foreach ($this->tables as $table) {
            if ($table instanceof Table) {
                $this->_data['trashed'][$table->getTable()] = 0;
            } else {
                $this->_data['trashed'][$table] = 0;
            }
        }
    }

    /**
     * Counts the number of records trashed from a table
     *
     * @param mixed $tableOrName A table object or table name
     *
     * @return int number of records trashed
     */
    public function countTrashed($tableOrName)
    {
        $Table = $tableOrName;
        if (is_string($tableOrName)) {
            $Table = TableRegistry::getTableLocator()->get($tableOrName);
        }
        if (! $tableOrName instanceof Table) {
            Log::warning(__("Failed to countTrashed() on {0}", $tableOrName));
            return -1;
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
            return __("None trashed");
        }

        return __("{0} trashed", $this->_data['totalTrashed']);
    }

    /**
     * Shutdown event hook
     *
     * @param \Cake\Event\Event $event A panel event
     *
     * @return void
     */
    public function shutdown(Event $event)
    {
        $this->_data = [
            'totalTrashed' => 0,
            'trashed' => []
        ];

        foreach ($this->tables as $table) {
            $count = $this->countTrashed($table);
            if ($table instanceof Table) {
                $this->_data['trashed'][$table->getTable()] = $count;
            } else {
                $this->_data['trashed'][$table] = $count;
            }
        }
        $this->_data['totalTrashed'] = array_sum($this->_data['trashed']);
    }
}
