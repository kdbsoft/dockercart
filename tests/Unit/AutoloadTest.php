<?php
declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class AutoloadTest extends TestCase
{
    public function testComposerAutoloaderWorks(): void
    {
        $this->assertTrue(class_exists(\Twig\Environment::class));
        $this->assertTrue(class_exists(\ScssPhp\ScssPhp\Compiler::class));
    }

    public function testPhpVersionMeetsRequirement(): void
    {
        $this->assertTrue(PHP_VERSION_ID >= 80500, 'PHP 8.5+ required');
    }

    public function testVersionFileExists(): void
    {
        $this->assertFileExists(__DIR__ . '/../../VERSION');
    }
}
