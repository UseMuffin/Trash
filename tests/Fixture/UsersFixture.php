<?php
namespace Muffin\Trash\Test\Fixture;

use Cake\TestSuite\Fixture\TestFixture;

class UsersFixture extends TestFixture
{
    public $table = 'trash_users';

    /**
     * fields property
     *
     * @var array
     */
    public $fields = [
        'id' => ['type' => 'integer'],
        'name' => ['type' => 'string', 'null' => false],
        'created' => ['type' => 'datetime', 'null' => true],
        '_constraints' => ['primary' => ['type' => 'primary', 'columns' => ['id']]]
    ];

    /**
     * records property
     *
     * @var array
     */
    public $records = [
        ['name' => 'Dummy'],
    ];

    public function init()
    {
        $created = date('Y-m-d H:i:s');
        array_walk($this->records, function (&$record) use ($created) {
            $record += compact('created');
        });
        parent::init();
    }
}
