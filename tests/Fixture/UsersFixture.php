<?php
namespace Muffin\Trash\Test\Fixture;

use Cake\TestSuite\Fixture\TestFixture;

class UsersFixture extends TestFixture
{
    public string $table = 'trash_users';

    /**
     * records property
     *
     * @var array
     */
    public array $records = [
        ['name' => 'Dummy'],
    ];

    public function init(): void
    {
        $created = date('Y-m-d H:i:s');
        array_walk($this->records, function (&$record) use ($created) {
            $record += compact('created');
        });
        parent::init();
    }
}
