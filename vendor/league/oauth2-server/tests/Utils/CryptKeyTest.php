<?php

namespace LeagueTests\Utils;

use League\OAuth2\Server\CryptKey;
use PHPUnit\Framework\TestCase;

class CryptKeyTest extends TestCase
{
    /**
     * @expectedException \LogicException
     */
    public function testNoFile()
    {
        new CryptKey('undefined file');
    }

    public function testKeyCreation()
    {
        $keyFile = __DIR__ . '/../Stubs/public.key';
        $key = new CryptKey($keyFile, 'secret');

        $this->assertEquals('file://' . $keyFile, $key->getKeyPath());
        $this->assertEquals('secret', $key->getPassPhrase());
    }

    public function testKeyFileCreation()
    {
        $keyContent = file_get_contents(__DIR__ . '/../Stubs/public.key');
        $key = new CryptKey($keyContent);

        $this->assertEquals(
            'file://' . sys_get_temp_dir() . '/' . sha1($keyContent) . '.key',
            $key->getKeyPath()
        );

        $keyContent = file_get_contents(__DIR__ . '/../Stubs/private.key.crlf');
        $key = new CryptKey($keyContent);

        $this->assertEquals(
            'file://' . sys_get_temp_dir() . '/' . sha1($keyContent) . '.key',
            $key->getKeyPath()
        );
    }
}
