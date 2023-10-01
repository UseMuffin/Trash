<?php
namespace Muffin\Trash\Test\Fixture;

use Cake\I18n\Time;
use Cake\TestSuite\Fixture\TestFixture;

class ArticlesFixture extends TestFixture
{
    public string $table = 'trash_articles';

    /**
     * records property
     *
     * @var array
     */
    public array $records = [
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
