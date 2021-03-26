<?php
declare(strict_types=1);

namespace Muffin\Trash;

use Cake\Core\BasePlugin;

/**
 * Plugin for Muffin Trash - Soft Delete
 */
class Plugin extends BasePlugin
{
    /**
     * Disable routes hook.
     *
     * @var bool
     */
    protected $routesEnabled = false;

    /**
     * Disable middleware hook.
     *
     * @var bool
     */
    protected $middlewareEnabled = false;

    /**
     * Disable console hook.
     *
     * @var bool
     */
    protected $consoleEnabled = false;

    /**
     * Disable console hook.
     *
     * @var bool
     */
    protected $bootstrapEnabled = false;
}
