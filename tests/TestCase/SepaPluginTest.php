<?php

declare(strict_types=1);

namespace Sepa\Test\TestCase;

use PHPUnit\Framework\TestCase;
use Sepa\SepaPlugin;

/**
 * Sanity-check that the main plugin class loads.
 */
class SepaPluginTest extends TestCase
{
    public function testSepaPluginCanBeInstantiated(): void
    {
        $plugin = new SepaPlugin();
        $this->assertInstanceOf(SepaPlugin::class, $plugin);
    }
}
