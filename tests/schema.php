<?php
declare(strict_types=1);

return [
    [
        'table' => 'trash_articles',
        'columns' => [
            'id' => ['type' => 'integer'],
            'title' => ['type' => 'string', 'null' => false],
            'sub_title' => ['type' => 'string', 'null' => false],
            'comment_count' => ['type' => 'integer', 'null' => true],
            'total_comment_count' => ['type' => 'integer', 'null' => true],
            'trashed' => ['type' => 'datetime', 'null' => true],
            'created' => ['type' => 'datetime', 'null' => true],
            'modified' => ['type' => 'datetime', 'null' => true],
        ],
        'constraints' => [
            'primary' => ['type' => 'primary', 'columns' => ['id']],
        ],
    ],
    [
        'table' => 'trash_articles_users',
        'columns' => [
            'id' => ['type' => 'integer'],
            'article_id' => ['type' => 'integer'],
            'user_id' => ['type' => 'integer'],
        ],
        'constraints' => [
            'primary' => ['type' => 'primary', 'columns' => ['id']],
        ],
    ],
    [
        'table' => 'trash_comments',
        'columns' => [
            'id' => ['type' => 'integer'],
            'user_id' => ['type' => 'integer', 'null' => false],
            'article_id' => ['type' => 'integer', 'null' => false],
            'body' => ['type' => 'text', 'null' => false],
            'trashed' => ['type' => 'datetime', 'null' => true],
            'created' => ['type' => 'datetime', 'null' => true],
        ],
        'constraints' => [
            'primary' => ['type' => 'primary', 'columns' => ['id']],
        ],
    ],
    [
        'table' => 'trash_composite_articles_users',
        'columns' => [
            'article_id' => ['type' => 'integer'],
            'user_id' => ['type' => 'integer'],
            'trashed' => ['type' => 'datetime', 'null' => true],
        ],
        'constraints' => [
            'primary' => ['type' => 'primary', 'columns' => ['article_id', 'user_id']],
        ],
    ],
    [
        'table' => 'trash_users',
        'columns' => [
            'id' => ['type' => 'integer'],
            'name' => ['type' => 'string', 'null' => false],
            'created' => ['type' => 'datetime', 'null' => true],
        ],
        'constraints' => [
            'primary' => ['type' => 'primary', 'columns' => ['id']],
        ],
    ],
];
