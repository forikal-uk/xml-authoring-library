<?php

namespace XmlSquad\Library\Console;

use Psr\Log\AbstractLogger;
use Psr\Log\LogLevel;
use Symfony\Component\Console\Formatter\OutputFormatter;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Output\ConsoleOutputInterface;

/**
 * Logs messages to a console output and follows the PSR log interface.
 *
 * In contrast to the default Symfony ConsoleLogger it has more flexibility (we can change its source code)
 *
 * @author Surgie Finesse
 */
class ConsoleLogger extends AbstractLogger
{
    /**
     * @var OutputInterface The output where to print messages
     */
    protected $output;

    /**
     * @var int[] A map telling what verbosity level each log level corresponds
     */
    protected $verbosityLevelMap = [
        LogLevel::EMERGENCY => OutputInterface::VERBOSITY_NORMAL,
        LogLevel::ALERT => OutputInterface::VERBOSITY_NORMAL,
        LogLevel::CRITICAL => OutputInterface::VERBOSITY_NORMAL,
        LogLevel::ERROR => OutputInterface::VERBOSITY_NORMAL,
        LogLevel::WARNING => OutputInterface::VERBOSITY_NORMAL,
        LogLevel::NOTICE => OutputInterface::VERBOSITY_VERBOSE,
        LogLevel::INFO => OutputInterface::VERBOSITY_VERY_VERBOSE,
        LogLevel::DEBUG => OutputInterface::VERBOSITY_DEBUG,
    ];

    /**
     * @var string[] A map telling what format should be applied to each log level
     */
    protected $formatLevelMap = [
        LogLevel::EMERGENCY => 'error',
        LogLevel::ALERT => 'error',
        LogLevel::CRITICAL => 'error',
        LogLevel::ERROR => 'error',
        LogLevel::WARNING => 'info',
        LogLevel::NOTICE => 'info',
        LogLevel::INFO => 'info',
        LogLevel::DEBUG => '',
    ];

    /**
     * @param OutputInterface $output The output where to print messages
     * @param int[] $verbosityLevelMap A map telling what verbosity level each log level corresponds
     * @param string[] $formatLevelMap A map telling what format should be applied to each log level. A format can be an
     *     empty string (it means no format).
     */
    public function __construct(OutputInterface $output, array $verbosityLevelMap = [], array $formatLevelMap = [])
    {
        $this->output = $output;
        $this->verbosityLevelMap = $verbosityLevelMap + $this->verbosityLevelMap;
        $this->formatLevelMap = $formatLevelMap + $this->formatLevelMap;
    }

    /**
     * {@inheritdoc}
     */
    public function log($level, $message, array $context = [])
    {
        if (!isset($this->verbosityLevelMap[$level])) {
            throw new \InvalidArgumentException(sprintf('The log level "%s" does not exist.', $level));
        }

        $output = $this->output;

        // Write to the error output if necessary and available
        if (in_array($this->formatLevelMap[$level], [LogLevel::ERROR, LogLevel::CRITICAL, LogLevel::ALERT, LogLevel::EMERGENCY])) {
            if ($output instanceof ConsoleOutputInterface) {
                $output = $output->getErrorOutput();
            }
        }

        // the if condition check isn't necessary -- it's the same one that $output will do internally anyway.
        // We only do it for efficiency here as the message formatting is relatively expensive.
        if ($output->getVerbosity() >= $this->verbosityLevelMap[$level]) {
            $format = $this->formatLevelMap[$level];
            $message = $this->messageToString($level, $message, $context);

            if ($format === '' || $format === null) {
                $output->writeln($message, OutputInterface::OUTPUT_PLAIN);
            } else {
                $output->writeln(sprintf('<%s>%s</%s>', $format, OutputFormatter::escape($message), $format));
            }
        }
    }

    /**
     * Converts a log message and its context to a string.
     *
     * Arguments are the same as in the `log` method.
     *
     * @return string
     */
    protected function messageToString($level, $message, array $context)
    {
        if (!empty($context)) {
            $message .= ' '.json_encode($context, JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR);
        }

        return $message;
    }
}
