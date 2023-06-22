<?php
declare(strict_types=1);

namespace Muffin\Trash;

use Cake\Core\BasePlugin;

/**
 * Plugin for Muffin Trash - Soft Delete
 */
class TrashPlugin extends BasePlugin
{
    /**
     * Disable routes hook.
     *
     * @var bool
     */
    protected bool $routesEnabled = false;

    /**
     * Disable middleware hook.
     *
     * @var bool
     */
    protected bool $middlewareEnabled = false;

    /**
     * Disable console hook.
     *
     * @var bool
     */
    protected bool $consoleEnabled = false;

    /**
     * Disable console hook.
     *
     * @var bool
     */
    protected bool $bootstrapEnabled = false;
}
