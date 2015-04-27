<?php
namespace Muffin\Trash\Test\TestCase\Model\Behavior;

use Cake\ORM\Entity;
use Cake\ORM\TableRegistry;
use Cake\TestSuite\TestCase;

class TrashBehaviorTest extends TestCase
{

    public $fixtures = [
        'plugin.Muffin/Trash.articles',
        'plugin.Muffin/Trash.comments',
        'plugin.Muffin/Trash.users',
        'plugin.Muffin/Trash.articles_users',
    ];

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

    public function tearDown()
    {
        parent::tearDown();
        TableRegistry::clear();
        unset($this->Articles, $this->Behavior);
    }

    public function testBeforeFind()
    {
        $result = $this->Articles->find('all')->toArray();
        $this->assertCount(1, $result);
    }

    public function testBeforeDelete()
    {
        $article = $this->Articles->get(1);
        $result = $this->Articles->delete($article);

        $this->assertCount(3, $this->Articles->find('withTrashed'));
    }

    public function testFindOnlyTrashed()
    {
        $this->assertCount(2, $this->Articles->find('onlyTrashed'));
    }

    public function testFindWithTrashed()
    {
        $this->assertCount(3, $this->Articles->find('withTrashed'));
    }

    public function testEmptyTrash()
    {
        $this->Articles->emptyTrash();

        $this->assertCount(1, $this->Articles->find());
    }

    public function testRestoreTrashAll()
    {
        $this->Articles->restoreTrash();

        $this->assertCount(3, $this->Articles->find());
    }

    public function testRestoreTrashEntity()
    {
        $this->Articles->restoreTrash(new Entity([
            'id' => 2,
        ], ['markNew' => false, 'markClean' => true]));

        $this->assertCount(2, $this->Articles->find());
    }

    public function testFindingRecordWithHasManyAssoc()
    {
        $result = $this->Articles->get(1, ['contain' => ['Comments']]);
        $this->assertCount(1, $result->comments);
    }

    public function testFindingRecordWithBelongsToManyAssoc()
    {
        $result = $this->Users->get(1, ['contain' => ['Articles']]);
        $this->assertCount(1, $result->articles);
    }
}
