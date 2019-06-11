<?php
declare(strict_types=1);

namespace Icarus\TranslationExtra\DI;


use Contributte\Translation\Loaders\Neon as NeonFileLoader;
use Icarus\TranslationExtra\Command\TranslationUpdateCommand;
use Icarus\TranslationExtra\Dumper\NeonFileDumper;
use Icarus\TranslationExtra\Extractor\LatteExtractor;
use Nette\DI\CompilerExtension;
use Nette;
use Nette\Schema\Expect;
use Symfony\Component\Translation\Extractor\ExtractorInterface;
use Symfony\Component\Translation\Reader\TranslationReader;
use Symfony\Component\Translation\Reader\TranslationReaderInterface;
use Symfony\Component\Translation\Writer\TranslationWriter;
use Symfony\Component\Translation\Writer\TranslationWriterInterface;


class TranslationExtraExtension extends CompilerExtension
{

    public function getConfigSchema(): Nette\Schema\Schema
    {
        return Expect::structure([
            'defaultLocale' => Expect::string()->required(),
            'defaultTranslationDir' => Expect::string()->required(),
            'defaultViewDir' => Expect::string()->required()
        ]);
    }



    public function loadConfiguration()
    {
        $builder = $this->getContainerBuilder();

        $builder->addDefinition($this->prefix("writer"))
            ->setType(TranslationWriterInterface::class)
            ->setFactory(TranslationWriter::class)
            ->addSetup("?->addDumper(?, new ?())", ['@self', 'neon', new Nette\PhpGenerator\PhpLiteral(NeonFileDumper::class)]);

        $builder->addDefinition($this->prefix("reader"))
            ->setType(TranslationReaderInterface::class)
            ->setFactory(TranslationReader::class)
            ->addSetup("?->addLoader(?, new ?())", ['@self', 'neon', new Nette\PhpGenerator\PhpLiteral(NeonFileLoader::class)]);

        $builder->addDefinition($this->prefix("latteExtractor"))
            ->setFactory(LatteExtractor::class);

        $builder->addDefinition($this->prefix("command.update"))
            ->setFactory(TranslationUpdateCommand::class, [
                "@" . $this->prefix("writer"),
                "@" . $this->prefix("reader"),
                "@" . $this->prefix("latteExtractor"),
                $this->config->defaultLocale,
                $this->config->defaultTranslationDir,
                $this->config->defaultViewDir,
            ]);
    }
}