<?php
namespace Muffin\Trash\Test\Fixture;

use Cake\TestSuite\Fixture\TestFixture;

class ArticlesUsersFixture extends TestFixture
{
    public $table = 'trash_articles_users';

    /**
     * fields property
     *
     * @var array
     */
    public $fields = [
        'id' => ['type' => 'integer'],
        'article_id' => ['type' => 'integer'],
        'user_id' => ['type' => 'integer'],
        '_constraints' => ['primary' => ['type' => 'primary', 'columns' => ['id']]],
    ];

    /**
     * records property
     *
     * @var array
     */
    public $records = [
        ['article_id' => 1, 'user_id' => 1],
        ['article_id' => 3, 'user_id' => 1],
    ];
}
