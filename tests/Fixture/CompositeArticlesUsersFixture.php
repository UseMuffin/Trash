<?php
namespace Muffin\Trash\Test\Fixture;

use Cake\TestSuite\Fixture\TestFixture;

class CompositeArticlesUsersFixture extends TestFixture
{
    public string $table = 'trash_composite_articles_users';

    /**
     * records property
     *
     * @var array
     */
    public array $records = [
        ['article_id' => 1, 'user_id' => 1, 'trashed' => null],
        ['article_id' => 3, 'user_id' => 1, 'trashed' => null],
    ];
}
