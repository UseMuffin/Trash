<?php
namespace Muffin\Trash\Test\Fixture;

use Cake\I18n\Time;
use Cake\TestSuite\Fixture\TestFixture;

class ArticlesFixture extends TestFixture
{
    public $table = 'trash_articles';

    /**
     * fields property
     *
     * @var array
     */
    public $fields = [
        'id' => ['type' => 'integer'],
        'title' => ['type' => 'string', 'null' => false],
        'sub_title' => ['type' => 'string', 'null' => false],
        'comment_count' => ['type' => 'integer', 'null' => true],
        'total_comment_count' => ['type' => 'integer', 'null' => true],
        'trashed' => ['type' => 'datetime', 'null' => true],
        'created' => ['type' => 'datetime', 'null' => true],
        'modified' => ['type' => 'datetime', 'null' => true],
        '_constraints' => ['primary' => ['type' => 'primary', 'columns' => ['id']]],
    ];

    /**
     * records property
     *
     * @var array
     */
    public $records = [
        ['title' => 'First Article', 'sub_title' => 'subtitle 1'],
        ['title' => 'Second Article', 'sub_title' => 'subtitle 2'],
        ['title' => 'Third Article', 'sub_title' => 'subtitle 3'],
    ];

    public function init(): void
    {
        $created = $modified = new Time();
        array_walk($this->records, function (&$record) use ($created, $modified) {
            $record += compact('created', 'modified');
        });

        $this->records[1]['trashed'] = $created;
        $this->records[2]['trashed'] = $created;
        parent::init();
    }
}
