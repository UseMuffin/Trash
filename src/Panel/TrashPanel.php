<?php
namespace Muffin\Trash\Panel;

use Cake\Core\Configure;
use Cake\Event\Event;
use Cake\ORM\TableRegistry;
use DebugKit\DebugPanel;

/**
 * TrashPanel
 *
 * Lists how many records were `Trashed` from the configured tables  
 * 
 */
class TrashPanel extends DebugPanel
{
    /**
     * @property array Array containing names of tables to check for trashed records
     */
    protected $_tables = [];

    /**
     * initialize
     *
     */
    public function initialize()
    {
        $this->_data = ['trashed' => []];

        if (Configure::read('TrashedPanel.tables')) {
            $this->_tables = Configure::read('TrashedPanel.tables');
        }

        foreach($this->_tables as $table) {
            $this->_data['trashed'][$table] = 0;
        }
    }

    /**
     * Counts the number of records trashed from a table
     *
     * @param string $table The table name - must have a class in App\Model\Table
     * @return integer number of records trashed
     */
    protected function _countTrashed($tableName)
    {
        $Table = TableRegistry::get($tableName);
        // Having the TrashBehavior enabled will give us zero results
        // so we remove it here
        $Table->removeBehavior('Trash');
        $count = $Table->find()->where(function($exp, $query) {
                return $exp->isNotNull('deleted');
            })
            ->count($Table->primaryKey());
        return $count;
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
     * @param Cake\Event\Event $event
     */
    public function shutdown(Event $event)
    {
        $this->_data = [
            'totalTrashed' => 0,
            'trashed' => []
        ];

        foreach($this->_tables as $table) {
            $this->_data['trashed'][$table] = $this->_countTrashed($table);
        }

        $this->_data['totalTrashed'] = array_sum($this->_data['trashed']);
    }
}
