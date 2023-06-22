<?php
namespace Muffin\Trash\Test\Fixture;

use Cake\TestSuite\Fixture\TestFixture;

class CommentsFixture extends TestFixture
{
    public string $table = 'trash_comments';

    /**
     * records property
     *
     * @var array
     */
    public array $records = [
        ['user_id' => 1, 'article_id' => 1, 'body' => 'Dummy text'],
        ['user_id' => 1, 'article_id' => 1, 'body' => 'Some other dummy text'],
        ['user_id' => 1, 'article_id' => 2, 'body' => 'Even more dummy text'],
    ];

    public function init(): void
    {
        $created = date('Y-m-d H:i:s');
        array_walk($this->records, function (&$record) use ($created) {
            $record += compact('created');
        });

        $this->records[1]['trashed'] = $created;
        parent::init();
    }
}
