<?php

namespace Forikal\Library\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Exception\FileNotFoundException;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Yaml\Parser;

/**
 * Class AbstractCommand
 */
abstract class AbstractCommand extends Command
{
    const DEFAULT_CONFIG_FILENAME = 'scapesettings.yml';

    /**
     * @var Filesystem
     */
    protected $filesystem;

    /**
     * AbstractForikalCommand constructor.
     * @param null $name
     * @param Filesystem|null $filesystem
     */
    public function __construct($name = null, Filesystem $filesystem = null)
    {
        parent::__construct($name);

        $this->filesystem = $filesystem ?? new Filesystem();
    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->addOption('configFilename', 'c', InputOption::VALUE_REQUIRED, 'Name of configuration file', self::DEFAULT_CONFIG_FILENAME)
        ;
    }

    /**
     * @param $configFilename
     * @return array
     */
    protected function getConfigOptions($configFilename)
    {
        $configFilename = $this->getConfigFilename($configFilename);
        $parser = new Parser();
        return $parser->parseFile($configFilename);
    }

    /**
     * @param string $configFilename
     * @return string
     * @throws FileNotFoundException
     */
    protected function getConfigFilename($configFilename)
    {
        $cwd = getcwd();
        $shift = '';
        do {
            $configDirectory = realpath($cwd . $shift);
            $configFilepath = rtrim($configDirectory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $configFilename;
            if ($this->filesystem->exists($configFilepath)) {
                return $configFilepath;
            }
            $shift .= DIRECTORY_SEPARATOR . '..';
        } while (!$this->isRootDirectory($configDirectory));

        throw new FileNotFoundException(sprintf(
            'Configuration file not found.'
        ));
    }

    /**
     * @param string $directory
     * @return bool
     */
    protected function isRootDirectory($directory)
    {
        return $directory == realpath($directory . DIRECTORY_SEPARATOR . '..');
    }

    /**
     * Prints an error to an output
     *
     * @param OutputInterface $output
     * @param string $message The error message
     */
    protected function writeError(OutputInterface $output, string $message)
    {
        if ($output instanceof ConsoleOutputInterface) {
            $output = $output->getErrorOutput();
        }

        $output->writeln($this->getHelper('formatter')->formatBlock($message, 'error'));
    }
}
