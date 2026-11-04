<?php

namespace Demo\Tests;

use Demo\Config;
use PHPUnit\Framework\TestCase;

final class ConfigTest extends TestCase
{
    public function testParsesEnvFile(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'env');
        file_put_contents($file, "# comment\nA=1\n\nB = \"two words\"\nC=x=y\nD='quoted'\ninvalid line\n");
        $env = Config::parseEnvFile($file);
        unlink($file);
        $this->assertSame(['A' => '1', 'B' => 'two words', 'C' => 'x=y', 'D' => 'quoted'], $env);
    }

    public function testMissingEnvFileIsEmpty(): void
    {
        $this->assertSame([], Config::parseEnvFile('/nonexistent/.env'));
    }

    public function testLoadsTheCatalogFromThePrivateDirectory(): void
    {
        $config = Config::load(__DIR__ . '/../private');
        $this->assertSame('DeferredCredential', $config->credential('deferred')['credentialId']);
        $this->assertArrayHasKey('baseUrl', $config->issuer('sandbox'));
    }
}
