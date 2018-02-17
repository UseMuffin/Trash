<?php
namespace Muffin\Trash\Test\TestCase\Panel;

use Cake\Core\Configure;
use Cake\Datasource\EntityInterface;
use Cake\Event\Event;
use Cake\ORM\Association\HasMany;
use Cake\ORM\Entity;
use Cake\ORM\TableRegistry;
use Cake\TestSuite\TestCase;
use Muffin\Trash\Model\Behavior\TrashBehavior;
use Muffin\Trash\Panel\TrashPanel;

/**
 * @property \Cake\ORM\Table Users
 * @property \Cake\ORM\Table CompositeArticlesUsers
 * @property \Cake\ORM\Table Comments
 * @property \Cake\ORM\Table Articles
 * @property \Muffin\Trash\Model\Behavior\TrashBehavior Behavior
 */
class TrashPanelTest extends TestCase
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

    public $tableConfig = [
        'Muffin/Trash' => [
            'field' => 'trashed',
            'panel' => [
                'tables' => [
                    'Articles',
                    'Comments',
                    'Users',
                    'ArticlesUsers',
                    'CompositeArticlesUsers',
                ],
            ],
        ],
    ];

    /**
     * Runs before each test.
     *
     * @return void
     */
    public function setUp()
    {
        parent::setUp();
        Configure::write('Muffin/Trash', $this->tableConfig['Muffin/Trash']);

        $this->TrashPanel = new TrashPanel();

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
     * Test the countTrashed method
     *
     * @return void
     */
    public function testCountTrashed()
    {
        $article = $this->Articles->get(1);
        $result = $this->Articles->delete($article);

        $this->assertEquals(3, $this->TrashPanel->countTrashed($this->Articles));
        $this->assertEquals(
            $this->Articles->countTrashed(),
            $this->TrashPanel->countTrashed($this->Articles)
        );
    }

    /**
     * Test it returns zero when all records are emptied from the trash.
     *
     * @return void
     */
    public function testCountTrashedAfterEmptyTrash()
    {
        $this->Articles->emptyTrash();

        $this->assertEquals(0, $this->TrashPanel->countTrashed($this->Articles));
    }

    /**
     * Test it counts appropriately when all records in the trash are restored
     *
     * @return void
     */
    public function testCountTrashedAfterRestoreTrash()
    {
        $this->Articles->restoreTrash();

        $this->assertEquals(0, $this->TrashPanel->countTrashed($this->Articles));
    }
}
