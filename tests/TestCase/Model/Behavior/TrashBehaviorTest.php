<?php
namespace Muffin\Trash\Test\TestCase\Model\Behavior;

use Cake\Core\Configure;
use Cake\Datasource\EntityInterface;
use Cake\Event\Event;
use Cake\ORM\Association\HasMany;
use Cake\ORM\Entity;
use Cake\ORM\TableRegistry;
use Cake\TestSuite\TestCase;
use Muffin\Trash\Model\Behavior\TrashBehavior;

/**
 * @property \Cake\ORM\Table Users
 * @property \Cake\ORM\Table CompositeArticlesUsers
 * @property \Cake\ORM\Table Comments
 * @property \Cake\ORM\Table Articles
 * @property \Muffin\Trash\Model\Behavior\TrashBehavior Behavior
 */
class TrashBehaviorTest extends TestCase
{
    /**
     * Fixtures to load.
     *
     * @var array
     */
    public $fixtures = [
        'plugin.Muffin/Trash.articles',
        'plugin.Muffin/Trash.comments',
        'plugin.Muffin/Trash.users',
        'plugin.Muffin/Trash.articles_users',
        'plugin.Muffin/Trash.composite_articles_users',
    ];

    /**
     * Runs before each test.
     *
     * @return void
     */
    public function setUp()
    {
        parent::setUp();

        $this->Users = TableRegistry::get('Muffin/Trash.Users', ['table' => 'trash_users']);
        $this->Users->belongsToMany('Articles', [
            'className' => 'Muffin/Trash.Articles',
            'joinTable' => 'trash_articles_users',
            'foreignKey' => 'user_id',
            'targetForeignKey' => 'article_id',
        ]);

        $this->CompositeArticlesUsers = TableRegistry::get('Muffin/Trash.CompositeArticlesUsers', ['table' => 'trash_composite_articles_users']);
        $this->CompositeArticlesUsers->addBehavior('Muffin/Trash.Trash');

        $this->Comments = TableRegistry::get('Muffin/Trash.Comments', ['table' => 'trash_comments']);
        $this->Comments->belongsTo('Articles', [
            'className' => 'Muffin/Trash.Articles',
            'foreignKey' => 'article_id',
        ]);
        $this->Comments->addBehavior('CounterCache', ['Articles' => [
            'comment_count',
            'total_comment_count' => ['finder' => 'withTrashed']
        ]]);
        $this->Comments->addBehavior('Muffin/Trash.Trash');

        $this->Articles = TableRegistry::get('Muffin/Trash.Articles', ['table' => 'trash_articles']);
        $this->Articles->addBehavior('Muffin/Trash.Trash');
        $this->Articles->hasMany('Comments', [
            'className' => 'Muffin/Trash.Comments',
            'foreignKey' => 'article_id',
            'sort' => ['Comments.id' => 'ASC'],
        ]);
        $this->Articles->belongsToMany('Users', [
            'className' => 'Muffin/Trash.Users',
            'joinTable' => 'trash_articles_users',
            'foreignKey' => 'article_id',
            'targetForeignKey' => 'user_id',
        ]);
        $this->Articles->hasMany('CompositeArticlesUsers', [
            'className' => 'Muffin/Trash.CompositeArticlesUsers',
            'foreignKey' => 'article_id',
        ]);

        $this->Behavior = $this->Articles->behaviors()->Trash;
    }

    /**
     * Runs after each test.
     *
     * @return void
     */
    public function tearDown()
    {
        parent::tearDown();
        TableRegistry::clear();
        unset($this->Users, $this->Comments, $this->Articles, $this->Behavior);
    }

    /**
     * Test the beforeFind callback.
     *
     * @return void
     */
    public function testBeforeFind()
    {
        $result = $this->Articles->find('all')->toArray();
        $this->assertCount(1, $result);
    }

    /**
     * Test the beforeDelete callback.
     *
     * @return void
     */
    public function testBeforeDelete()
    {
        $article = $this->Articles->get(1);
        $result = $this->Articles->delete($article);

        $this->assertTrue($result);
        $this->assertCount(3, $this->Articles->find('withTrashed'));
    }

    /**
     * Tests that the options passed to the `delete()` method are being passed on into
     * the cascading delete process.
     *
     * @return void
     */
    public function testDeleteOptionsArePassedToCascadingDeletes()
    {
        $association = $this->Articles->getAssociation('Comments');
        $association->setDependent(true);
        $association->setCascadeCallbacks(true);

        $hasDeleteOptionsBefore = false;
        $hasDeleteOptionsAfter = false;
        $this->Comments->getEventManager()->on(
            'Model.beforeDelete',
            ['priority' => 1],
            function (Event $event, EntityInterface $entity, \ArrayObject $options) use (&$hasDeleteOptionsBefore) {
                if (isset($options['deleteOptions'])) {
                    $hasDeleteOptionsBefore = true;
                }
            }
        );
        $this->Comments->getEventManager()->on(
            'Model.afterDelete',
            function (Event $event, EntityInterface $entity, \ArrayObject $options) use (&$hasDeleteOptionsAfter) {
                if (isset($options['deleteOptions'])) {
                    $hasDeleteOptionsAfter = true;
                }
            }
        );

        $article = $this->Articles->get(1);
        $result = $this->Articles->delete($article, [
            'deleteOptions' => true
        ]);

        $this->assertTrue($result);
        $this->assertTrue($hasDeleteOptionsBefore);
        $this->assertTrue($hasDeleteOptionsAfter);
    }

    /**
     * Tests that the options passed to the `delete()` method are being passed on into
     * the saving process.
     *
     * @return void
     */
    public function testDeleteOptionsArePassedToSave()
    {
        $association = $this->Articles->getAssociation('Comments');
        $association->setDependent(true);
        $association->setCascadeCallbacks(true);

        $mainHasDeleteOptions = false;
        $dependentHasDeleteOptions = false;
        $dependentIsNotPrimary = false;
        $this->Articles->getEventManager()->on(
            'Model.beforeSave',
            function (Event $event, EntityInterface $entity, \ArrayObject $options) use (&$mainHasDeleteOptions) {
                if (isset($options['deleteOptions'])) {
                    $mainHasDeleteOptions = true;
                }
            }
        );
        $this->Comments->getEventManager()->on(
            'Model.beforeSave',
            function (
                Event $event,
                EntityInterface $entity,
                \ArrayObject $options
            ) use (
                &$dependentHasDeleteOptions,
                &$dependentIsNotPrimary
            ) {
                if (isset($options['deleteOptions'])) {
                    $dependentHasDeleteOptions = true;
                }

                $dependentIsNotPrimary = $options['_primary'] === false;
            }
        );

        $article = $this->Articles->get(1);
        $result = $this->Articles->delete($article, [
            'deleteOptions' => true
        ]);

        $this->assertTrue($result);
        $this->assertTrue($mainHasDeleteOptions);
        $this->assertTrue($dependentHasDeleteOptions);
        $this->assertTrue($dependentIsNotPrimary);
    }

    /**
     * Test trash function with composite primary keys
     *
     * @return void
     */
    public function testTrashComposite()
    {
        $item = $this->CompositeArticlesUsers->get([3, 1]);
        $result = $this->CompositeArticlesUsers->trash($item);

        $this->assertTrue($result);
        $this->assertCount(1, $this->CompositeArticlesUsers->find('onlyTrashed'));
    }

    /**
     * Test trash function
     *
     * @return void
     */
    public function testTrash()
    {
        $article = $this->Articles->get(1);
        $result = $this->Articles->trash($article);

        $this->assertTrue($result);
        $this->assertCount(3, $this->Articles->find('withTrashed'));
    }

    /**
     * Test trash function with property not accessible
     *
     * @return void
     */
    public function testTrashNonAccessibleProperty()
    {
        $article = $this->Articles->get(1);
        $article->setAccess('trashed', false);
        $result = $this->Articles->trash($article);

        $this->assertTrue($result);
        $this->assertCount(3, $this->Articles->find('withTrashed'));
    }

    /**
     * Test it can find only trashed records.
     *
     * @return void
     */
    public function testFindOnlyTrashed()
    {
        $this->assertCount(2, $this->Articles->find('onlyTrashed'));
    }

    /**
     * Test it can find with trashed records.
     *
     * @return void
     */
    public function testFindWithTrashed()
    {
        $this->assertCount(3, $this->Articles->find('withTrashed'));
    }

    /**
     * Test it can empty all records from the trash.
     *
     * @return void
     */
    public function testEmptyTrash()
    {
        $this->Articles->emptyTrash();

        $this->assertCount(1, $this->Articles->find());
    }

    /**
     * Test it can restore all records in the trash.
     *
     * @return void
     */
    public function testRestoreTrash()
    {
        $this->Articles->restoreTrash();

        $this->assertCount(3, $this->Articles->find());
    }

    /**
     * Test it can trash all records.
     *
     * @return void
     */
    public function testTrashAll()
    {
        $this->assertCount(1, $this->Articles->find());

        $this->Articles->trashAll('1 = 1');
        $this->assertCount(0, $this->Articles->find());
    }

    /**
     * Test it can restore one record from the trash.
     *
     * @return void
     */
    public function testRestoreTrashEntity()
    {
        $this->Articles->restoreTrash(new Entity([
            'id' => 2,
        ], ['markNew' => false, 'markClean' => true]));

        $this->assertCount(2, $this->Articles->find());
    }

    /**
     * Test it can find records with a hasMany association.
     *
     * @return void
     */
    public function testFindingRecordWithHasManyAssoc()
    {
        $result = $this->Articles->get(1, ['contain' => ['Comments']]);
        $this->assertCount(1, $result->comments);
    }

    /**
     * Test it can find records with HABTM association.
     *
     * @return void
     */
    public function testFindingRecordWithBelongsToManyAssoc()
    {
        $result = $this->Users->get(1, ['contain' => ['Articles']]);
        $this->assertCount(1, $result->articles);
    }

    /**
     * Test that it can work alongside CounterCache behavior.
     *
     * @return void
     */
    public function testInteroperabilityWithCounterCache()
    {
        $comment = $this->Comments->get(1);
        $this->Comments->delete($comment);
        $result = $this->Articles->get(1);

        $this->assertEquals(0, $result->comment_count);
        $this->assertEquals(2, $result->total_comment_count);
    }

    /**
     * Test that it can work alongside CounterCache behavior and trash method.
     *
     * @return void
     */
    public function testInteroperabilityWithCounterCacheAndTrashMethod()
    {
        $comment = $this->Comments->get(1);
        $this->Comments->trash($comment);
        $result = $this->Articles->get(1);

        $this->assertEquals(0, $result->comment_count);
        $this->assertEquals(2, $result->total_comment_count);
    }

    /**
     * Ensure that when trashing it will cascade into related dependent records
     *
     * @return void
     */
    public function testCascadingTrash()
    {
        $association = $this->Articles->getAssociation('Comments');
        $association->setDependent(true);
        $association->setCascadeCallbacks(true);

        $article = $this->Articles->get(1);
        $this->Articles->trash($article);

        $article = $this->Articles->find('withTrashed')
            ->where(['Articles.id' => 1])
            ->contain(['Comments' => [
                'finder' => 'withTrashed'
            ]])
            ->first();

        $this->assertNotEmpty($article->trashed);
        $this->assertInstanceOf('Cake\I18n\Time', $article->trashed);

        $this->assertNotEmpty($article->comments[0]->trashed);
        $this->assertInstanceOf('Cake\I18n\Time', $article->comments[0]->trashed);
    }

    public function testCascadingUntrashOptionsArePassedToSave()
    {
        $association = $this->Articles->getAssociation('Comments');
        $association->setDependent(true);
        $association->setCascadeCallbacks(true);

        $this->Articles->Comments->getTarget()->trashAll([]);
        $this->assertEquals(0, $this->Articles->Comments->getTarget()->find()->count());

        $this->Articles->trashAll([]);
        $this->assertEquals(0, $this->Articles->find()->count());

        $article = $this->Articles
            ->find('withTrashed')
            ->where(['Articles.id' => 1])
            ->first();

        $mainHasRestoreOptions = false;
        $dependentHasRestoreOptions = false;
        $dependentIsNotPrimary = false;
        $this->Articles->getEventManager()->on(
            'Model.beforeSave',
            function (Event $event, EntityInterface $entity, \ArrayObject $options) use (&$mainHasRestoreOptions) {
                if (isset($options['restoreOptions'])) {
                    $mainHasRestoreOptions = true;
                }
            }
        );
        $this->Comments->getEventManager()->on(
            'Model.beforeSave',
            function (
                Event $event,
                EntityInterface $entity,
                \ArrayObject $options
            ) use (
                &$dependentHasRestoreOptions,
                &$dependentIsNotPrimary
            ) {
                if (isset($options['restoreOptions'])) {
                    $dependentHasRestoreOptions = true;
                }

                $dependentIsNotPrimary = $options['_primary'] === false;
            }
        );

        $result = $this->Articles->cascadingRestoreTrash($article, [
            'restoreOptions' => true
        ]);

        $this->assertInstanceOf('Cake\Datasource\EntityInterface', $result);
        $this->assertTrue($mainHasRestoreOptions);
        $this->assertTrue($dependentHasRestoreOptions);
        $this->assertTrue($dependentIsNotPrimary);
    }

    /**
     * Tests that cascading restore with an entity specified will restore that entity record,
     * and the dependent records.
     *
     * @return void
     */
    public function testCascadingUntrashEntity()
    {
        $association = $this->Articles->getAssociation('Comments');
        $association->setDependent(true);
        $association->setCascadeCallbacks(true);

        $association = $this->Articles->getAssociation('CompositeArticlesUsers');
        $association->setDependent(true);
        $association->setCascadeCallbacks(true);

        $this->Articles->Comments->getTarget()->trashAll([]);
        $this->assertEquals(0, $this->Articles->Comments->getTarget()->find()->count());

        $this->Articles->CompositeArticlesUsers->getTarget()->trashAll([]);
        $this->assertEquals(0, $this->Articles->CompositeArticlesUsers->getTarget()->find()->count());

        $this->Articles->trashAll([]);
        $this->assertEquals(0, $this->Articles->find()->count());

        $article = $this->Articles
            ->find('withTrashed')
            ->where(['Articles.id' => 1])
            ->contain([
                'Comments' => [
                    'finder' => 'withTrashed'
                ],
                'CompositeArticlesUsers' => [
                    'finder' => 'withTrashed'
                ],
            ])
            ->first();

        $this->assertNotEmpty($article->trashed);
        $this->assertInstanceOf('Cake\I18n\Time', $article->trashed);

        $this->assertNotEmpty($article->comments[0]->trashed);
        $this->assertInstanceOf('Cake\I18n\Time', $article->comments[0]->trashed);

        $this->assertNotEmpty($article->composite_articles_users[0]->trashed);
        $this->assertInstanceOf('Cake\I18n\Time', $article->composite_articles_users[0]->trashed);

        $unrelatedComment = $this->Articles->Comments->getTarget()->findById(3)->find('withTrashed')->first();
        $this->assertNotEquals($article->id, $unrelatedComment->article_id);
        $this->assertNotEmpty($unrelatedComment->trashed);
        $this->assertInstanceOf('Cake\I18n\Time', $unrelatedComment->trashed);

        $unrelatedArticleUser = $this->Articles->CompositeArticlesUsers->getTarget()->findByArticleId(3)->find('withTrashed')->first();
        $this->assertNotEquals($article->id, $unrelatedArticleUser->article_id);
        $this->assertNotEmpty($unrelatedArticleUser->trashed);
        $this->assertInstanceOf('Cake\I18n\Time', $unrelatedArticleUser->trashed);

        $this->assertInstanceOf('Cake\Datasource\EntityInterface', $this->Articles->cascadingRestoreTrash($article));

        $article = $this->Articles
            ->find()
            ->where(['Articles.id' => 1])
            ->contain(['Comments', 'CompositeArticlesUsers'])
            ->first();

        $this->assertEmpty($article->trashed);
        $this->assertEmpty($article->comments[0]->trashed);
        $this->assertEmpty($article->composite_articles_users[0]->trashed);

        $unrelatedComment = $this->Articles->Comments->getTarget()->findById(3)->find('withTrashed')->first();
        $this->assertNotEquals($article->id, $unrelatedComment->article_id);
        $this->assertNotEmpty($unrelatedComment->trashed);
        $this->assertInstanceOf('Cake\I18n\Time', $unrelatedComment->trashed);

        $unrelatedArticleUser = $this->Articles->CompositeArticlesUsers->getTarget()->findByArticleId(3)->find('withTrashed')->first();
        $this->assertNotEquals($article->id, $unrelatedArticleUser->article_id);
        $this->assertNotEmpty($unrelatedArticleUser->trashed);
        $this->assertInstanceOf('Cake\I18n\Time', $unrelatedArticleUser->trashed);
    }

    /**
     * Tests that cascading restore without specifying an entity will restore all records.
     *
     * @return void
     */
    public function testCascadingUntrashAll()
    {
        $association = $this->Articles->getAssociation('Comments');
        $association->setDependent(true);
        $association->setCascadeCallbacks(true);

        $association = $this->Articles->getAssociation('CompositeArticlesUsers');
        $association->setDependent(true);
        $association->setCascadeCallbacks(true);

        $this->Articles->Comments->getTarget()->trashAll([]);
        $this->assertEquals(0, $this->Articles->Comments->getTarget()->find()->count());

        $this->Articles->CompositeArticlesUsers->getTarget()->trashAll([]);
        $this->assertEquals(0, $this->Articles->CompositeArticlesUsers->getTarget()->find()->count());

        $this->Articles->trashAll([]);
        $this->assertEquals(0, $this->Articles->find()->count());

        $article = $this->Articles
            ->find('withTrashed')
            ->where(['Articles.id' => 1])
            ->contain([
                'Comments' => [
                    'finder' => 'withTrashed'
                ],
                'CompositeArticlesUsers' => [
                    'finder' => 'withTrashed'
                ],
            ])
            ->first();

        $this->assertNotEmpty($article->trashed);
        $this->assertInstanceOf('Cake\I18n\Time', $article->trashed);

        $this->assertNotEmpty($article->comments[0]->trashed);
        $this->assertInstanceOf('Cake\I18n\Time', $article->comments[0]->trashed);

        $this->assertNotEmpty($article->composite_articles_users[0]->trashed);
        $this->assertInstanceOf('Cake\I18n\Time', $article->composite_articles_users[0]->trashed);

        $this->assertEquals(8, $this->Articles->cascadingRestoreTrash());

        $article = $this->Articles
            ->find()
            ->where(['Articles.id' => 1])
            ->contain(['Comments', 'CompositeArticlesUsers'])
            ->first();

        $this->assertEmpty($article->trashed);
        $this->assertEmpty($article->comments[0]->trashed);
        $this->assertEmpty($article->composite_articles_users[0]->trashed);

        $this->assertEquals(3, $this->Articles->Comments->getTarget()->find()->count());
        $this->assertEquals(2, $this->Articles->CompositeArticlesUsers->getTarget()->find()->count());
        $this->assertEquals(3, $this->Articles->find()->count());
    }

    /**
     * Tests that cascading restore returns the expected value on failure.
     *
     * @return void
     */
    public function testCascadingUntrashFailure()
    {
        $association = $this->Articles->getAssociation('Comments');
        $association->setDependent(true);
        $association->setCascadeCallbacks(true);
        $association->getEventManager()->on('Model.beforeSave', function () {
            return false;
        });

        $association->getTarget()->trashAll([]);
        $this->assertEquals(0, $association->getTarget()->find()->count());

        $this->Articles->trashAll([]);
        $this->assertEquals(0, $this->Articles->find()->count());

        $article = $this->Articles
            ->find('withTrashed')
            ->where(['Articles.id' => 1])
            ->first();

        $this->assertFalse($this->Articles->cascadingRestoreTrash($article));
    }

    /**
     * Ensure that removing dependent records via the replace save strategy will trash those records
     *
     * @return void
     */
    public function testTrashDependentViaReplaceSaveStrategy()
    {
        $association = $this->Articles->getAssociation('Comments');
        $association->setDependent(true);
        $association->setCascadeCallbacks(true);
        $association->setSaveStrategy(HasMany::SAVE_REPLACE);

        $article = $this->Articles->get(1, [
            'contain' => ['Comments']
        ]);

        $this->assertEquals(1, $article->comments[0]->id);
        $this->assertEmpty($article->comments[0]->trashed);

        $article->set('comments', []);
        $article->setDirty('comments', true);

        $this->assertInstanceOf('Cake\Datasource\EntityInterface', $this->Articles->save($article));

        $article = $this->Articles
            ->find('withTrashed')
            ->where(['Articles.id' => 1])
            ->contain(['Comments' => [
                'finder' => 'withTrashed'
            ]])
            ->first();

        $this->assertNotEmpty($article->comments);
        $this->assertEquals(1, $article->comments[0]->id);
        $this->assertNotEmpty($article->comments[0]->trashed);
        $this->assertInstanceOf('Cake\I18n\Time', $article->comments[0]->trashed);
    }

    /**
     * Test that getTrashField() throws exception if "field" is not specified
     * and cannot be introspected.
     *
     * @expectedException RuntimeException
     * @return void
     */
    public function testGetTrashFieldException()
    {
        $trash = new TrashBehavior($this->Users);
        $trash->getTrashField();
    }

    /**
     * Test that getTrashField() uses configured value
     *
     * @return void
     */
    public function testGetTrashFieldUsesConfiguredValue()
    {
        $trash = new TrashBehavior($this->Users, ['field' => 'deleted']);
        $this->assertEquals('Users.deleted', $trash->getTrashField());

        Configure::write('Muffin/Trash.field', 'trashed');
        $trash = new TrashBehavior($this->Users);
        $this->assertEquals('Users.trashed', $trash->getTrashField());
    }

    /**
     * Test that getTrashField() uses a default value if no field is configured and that it sets the name of the field
     * in the config array.
     *
     * @return void
     */
    public function testGetTrashFieldFallbackToDefault()
    {
        $trash = new TrashBehavior($this->Articles, ['field' => '']);

        $this->assertEmpty($trash->getConfig('field'));
        $this->assertEquals('Articles.trashed', $trash->getTrashField());
        $this->assertEquals('trashed', $trash->getConfig('field'));
    }

    /**
     * Test that getTrashField() defaults to deleted or trashed
     * when found in schema and not specified
     *
     * @return void
     */
    public function testGetTrashFieldSchemaIntrospection()
    {
        $this->assertEquals(
            'Articles.trashed',
            $this->Articles->behaviors()->get('Trash')->getTrashField()
        );
    }

    /**
     * Test the implementedEvents method.
     *
     * @dataProvider provideConfigsForImplementedEventsTest
     * @param array $config Initial behavior config.
     * @param array $implementedEvents Expected implementedEvents.
     * @return void
     */
    public function testImplementedEvents(array $config, array $implementedEvents)
    {
        $trash = new TrashBehavior($this->Users, $config);

        $this->assertEquals($implementedEvents, $trash->implementedEvents());
    }

    /**
     * Provide configs for the implementedEvents test.
     *
     * @return array
     */
    public function provideConfigsForImplementedEventsTest()
    {
        return [
            'No event config inherits default events' => [
                [],
                [
                    'Model.beforeDelete' => [
                        'callable' => 'beforeDelete'
                    ],
                    'Model.beforeFind' => [
                        'callable' => 'beforeFind'
                    ],
                ],
            ],
            'Event config with empty array inherits default events' => [
                [
                    'events' => [],
                ],
                [
                    'Model.beforeDelete' => [
                        'callable' => 'beforeDelete'
                    ],
                    'Model.beforeFind' => [
                        'callable' => 'beforeFind'
                    ],
                ],
            ],
            'Event config with false disables default events' => [
                [
                    'events' => false,
                ],
                [],
            ],
            'Event config with event key as value' => [
                [
                    'events' => [
                        'Model.beforeDelete',
                    ],
                ],
                [
                    'Model.beforeDelete' => [
                        'callable' => 'beforeDelete',
                    ],
                ],
            ],
            'Event config with method name as value' => [
                [
                    'events' => [
                        'Model.beforeFind' => 'beforeFind',
                    ],
                ],
                [
                    'Model.beforeFind' => [
                        'callable' => 'beforeFind'
                    ],
                ],
            ],
            'Event config with callables' => [
                [
                    'events' => [
                        'Model.beforeDelete' => [
                            'callable' => function () {
                            },
                        ],
                        'Model.beforeFind' => [
                            'callable' => [$this, 'beforeDelete'],
                            'passParams' => true,
                        ],
                    ],
                ],
                [
                    'Model.beforeDelete' => [
                        'callable' => function () {
                        }
                    ],
                    'Model.beforeFind' => [
                        'callable' => [$this, 'beforeDelete'],
                        'passParams' => true,
                    ],
                ],
            ],
            'Event config with multiple options' => [
                [
                    'events' => [
                        'Model.beforeDelete' => [
                            'callable' => 'beforeDelete',
                            'passParams' => true,
                        ],
                    ],
                ],
                [
                    'Model.beforeDelete' => [
                        'callable' => 'beforeDelete',
                        'passParams' => true,
                    ],
                ],
            ],
            'Event config with default and event priorities' => [
                [
                    'priority' => 1,
                    'events' => [
                        'Model.beforeDelete',
                        'Model.beforeFind' => ['priority' => 5],
                    ],
                ],
                [
                    'Model.beforeDelete' => [
                        'callable' => 'beforeDelete',
                        'priority' => 1,
                    ],
                    'Model.beforeFind' => [
                        'callable' => 'beforeFind',
                        'priority' => 5,
                    ],
                ],
            ],
        ];
    }
}
