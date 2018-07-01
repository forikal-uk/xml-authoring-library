<?php

namespace XmlSquad\Library\Command;

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
    const DEFAULT_CONFIG_FILENAME = 'scapesettings.yaml';

    /**
     * @string Appended to directories to go up a level.
     */
    const SHIFT_DIR_PARENT = DIRECTORY_SEPARATOR . '..';

    /**
     * @var Filesystem
     */
    protected $filesystem;

    /**
     * AbstractXmlSquadCommand constructor.
     * @param null $name
     * @param Filesystem|null $filesystem
     */
    public function __construct($name = null, Filesystem $filesystem = null)
    {
        parent::__construct($name);

        $this->filesystem = $filesystem ?? new Filesystem();
    }


    /**
     * Optional argument that specifies default
     *
     * @return $this
     */
    protected function doConfigureConfigFilename()
    {
        $this
            ->addOption('configFilename', 'c', InputOption::VALUE_OPTIONAL, 'Name of configuration file', self::DEFAULT_CONFIG_FILENAME);
        return $this;
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
            $shift .= self::SHIFT_DIR_PARENT;
        } while ($this->isReadableDirectory($configDirectory));

        throw new FileNotFoundException(sprintf(
            'Configuration file not found'. $this->getOpenBaseDirWarning() . '.'
        ));
    }

    /**
     * Returns true if the directory is readable whilst suppressing errors caused by open_basedir settings.
     *
     * @param string $directory
     * @return bool
     */
    protected function isReadableDirectory($directory)
    {
        return (@is_readable(@realpath($directory . self::SHIFT_DIR_PARENT)));
    }

    /**
     * Returns a supplamentary message about open base directories if open_basedir has a value.
     *
     * @return string
     */
    private function getOpenBaseDirWarning()
    {
        if ($this->getOpenBaseDir()) {
            return ' when searching all directories that are open ['.$this->getOpenBaseDir().']';
        }
    }

    /**
     * Get the value of the open_basedir ini setting.
     *
     * @return string
     */
    private function getOpenBaseDir()
    {
        return ini_get('open_basedir');
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
