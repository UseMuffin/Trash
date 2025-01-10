<?php
declare(strict_types=1);

namespace Muffin\Trash\Model\Behavior;

use ArrayObject;
use Cake\Core\Configure;
use Cake\Core\Exception\CakeException;
use Cake\Database\Expression\BetweenExpression;
use Cake\Database\Expression\ComparisonExpression;
use Cake\Database\Expression\IdentifierExpression;
use Cake\Database\Expression\QueryExpression;
use Cake\Database\Expression\UnaryExpression;
use Cake\Database\Query\SelectQuery;
use Cake\Datasource\EntityInterface;
use Cake\Event\EventInterface;
use Cake\I18n\DateTime;
use Cake\ORM\Association;
use Cake\ORM\Behavior;
use Cake\ORM\Table;
use Closure;
use InvalidArgumentException;
use function Cake\Core\pluginSplit;

/**
 * Trash Behavior.
 */
class TrashBehavior extends Behavior
{
    public const SOFT_DELETE_OPTION = 'muffin.trash.soft-delete';

    /**
     * Default configuration.
     *
     * - field: the name of the datetime field to use for tracking `trashed` records.
     * - priority: the default priority for events
     * - events: the list of events to enable (also accepts arrays in `implementedEvents()`-compatible format)
     *
     * @var array<string, mixed>
     */
    protected array $_defaultConfig = [
        'field' => null,
        'priority' => null,
        'events' => [
            'Model.beforeDelete',
            'Model.beforeFind',
        ],
        'cascadeOnTrash' => true,
    ];

    /**
     * Initialize the behavior.
     *
     * @param array $config The config for this behavior.
     * @return void
     */
    public function initialize(array $config): void
    {
        if (!empty($config['events'])) {
            $this->setConfig('events', $config['events'], false);
        }
    }

    /**
     * Return list of events this behavior is interested in.
     *
     * @return array<string, mixed>
     * @throws \InvalidArgumentException When events are configured in an invalid format.
     */
    public function implementedEvents(): array
    {
        $events = [];
        if ($this->getConfig('events') === false) {
            return $events;
        }
        foreach ((array)$this->getConfig('events') as $eventKey => $event) {
            if (is_numeric($eventKey)) {
                $eventKey = $event;
                $event = null;
            }
            if ($event === null || is_string($event)) {
                $event = ['callable' => $event];
            }
            if (!is_array($event)) {
                throw new InvalidArgumentException('Event should be string or array');
            }
            $priority = $this->getConfig('priority');
            if (!array_key_exists('callable', $event) || $event['callable'] === null) {
                [, $event['callable']] = pluginSplit($eventKey);
            }
            if ($priority && !array_key_exists('priority', $event)) {
                $event['priority'] = $priority;
            }
            $events[$eventKey] = $event;
        }

        return $events;
    }

    /**
     * Callback to never really delete a record but instead mark it as `trashed`.
     *
     * @param \Cake\Event\EventInterface $event The beforeDelete event that was fired.
     * @param \Cake\Datasource\EntityInterface $entity The entity to be deleted.
     * @param \ArrayObject $options Options.
     * @return void
     * @throws \Cake\Core\Exception\CakeException if fails to mark entity as `trashed`.
     */
    public function beforeDelete(EventInterface $event, EntityInterface $entity, ArrayObject $options): void
    {
        if ($options->offsetExists('purge') && $options['purge'] === true) {
            return;
        }

        $event->stopPropagation();

        if (!$this->trash($entity, $options->getArrayCopy())) {
            $event->setResult(false);

            return;
        }

        $options[self::SOFT_DELETE_OPTION] = true;

        /** @var \Cake\ORM\Table $table */
        $table = $event->getSubject();
        $table->dispatchEvent('Model.afterDelete', [
            'entity' => $entity,
            'options' => $options,
        ]);

        $event->setResult(true);
    }

    /**
     * Trash given entity.
     *
     * @param \Cake\Datasource\EntityInterface $entity EntityInterface.
     * @param array $options Trash operation options.
     * @return bool
     * @throws \Cake\Core\Exception\CakeException if no primary key is set on entity.
     */
    public function trash(EntityInterface $entity, array $options = []): bool
    {
        $primaryKey = (array)$this->_table->getPrimaryKey();

        foreach ($primaryKey as $field) {
            if (!$entity->has($field)) {
                throw new CakeException('Primay key value has not been set');
            }
        }

        if ($this->getConfig('cascadeOnTrash')) {
            $associations = $this->_table->associations()->getByType(['HasOne', 'HasMany']);
            foreach ($associations as $association) {
                if ($this->_isRecursable($association, $this->_table)) {
                    $association->cascadeDelete($entity, ['_primary' => false] + $options);
                }
            }
        }

        $data = [$this->getTrashField(false) => new DateTime()];
        $entity->set($data, ['guard' => false]);

        if ($this->_table->save($entity, $options)) {
            return true;
        }

        return false;
    }

    /**
     * Callback to always return rows that have not been `trashed`.
     *
     * @param \Cake\Event\EventInterface $event Event.
     * @param \Cake\ORM\Query\SelectQuery $query Query.
     * @param \ArrayObject $options Options.
     * @param bool $primary Primary or associated table being queries.
     * @return void
     */
    public function beforeFind(EventInterface $event, SelectQuery $query, ArrayObject $options, bool $primary): void
    {
        $field = $this->getTrashField();
        $addCondition = true;

        $query->traverseExpressions(function ($expression) use (&$addCondition, $field): void {
            if (!$addCondition) {
                return;
            }

            if (
                $expression instanceof IdentifierExpression
                && $expression->getIdentifier() === $field
            ) {
                $addCondition = false;

                return;
            }

            if (
                ($expression instanceof ComparisonExpression || $expression instanceof BetweenExpression)
                && $expression->getField() === $field
            ) {
                $addCondition = false;
            }
        });

        $option = $query->getOptions();

        if ($addCondition && empty($option['skipAddTrashCondition'])) {
            $query->andWhere($query->newExpr()->isNull($field));
        }
    }

    /**
     * Custom finder to get only the `trashed` rows.
     *
     * @param \Cake\ORM\Query\SelectQuery $query Query.
     * @param array $options Options.
     * @return \Cake\ORM\Query\SelectQuery
     */
    public function findOnlyTrashed(SelectQuery $query, array $options): SelectQuery
    {
        return $query->andWhere($query->newExpr()->isNotNull($this->getTrashField()));
    }

    /**
     * Custom finder to get all rows (`trashed` or not).
     *
     * @param \Cake\ORM\Query\SelectQuery $query Query.
     * @param array $options Options.
     * @return \Cake\ORM\Query\SelectQuery
     */
    public function findWithTrashed(SelectQuery $query, array $options = []): SelectQuery
    {
        return $query->applyOptions([
            'skipAddTrashCondition' => true,
        ]);
    }

    /**
     * Marks all rows matching `$conditions` as `trashed`.
     *
     * @param mixed $conditions Conditions to be used, accepts anything Query::where()
     * can take.
     * @return int Count Returns the affected rows.
     */
    public function trashAll(mixed $conditions): int
    {
        return $this->_table->updateAll(
            [$this->getTrashField(false) => new DateTime()],
            $conditions
        );
    }

    /**
     * Deletes all rows marked as `trashed`.
     *
     * @return int
     */
    public function emptyTrash(): int
    {
        return $this->_table->deleteAll($this->_getUnaryExpression());
    }

    /**
     * Restores all (or given) trashed row(s).
     *
     * @param \Cake\Datasource\EntityInterface|null $entity to restore.
     * @param array $options Restore operation options (only applies when restoring a specific entity).
     * @return \Cake\Datasource\EntityInterface|int|false
     */
    public function restoreTrash(?EntityInterface $entity = null, array $options = []): false|int|EntityInterface
    {
        $data = [$this->getTrashField(false) => null];

        if ($entity instanceof EntityInterface) {
            if ($entity->isDirty()) {
                throw new CakeException('Can not restore from a dirty entity.');
            }
            $entity->set($data, ['guard' => false]);

            return $this->_table->save($entity, $options);
        }

        return $this->_table->updateAll($data, $this->_getUnaryExpression());
    }

    /**
     * Restore an item from trashed status and all its related data
     *
     * @param \Cake\Datasource\EntityInterface|null $entity Entity instance
     * @param array $options Restore operation options (only applies when restoring a specific entity).
     * @return \Cake\Datasource\EntityInterface|int|bool
     */
    public function cascadingRestoreTrash(
        ?EntityInterface $entity = null,
        array $options = []
    ): bool|int|EntityInterface {
        $result = $this->restoreTrash($entity, $options);

        $associations = $this->_table->associations()->getByType(['HasOne', 'HasMany']);
        foreach ($associations as $association) {
            if ($this->_isRecursable($association, $this->_table)) {
                if ($entity === null) {
                    if ($result > 1) {
                        $result += $association->getTarget()->cascadingRestoreTrash(null, $options);
                    }
                } else {
                    /** @var list<string> $foreignKey */
                    $foreignKey = (array)$association->getForeignKey();
                    /** @var list<string> $bindingKey */
                    $bindingKey = (array)$association->getBindingKey();
                    $conditions = array_combine($foreignKey, $entity->extract($bindingKey));

                    foreach ($association->find('withTrashed')->where($conditions) as $related) {
                        if (
                            !$association
                                ->getTarget()
                                ->cascadingRestoreTrash($related, ['_primary' => false] + $options)
                        ) {
                            $result = false;
                        }
                    }
                }
            }
        }

        return $result;
    }

    /**
     * Returns a unary expression for bulk record manipulation.
     *
     * @return \Closure
     */
    protected function _getUnaryExpression(): Closure
    {
        return fn (QueryExpression $queryExpression): QueryExpression => $queryExpression
            ->add(new UnaryExpression(
                'IS NOT NULL',
                $this->getTrashField(false),
                UnaryExpression::POSTFIX
            ));
    }

    /**
     * Returns the table's field used to mark a `trashed` row.
     *
     * @param bool $aliased Should field be aliased or not. Default true.
     * @return string
     */
    public function getTrashField(bool $aliased = true): string
    {
        $field = $this->getConfig('field');

        if (empty($field)) {
            $columns = $this->_table->getSchema()->columns();
            foreach (['deleted', 'trashed'] as $name) {
                if (in_array($name, $columns, true)) {
                    $field = $name;
                    break;
                }
            }

            /** @psalm-suppress RedundantCondition */
            if (empty($field)) {
                $field = Configure::read('Muffin/Trash.field');
            }

            if (empty($field)) {
                throw new CakeException('TrashBehavior: "field" config needs to be provided.');
            }

            $this->setConfig('field', $field);
        }

        if ($aliased) {
            return $this->_table->aliasField($field);
        }

        return $field;
    }

    /**
     * Find out if an associated Table has the Trash behaviour and it's records can be trashed
     *
     * @param \Cake\ORM\Association $association The table association
     * @param \Cake\ORM\Table $table The table instance to check
     * @return bool
     */
    protected function _isRecursable(Association $association, Table $table): bool
    {
        return (
                $association->getTarget()->hasBehavior('Trash')
                || $association->getTarget()->hasBehavior(static::class)
            )
            && $association->isOwningSide($table)
            && $association->getDependent()
            && $association->getCascadeCallbacks();
    }
}
