<?php
namespace Muffin\Trash\Model\Behavior;

use ArrayObject;
use Cake\Core\Configure;
use Cake\Database\Expression\IdentifierExpression;
use Cake\Database\Expression\UnaryExpression;
use Cake\Datasource\EntityInterface;
use Cake\Event\Event;
use Cake\I18n\Time;
use Cake\ORM\Behavior;
use Cake\ORM\Query;
use Cake\ORM\Table;
use RuntimeException;

/**
 * Trash Behavior.
 *
 */
class TrashBehavior extends Behavior
{

    /**
     * Default configuration.
     *
     * - field: the name of the datetime field to use for tracking `trashed` records.
     *
     * @var array
     */
    protected $_defaultConfig = [
        'field' => null,
        'events' => [
            'Model.beforeDelete',
            'Model.beforeFind',
        ],
    ];

    /**
     * Constructor
     *
     * Merges config with the default and store in the config property
     *
     * @param \Cake\ORM\Table $table The table this behavior is attached to.
     * @param array $config The config for this behavior.
     */
    public function __construct(Table $table, array $config = [])
    {
        $columns = $table->schema()->columns();
        foreach (['deleted', 'trashed'] as $name) {
            if (in_array($name, $columns)) {
                $this->_defaultConfig['field'] = $name;
                break;
            }
        }

        if (empty($this->_defaultConfig['field']) &&
            $field = Configure::read('Muffin/Trash.field')
        ) {
            $this->_defaultConfig['field'] = $field;
        }

        parent::__construct($table, $config);
    }

    /**
     * Return list of events this behavior is interested in.
     *
     * @return array
     */
    public function implementedEvents()
    {
        $events = [];
        foreach ((array)$this->config('events') as $event) {
            list(, $method) = explode('.', $event);
            $events[$event] = $method;
        }

        return $events;
    }

    /**
     * Callback to never really delete a record but instead mark it as `trashed`.
     *
     * @param \Cake\Event\Event $event The beforeDelete event that was fired.
     * @param \Cake\Datasource\EntityInterface $entity The entity to be deleted.
     * @param \ArrayObject $options Options.
     * @return true
     * @throws \RuntimeException if fails to mark entity as `trashed`.
     */
    public function beforeDelete(Event $event, EntityInterface $entity, ArrayObject $options)
    {
        if (!$this->trash($entity)) {
            throw new RuntimeException();
        }

        $event->stopPropagation();

        $event->subject()->dispatchEvent('Model.afterDelete', [
            'entity' => $entity,
            'options' => $options
        ]);

        return true;
    }

    /**
     * Trash given entity.
     *
     * @param \Cake\Datasource\EntityInterface $entity EntityInterface.
     * @return bool
     * @throws \RuntimeException if no primary key is set on entity.
     */
    public function trash(EntityInterface $entity)
    {
        $field = $this->getTrashField(false);
        $primaryKey = $this->_table->primaryKey();

        if (empty($entity->{$primaryKey})) {
            throw new RuntimeException();
        }

        return (bool)$this->_table->updateAll(
            [$this->getTrashField(false) => new Time()],
            [$primaryKey => $entity->{$primaryKey}]
        );
    }

    /**
     * Callback to always return rows that have not been `trashed`.
     *
     * @param \Cake\Event\Event $event Event.
     * @param \Cake\ORM\Query $query Query.
     * @param \ArrayObject $options Options.
     * @param bool $primary Primary or associated table being queries.
     * @return void
     */
    public function beforeFind(Event $event, Query $query, ArrayObject $options, $primary)
    {
        $field = $this->getTrashField();
        $check = false;

        $query->traverseExpressions(function ($expression) use (&$check, $field) {
            if ($expression instanceof IdentifierExpression) {
                !$check && $check = $expression->getIdentifier() === $field;
            }
        });

        if ($check) {
            return;
        }

        $query->andWhere($query->newExpr()->isNull($field));
    }

    /**
     * Custom finder to get only the `trashed` rows.
     *
     * @param \Cake\ORM\Query $query Query.
     * @param array $options Options.
     * @return \Cake\ORM\Query
     */
    public function findOnlyTrashed(Query $query, array $options)
    {
        return $query->andWhere($query->newExpr()->isNotNull($this->getTrashField()));
    }

    /**
     * Custom finder to get all rows (`trashed` or not).
     *
     * @param \Cake\ORM\Query $query Query.
     * @param array $options Options.
     * @return \Cake\ORM\Query
     */
    public function findWithTrashed(Query $query, array $options)
    {
        return $query->where(['OR' => [
            $query->newExpr()->isNotNull($this->getTrashField()),
            $query->newExpr()->isNull($this->getTrashField()),
        ]]);
    }

    /**
     * Marks all rows matching `$conditions` as `trashed`.
     *
     * @param mixed $conditions Conditions to be used, accepts anything Query::where()
     * can take.
     * @return int Count Returns the affected rows.
     */
    public function trashAll($conditions)
    {
        return $this->_table->updateAll(
            [$this->getTrashField(false) => new Time()],
            $conditions
        );
    }

    /**
     * Deletes all rows marked as `trashed`.
     *
     * @return int
     */
    public function emptyTrash()
    {
        return $this->_table->deleteAll($this->_getUnaryExpression());
    }

    /**
     * Restores all (or given) trashed row(s).
     *
     * @param \Cake\Datasource\EntityInterface|null $entity to restore.
     * @return bool|\Cake\Datasource\EntityInterface|int|mixed
     */
    public function restoreTrash(EntityInterface $entity = null)
    {
        $data = [$this->getTrashField(false) => null];

        if ($entity instanceof EntityInterface) {
            if ($entity->dirty()) {
                throw new RuntimeException('Can not restore from a dirty entity.');
            }
            $entity->set($data);
            return $this->_table->save($entity);
        }

        return $this->_table->updateAll($data, $this->_getUnaryExpression());
    }

    /**
     * Returns a unary expression for bulk record manipulation.
     *
     * @return \Cake\Database\Expression\UnaryExpression
     */
    protected function _getUnaryExpression()
    {
        return new UnaryExpression(
            'IS NOT NULL',
            $this->getTrashField(false),
            UnaryExpression::POSTFIX
        );
    }

    /**
     * Returns the table's field used to mark a `trashed` row.
     *
     * @param bool $aliased Should field be aliased or not. Default true.
     * @return string
     */
    public function getTrashField($aliased = true)
    {
        $field = $this->config('field');

        if ($aliased) {
            return $this->_table->aliasField($field);
        }

        return $field;
    }
}
