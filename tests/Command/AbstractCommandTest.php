<?php

namespace XmlSquad\Library\Tests\Command;

use XmlSquad\Library\Command\AbstractCommand;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Exception\FileNotFoundException;

final class AbstractCommandTest extends TestCase
{
    /**
     * @test
     */
    public function getConfigFilenameExceptionTest()
    {
        /** @var AbstractCommand $stub */
        $stub = $this->getMockForAbstractClass(AbstractCommand::class);
        $foo = self::getPrivateMethod($stub, 'getConfigFilename');
        self::expectException(FileNotFoundException::class);
        self::expectExceptionMessageRegExp('/Configuration file not found/');
        $foo->invoke($stub, 'config.yaml');
    }

    /**
     *
     *
     * Note this test is brittle because it assumes the tests
     * are invoked from a directory that contains a README file.
     *
     * @test
     * @dataProvider getConfigFilenameDataProvider
     */
    public function getConfigFilenameTest($configFilename, $expected)
    {
        /** @var AbstractCommand $stub */
        $stub = $this->getMockForAbstractClass(AbstractCommand::class);
        $foo = self::getPrivateMethod($stub, 'getConfigFilename');
        self::assertEquals(
            $expected,
            $foo->invoke($stub, $configFilename)
        );
    }

    /**
     * @return array
     */
    public function getConfigFilenameDataProvider()
    {
        return [
            ['README.md', getcwd() . '/README.md'],
            //['xml-authoring-library', getcwd()], //Fails when tests are invoked from anywhere other than a specified directory.
        ];
    }

    /**
     * @test
     * @dataProvider isReadableDirectoryDataProvider
     */
    public function isReadableDirectoryTest($directory, $expected)
    {
        /** @var AbstractCommand $stub */
        $stub = $this->getMockForAbstractClass(AbstractCommand::class);
        $foo = self::getPrivateMethod($stub, 'isReadableDirectory');
        self::assertEquals(
            $expected,
            $foo->invoke($stub, $directory)
        );
    }

    /**
     * @return array
     */
    public function isReadableDirectoryDataProvider()
    {
        return [
            ['/some/path', false],
            [realpath(__DIR__), true],
        ];
    }

    /**
     * @test
     */
    public function writeErrorTest()
    {
        $input = $this->createMock(InputInterface::class);
        $errorOutput = $this->createMock(OutputInterface::class);
        $errorOutput->expects($this->once())->method('writeln')->with('<error> \\<Test> </error>');
        $output = $this->createMock(ConsoleOutputInterface::class);
        $output->expects($this->once())->method('getErrorOutput')->willReturn($errorOutput);
        $output->expects($this->never())->method('writeln');

        $command = new class extends AbstractCommand {
            public function execute(InputInterface $input, OutputInterface $output) {
                $this->writeError($output, '<Test>');
            }
        };
        $command->setApplication(new Application());
        $command->execute($input, $output);
    }

    /**
     * Get a private or protected method for testing/documentation purposes.
     * How to use for MyClass->foo():
     *      $cls = new MyClass();
     *      $foo = self::getPrivateMethod($cls, 'foo');
     *      $foo->invoke($cls, $...);
     * @param object $obj The instantiated instance of your class
     * @param string $name The name of your private/protected method
     * @return \ReflectionMethod The method you asked for
     */
    public static function getPrivateMethod($obj, $name)
    {
        $class = new \ReflectionClass($obj);
        $method = $class->getMethod($name);
        $method->setAccessible(true);
        return $method;
    }
}
