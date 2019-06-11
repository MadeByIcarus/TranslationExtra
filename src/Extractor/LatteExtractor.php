<?php
declare(strict_types=1);

namespace Icarus\TranslationExtra\Extractor;


use Latte\Parser;
use Nette\Utils\Finder;
use Symfony\Component\Translation\Extractor\AbstractFileExtractor;
use Symfony\Component\Translation\Extractor\ExtractorInterface;
use Symfony\Component\Translation\MessageCatalogue;


class LatteExtractor extends AbstractFileExtractor implements ExtractorInterface
{

    const TRANSLATE_NAME = "_";
    const DOMAIN_NAME = "translator";

    /**
     * @var string
     */
    private $prefix;

    /**
     * @var Parser
     */
    private $parser;



    public function __construct()
    {
        $this->parser = new Parser();
    }



    /**
     * @param string $file
     *
     * @return bool
     */
    protected function canBeExtracted($file)
    {
        return $this->isFile($file) && 'latte' === pathinfo($file, PATHINFO_EXTENSION);
    }



    /**
     * @param string|array $resource Files, a file or a directory
     *
     * @return array
     */
    protected function extractFromDirectory($directory)
    {
        return Finder::findFiles('*.latte')->from($directory);
    }



    /**
     * Extracts translation messages from files, a file or a directory to the catalogue.
     *
     * @param string|array $resource Files, a file or a directory
     * @param MessageCatalogue $catalogue The catalogue
     */
    public function extract($resource, MessageCatalogue $catalogue)
    {
        $files = $this->extractFiles($resource);
        foreach ($files as $file) {
            $this->parseFile($file, $catalogue);

            gc_mem_caches();
        }
    }



    protected function parseFile(\SplFileInfo $fileInfo, MessageCatalogue $catalogue)
    {
        $contents = file_get_contents($fileInfo->getPathname());
        $tokens = $this->parser->parse($contents);

        $defaultDomain = "messages";
        $domain = $defaultDomain;

        $customDomains = [];

        foreach ($tokens as $token) {

            $message = null;

            switch ($token->name) {
                case self::DOMAIN_NAME:
                    if ($token->closing) {
                        array_pop($customDomains);
                        $lastKey = count($customDomains) - 1;
                        $domain = $customDomains[$lastKey] ?? $defaultDomain;
                    } else {
                        $domain = $customDomains[] = $token->value;
                    }
                    continue 2;
                    break;
                case self::TRANSLATE_NAME:
                    list($key,) = explode(",", $token->value, 2);
                    $message = trim($key, " \t\n\r\0\x0B\"");
                    break;
            }

            if ($message) {
                $whole = $domain . "." . $message;

                list($messageDomain, $message) = explode(".", $whole, 2);

                $messageDomain .= MessageCatalogue::INTL_DOMAIN_SUFFIX;

                $defaultTranslation = (false !== $pos = strrpos($message, ".")) ?
                    substr($message, $pos + 1) :
                    $message;

                $catalogue->set($message, $defaultTranslation, $messageDomain);
                $metadata = $catalogue->getMetadata($message, $messageDomain) ?? [];
                $normalizedFilename = preg_replace('{[\\\\/]+}', '/', $fileInfo->getPathname());
                $metadata['sources'][] = $normalizedFilename . ':' . $token->line;
                $catalogue->setMetadata($message, $metadata, $messageDomain);
            }
        }
    }



    /**
     * Sets the prefix that should be used for new found messages.
     *
     * @param string $prefix The prefix
     */
    public function setPrefix($prefix)
    {
        $this->prefix = $prefix;
    }
}