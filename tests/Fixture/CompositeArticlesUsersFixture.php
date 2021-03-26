<?php
namespace Muffin\Trash\Test\Fixture;

use Cake\TestSuite\Fixture\TestFixture;

class CompositeArticlesUsersFixture extends TestFixture
{
    public $table = 'trash_composite_articles_users';

    /**
     * fields property
     *
     * @var array
     */
    public $fields = [
        'article_id' => ['type' => 'integer'],
        'user_id' => ['type' => 'integer'],
        'trashed' => ['type' => 'datetime', 'null' => true],
        '_constraints' => ['primary' => ['type' => 'primary', 'columns' => ['article_id', 'user_id']]],
    ];

    /**
     * records property
     *
     * @var array
     */
    public $records = [
        ['article_id' => 1, 'user_id' => 1, 'trashed' => null],
        ['article_id' => 3, 'user_id' => 1, 'trashed' => null],
    ];
}
