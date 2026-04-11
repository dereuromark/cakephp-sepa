<?php

declare(strict_types=1);

namespace Sepa\Test\TestCase;

use PHPUnit\Framework\TestCase;
use Sepa\Plugin;

/**
 * Sanity-check that the main plugin class loads.
 */
class PluginTest extends TestCase
{
    public function testPluginCanBeInstantiated(): void
    {
        $plugin = new Plugin();
        $this->assertInstanceOf(Plugin::class, $plugin);
    }
}
