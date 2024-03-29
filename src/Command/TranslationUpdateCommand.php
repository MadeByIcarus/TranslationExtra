<?php
declare(strict_types=1);

/*
 * This file is inspired by https://github.com/symfony/framework-bundle/blob/master/Command/TranslationUpdateCommand.php
 */

namespace Icarus\TranslationExtra\Command;


use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\InvalidArgumentException;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Translation\Catalogue\MergeOperation;
use Symfony\Component\Translation\Catalogue\TargetOperation;
use Symfony\Component\Translation\Extractor\ExtractorInterface;
use Symfony\Component\Translation\MessageCatalogue;
use Symfony\Component\Translation\Reader\TranslationReaderInterface;
use Symfony\Component\Translation\Writer\TranslationWriterInterface;


/**
 * A command that parses templates to extract translation messages and adds them
 * into the translation files.
 *
 * @author Michel Salib <michelsalib@hotmail.com>
 *
 * @final
 */
class TranslationUpdateCommand extends Command
{

    protected static $defaultName = 'translation:update';

    private $writer;

    private $reader;

    private $extractor;

    private $defaultLocale;

    private $defaultTransPath;

    private $defaultViewsPath;

    private $transPaths;

    private $viewsPaths;



    public function __construct(
        TranslationWriterInterface $writer,
        TranslationReaderInterface $reader,
        ExtractorInterface $extractor,
        string $defaultLocale,
        string $defaultTransPath = null,
        string $defaultViewsPath = null,
        array $transPaths = [],
        array $viewsPaths = []
    )
    {
        parent::__construct();

        $this->writer = $writer;
        $this->reader = $reader;
        $this->extractor = $extractor;
        $this->defaultLocale = $defaultLocale;
        $this->defaultTransPath = $defaultTransPath;
        $this->defaultViewsPath = $defaultViewsPath;
        $this->transPaths = $transPaths;
        $this->viewsPaths = $viewsPaths;
    }



    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setDefinition([
                new InputArgument('locale', InputArgument::REQUIRED, 'The locale'),
                new InputOption('prefix', null, InputOption::VALUE_OPTIONAL, 'Override the default prefix', '__'),
                new InputOption('output-format', null, InputOption::VALUE_OPTIONAL, 'Override the default output format', 'neon'),
                new InputOption('dump-messages', null, InputOption::VALUE_NONE, 'Should the messages be dumped in the console'),
                new InputOption('force', null, InputOption::VALUE_NONE, 'Should the update be done'),
                new InputOption('no-backup', null, InputOption::VALUE_NONE, 'Should backup be disabled'),
                new InputOption('clean', null, InputOption::VALUE_NONE, 'Should clean not found messages'),
                new InputOption('domain', null, InputOption::VALUE_OPTIONAL, 'Specify the domain to update'),
                new InputOption('output-format-as-tree', null, InputOption::VALUE_OPTIONAL, 'Specify the output format option', true),
            ])
            ->setDescription('Updates the translation file')
            ->setHelp(<<<'EOF'
The <info>%command.name%</info> command extracts translation strings from templates
of a given bundle or the default translations directory. It can display them or merge the new ones into the translation files.

When new translation strings are found it can automatically add a prefix to the translation
message.

Example running against default messages directory
  <info>php %command.full_name% --dump-messages en</info>
  <info>php %command.full_name% --force --prefix="new_" fr</info>
EOF
            );
    }



    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);
        $errorIo = $io->getErrorStyle();

        // check presence of force or dump-message
        if (true !== $input->getOption('force') && true !== $input->getOption('dump-messages')) {
            $errorIo->error('You must choose one of --force or --dump-messages');

            return 1;
        }

        // check format
        $supportedFormats = $this->writer->getFormats();
        if (!\in_array($input->getOption('output-format'), $supportedFormats, true)) {
            $errorIo->error(['Wrong output format', 'Supported formats are: ' . implode(', ', $supportedFormats) . '.']);

            return 1;
        }

        // Define Root Paths
        $transPaths = $this->transPaths;
        if ($this->defaultTransPath) {
            $transPaths[] = $this->defaultTransPath;
        }
        $viewsPaths = $this->viewsPaths;
        if ($this->defaultViewsPath) {
            $viewsPaths[] = $this->defaultViewsPath;
        }
        $currentName = 'default directory';


        $errorIo->title('Translation Messages Extractor and Dumper');
        $errorIo->comment(sprintf('Generating "<info>%s</info>" translation files for "<info>%s</info>"', $input->getArgument('locale'), $currentName));

        // load any messages from templates
        $extractedCatalogue = new MessageCatalogue($input->getArgument('locale'));
        $errorIo->comment('Parsing templates...');
        $this->extractor->setPrefix($input->getOption('prefix'));
        foreach ($viewsPaths as $path) {
            if (is_dir($path) || is_file($path)) {
                $this->extractor->extract($path, $extractedCatalogue);
            }
        }

        // load any existing messages from the translation files
        $currentCatalogue = new MessageCatalogue($input->getArgument('locale'));
        $errorIo->comment('Loading translation files...');
        foreach ($transPaths as $path) {
            if (is_dir($path)) {
                $this->reader->read($path, $currentCatalogue);
            }
        }

        if (null !== $domain = $input->getOption('domain')) {
            $currentCatalogue = $this->filterCatalogue($currentCatalogue, $domain);
            $extractedCatalogue = $this->filterCatalogue($extractedCatalogue, $domain);
        }

        // process catalogues
        $operation = $input->getOption('clean')
            ? new TargetOperation($currentCatalogue, $extractedCatalogue)
            : new MergeOperation($currentCatalogue, $extractedCatalogue);

        // Exit if no messages found.
        if (!\count($operation->getDomains())) {
            $errorIo->warning('No translation messages were found.');

            return;
        }

        $resultMessage = 'Translation files were successfully updated';

        // show compiled list of messages
        if (true === $input->getOption('dump-messages')) {
            $extractedMessagesCount = 0;
            $io->newLine();
            foreach ($operation->getDomains() as $domain) {
                $newKeys = array_keys($operation->getNewMessages($domain));
                $allKeys = array_keys($operation->getMessages($domain));

                $list = array_merge(
                    array_diff($allKeys, $newKeys),
                    array_map(function ($id) {
                        return sprintf('<fg=green>%s</>', $id);
                    }, $newKeys),
                    array_map(function ($id) {
                        return sprintf('<fg=red>%s</>', $id);
                    }, array_keys($operation->getObsoleteMessages($domain)))
                );

                $domainMessagesCount = \count($list);

                $io->section(sprintf('Messages extracted for domain "<info>%s</info>" (%d message%s)', $domain, $domainMessagesCount, $domainMessagesCount > 1 ? 's' : ''));
                $io->listing($list);

                $extractedMessagesCount += $domainMessagesCount;
            }

            if ('xlf' === $input->getOption('output-format')) {
                $errorIo->comment(sprintf('Xliff output version is <info>%s</info>', $input->getOption('xliff-version')));
            }

            $resultMessage = sprintf('%d message%s successfully extracted', $extractedMessagesCount, $extractedMessagesCount > 1 ? 's were' : ' was');
        }

        if (true === $input->getOption('no-backup')) {
            $this->writer->disableBackup();
        }

        // save the files
        if (true === $input->getOption('force')) {
            $errorIo->comment('Writing files...');

            $bundleTransPath = false;
            foreach ($transPaths as $path) {
                if (is_dir($path)) {
                    $bundleTransPath = $path;
                }
            }

            if (!$bundleTransPath) {
                $bundleTransPath = end($transPaths);
            }

            $this->writer->write($operation->getResult(), $input->getOption('output-format'), ['path' => $bundleTransPath, 'default_locale' => $this->defaultLocale, 'as_tree' => $input->getOption('output-format-as-tree')]);

            if (true === $input->getOption('dump-messages')) {
                $resultMessage .= ' and translation files were updated';
            }
        }

        $errorIo->success($resultMessage . '.');
    }



    private function filterCatalogue(MessageCatalogue $catalogue, string $domain): MessageCatalogue
    {
        $filteredCatalogue = new MessageCatalogue($catalogue->getLocale());

        if ($messages = $catalogue->all($domain)) {
            $filteredCatalogue->add($messages, $domain);
        }
        foreach ($catalogue->getResources() as $resource) {
            $filteredCatalogue->addResource($resource);
        }
        if ($metadata = $catalogue->getMetadata('', $domain)) {
            foreach ($metadata as $k => $v) {
                $filteredCatalogue->setMetadata($k, $v, $domain);
            }
        }

        return $filteredCatalogue;
    }
}