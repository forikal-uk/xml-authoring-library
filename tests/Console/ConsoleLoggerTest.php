<?php

namespace Forikal\Library\Tests\Console;

use Forikal\Library\Console\ConsoleLogger;
use PHPUnit\Framework\TestCase;
use Psr\Log\LogLevel;
use Symfony\Component\Console\Formatter\OutputFormatter;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ConsoleLoggerTest extends TestCase
{
    /**
     * Checks if message text and context is printed correctly
     *
     * @dataProvider messageFormatProvider
     */
    public function testMessageFormat($message, $context, $result)
    {
        $output = $this->getMock(OutputInterface::class);
        $output
            ->expects($this->once())
            ->method('writeln')
            ->with(
                $this->equalTo($result),
                $this->equalTo(OutputInterface::OUTPUT_PLAIN)
            );
        $output
            ->method('getVerbosity')
            ->willReturn(OutputInterface::VERBOSITY_DEBUG);

        $logger = new ConsoleLogger($output, [], [LogLevel::DEBUG => '']);
        $logger->debug($message, $context);
    }

    public function messageFormatProvider()
    {
        return [
            ['Hello', [], 'Hello'],
            ['A text with <info>special</info> characters', [], 'A text with <info>special</info> characters'],
            ['With context', ['foo' => [1, 2], 'baz' => (object)['hello' => 'world']], 'With context {"foo":[1,2],"baz":{"hello":"world"}}']
        ];
    }

    /**
     * Checks that the exception is thrown when a wrong log level is given
     */
    public function testInvalidLogLevel()
    {
        $output = $this->getMock(OutputInterface::class);
        $logger = new ConsoleLogger($output);

        $this->setExpectedException('InvalidArgumentException', 'The log level "foo" does not exist.');
        $logger->log('foo', 'bar');
    }

    /**
     * Checks that the verbosity level map works
     *
     * @dataProvider verbosityLevelMapProvider
     */
    public function testVerbosityLevelMap($outputVerbosity, $verbosityMap, $level, $shouldBePrinted)
    {
        $output = $this->getMock(OutputInterface::class);
        $output
            ->method('getVerbosity')
            ->willReturn($outputVerbosity);
        $output
            ->expects($shouldBePrinted ? $this->once() : $this->never())
            ->method('writeln');

        $logger = new ConsoleLogger($output, $verbosityMap);
        $logger->$level('Hello');
    }

    public function verbosityLevelMapProvider()
    {
        return [
            [OutputInterface::VERBOSITY_NORMAL, [LogLevel::INFO => OutputInterface::VERBOSITY_NORMAL], 'info', true],
            [OutputInterface::VERBOSITY_NORMAL, [LogLevel::INFO => OutputInterface::VERBOSITY_VERBOSE], 'info', false],
            [OutputInterface::VERBOSITY_DEBUG, [LogLevel::NOTICE => OutputInterface::VERBOSITY_VERBOSE], 'notice', true],
        ];
    }

    /**
     * Tests that the format level map works and a format is applied
     *
     * @dataProvider formatLevelMapProvider
     */
    public function testFormatLevelMap($formatMap, $level, $message, $resultMessage, $resultOptions = null)
    {
        $output = $this->getMock(OutputInterface::class);
        $output
            ->method('getVerbosity')
            ->willReturn(OutputInterface::VERBOSITY_DEBUG);
        call_user_func_array([
            $output
                ->expects($this->once())
                ->method('writeln'),
                'with'
        ], $resultOptions === null ? [$resultMessage] : [$resultMessage, $resultOptions]); // call_user_func_array can be replaced with argument unpacking since PHP 5.6

        $logger = new ConsoleLogger($output, [], $formatMap);
        $logger->$level($message);
    }

    public function formatLevelMapProvider()
    {
        return [
            [[LogLevel::WARNING => 'error'], 'warning', 'To the moon', '<error>To the moon</error>'],
            [[LogLevel::DEBUG => ''], 'debug', 'Hello, <foo>', 'Hello, <foo>', OutputInterface::OUTPUT_PLAIN],
            [[LogLevel::CRITICAL => 'error'], 'critical', '<error>Serious!</error>', '<error>'.OutputFormatter::escape('<error>Serious!</error>').'</error>'],
        ];
    }

    /**
     * Checks that error messages are printed to STDERR
     *
     * @dataProvider errorOutputProvider
     */
    public function testErrorOutput($level, $isError)
    {
        $output = $this->getMock(ConsoleOutputInterface::class);
        $output->method('getVerbosity')->willReturn(OutputInterface::VERBOSITY_DEBUG);

        if ($isError) {
            $errorOutput = $this->getMock(OutputInterface::class);
            $errorOutput->method('getVerbosity')->willReturn(OutputInterface::VERBOSITY_DEBUG);
            $errorOutput->expects($this->once())->method('writeln');

            $output->expects($this->atLeastOnce())->method('getErrorOutput')->willReturn($errorOutput);
            $output->expects($this->never())->method('writeln');
        } else {
            $output->expects($this->never())->method('getErrorOutput');
            $output->expects($this->once())->method('writeln');
        }

        $logger = new ConsoleLogger($output);
        $logger->$level('Test');
    }

    public function errorOutputProvider()
    {
        return [
            [LogLevel::EMERGENCY, true],
            [LogLevel::ALERT, true],
            [LogLevel::CRITICAL, true],
            [LogLevel::ERROR, true],
            [LogLevel::WARNING, false],
            [LogLevel::NOTICE, false],
            [LogLevel::INFO, false],
            [LogLevel::DEBUG, false]
        ];
    }
}
