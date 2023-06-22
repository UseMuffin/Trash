<?php
namespace Muffin\Trash\Test\Fixture;

use Cake\TestSuite\Fixture\TestFixture;

class ArticlesUsersFixture extends TestFixture
{
    public string $table = 'trash_articles_users';

    /**
     * records property
     *
     * @var array
     */
    public array $records = [
        ['article_id' => 1, 'user_id' => 1],
        ['article_id' => 3, 'user_id' => 1],
    ];
}
