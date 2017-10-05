<?php
namespace Muffin\Trash\Test\Fixture;

use Cake\TestSuite\Fixture\TestFixture;

class CommentsFixture extends TestFixture
{
    public $table = 'trash_comments';

    /**
     * fields property
     *
     * @var array
     */
    public $fields = [
        'id' => ['type' => 'integer'],
        'user_id' => ['type' => 'integer', 'null' => false],
        'article_id' => ['type' => 'integer', 'null' => false],
        'body' => ['type' => 'text', 'null' => false],
        'trashed' => ['type' => 'datetime', 'null' => true],
        'created' => ['type' => 'datetime', 'null' => true],
        '_constraints' => ['primary' => ['type' => 'primary', 'columns' => ['id']]]
    ];

    /**
     * records property
     *
     * @var array
     */
    public $records = [
        ['user_id' => 1, 'article_id' => 1, 'body' => 'Dummy text'],
        ['user_id' => 1, 'article_id' => 1, 'body' => 'Some other dummy text'],
        ['user_id' => 1, 'article_id' => 2, 'body' => 'Even more dummy text'],
    ];

    public function init()
    {
        $created = date('Y-m-d H:i:s');
        array_walk($this->records, function (&$record) use ($created) {
            $record += compact('created');
        });

        $this->records[1]['trashed'] = $created;
        parent::init();
    }
}
