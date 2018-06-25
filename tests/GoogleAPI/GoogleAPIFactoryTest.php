<?php

namespace XmlSquad\Library\Tests\GoogleAPI;

use XmlSquad\Library\GoogleAPI\GoogleAPIFactory;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class GoogleAPIFactoryTest extends TestCase
{
    public function testMake()
    {
        $factory = new GoogleAPIFactory();

        $client = $factory->make();
        $this->assertAttributeInstanceOf(NullLogger::class, 'logger', $client);

        $logger = new NullLogger();
        $client = $factory->make($logger);
        $this->assertAttributeSame($logger, 'logger', $client);
    }
}
