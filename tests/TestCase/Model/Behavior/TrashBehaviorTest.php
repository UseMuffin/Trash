<?php
namespace Muffin\Trash\Test\TestCase\Model\Behavior;

use Cake\ORM\Entity;
use Cake\ORM\TableRegistry;
use Cake\TestSuite\TestCase;
use Muffin\Trash\Model\Behavior\TrashBehavior;

/**
 * @property \Cake\ORM\Table Users
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
            'foreignKey' => 'article_id'
        ]);
        $this->Articles->belongsToMany('Users', [
            'className' => 'Muffin/Trash.Users',
            'joinTable' => 'trash_articles_users',
            'foreignKey' => 'article_id',
            'targetForeignKey' => 'user_id',
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
            '@inheritDefaults' => [
                '$config' => [],
                '$implementedEvents' => [
                    'Model.beforeDelete' => [
                        'callable' => 'beforeDelete'
                    ],
                    'Model.beforeFind' => [
                        'callable' => 'beforeFind'
                    ],
                ],
            ],
            '@inheritDefaultsWhenEmptyArray' => [
                '$config' => [
                    'events' => [],
                ],
                '$implementedEvents' => [
                    'Model.beforeDelete' => [
                        'callable' => 'beforeDelete'
                    ],
                    'Model.beforeFind' => [
                        'callable' => 'beforeFind'
                    ],
                ],
            ],
            '@disableDefaultsWhenFalse' => [
                '$config' => [
                    'events' => false,
                ],
                '$implementedEvents' => [],
            ],
            '@numericArray' => [
                '$config' => [
                    'events' => [
                        'Model.beforeDelete',
                    ],
                ],
                '$implementedEvents' => [
                    'Model.beforeDelete' => [
                        'callable' => 'beforeDelete',
                    ],
                ],
            ],
            '@assocArray' => [
                '$config' => [
                    'events' => [
                        'Model.beforeFind' => 'beforeFind',
                    ],
                ],
                '$implementedEvents' => [
                    'Model.beforeFind' => [
                        'callable' => 'beforeFind'
                    ],
                ],
            ],
            '@callables' => [
                '$config' => [
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
                '$implementedEvents' => [
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
            '@multipleParams' => [
                '$config' => [
                    'events' => [
                        'Model.beforeDelete' => [
                            'callable' => 'beforeDelete',
                            'passParams' => true,
                        ],
                    ],
                ],
                '$implementedEvents' => [
                    'Model.beforeDelete' => [
                        'callable' => 'beforeDelete',
                        'passParams' => true,
                    ],
                ],
            ],
            '@priority' => [
                '$config' => [
                    'priority' => 1,
                    'events' => [
                        'Model.beforeDelete',
                        'Model.beforeFind' => ['priority' => 5],
                    ],
                ],
                '$implementedEvents' => [
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
