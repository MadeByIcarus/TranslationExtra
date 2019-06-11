<?php
declare(strict_types=1);

namespace Icarus\TranslationExtra\Dumper;


use Nette\Neon\Neon;
use Nette\Utils\Strings;
use Symfony\Component\Translation\Dumper\FileDumper;
use Symfony\Component\Translation\Exception\LogicException;
use Symfony\Component\Translation\MessageCatalogue;
use Symfony\Component\Translation\Util\ArrayConverter;


class NeonFileDumper extends FileDumper
{

    private const EXTENSION = 'neon';

    private $extension;



    public function __construct(string $extension = self::EXTENSION)
    {
        if ($extension != self::EXTENSION) {
            throw new \InvalidArgumentException("Invalid extension value '$extension'.");
        }
        $this->extension = $extension;
    }



    /**
     * {@inheritdoc}
     */
    public function formatCatalogue(MessageCatalogue $messages, $domain, array $options = [])
    {
        if (!class_exists(Neon::class)) {
            throw new LogicException('Dumping translations in the NEON format requires the nette/neon package.');
        }

        $data = $messages->all($domain);

        if (isset($options['as_tree']) && $options['as_tree']) {
            $data = ArrayConverter::expandToTree($data);
        }

        if (isset($options['inline']) && $options['inline']) {
            return Neon::encode($data);
        }

        $result = Neon::encode($data, Neon::BLOCK);
        return $this->fixMultiline($result);
    }



    private function fixMultiline(string $encoded)
    {
        return Strings::replace($encoded, "/\".*\n?.*\"/", function (array $matches) {
            $value = trim($matches[0], "\"");
            $parts = explode("\\n", $value);
            $result = implode(PHP_EOL, $parts);
            return <<<EOF
'''
$result 
'''
EOF;
        });
    }



    /**
     * {@inheritdoc}
     */
    protected function getExtension()
    {
        return $this->extension;
    }
}