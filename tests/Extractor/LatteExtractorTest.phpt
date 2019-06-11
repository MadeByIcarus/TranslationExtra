<?php

namespace Icarus\TranslationExtra\Tests\Extractor;


require __DIR__ . "/../bootstrap.php";

use Icarus\TranslationExtra\Extractor\LatteExtractor;
use Symfony\Component\Translation\MessageCatalogue;
use Tester\Assert;
use Tester\TestCase;


class LatteExtractorTest extends TestCase
{

    public function testDefault()
    {
        $extractor = new LatteExtractor();

        $resources = __DIR__ . '/fixtures';
        $locale = "cs";
        $catalogue = new MessageCatalogue($locale, ['messages' => ['hello' => 'Ahoj', 'hello2' => 'hello']]);

        $extractor->extract($resources, $catalogue);

        $expected = [
            'messages' => ['hello' => 'hello', 'hello2' => 'hello', 'default' => 'default'],
            'world' => ['hello' => 'hello', 'hello after' => 'hello after'],
            'world2' => ['hello 2' => 'hello 2']
        ];
        $actual = $catalogue->all();
        Assert::equal($expected, $actual);
    }
}

(new LatteExtractorTest())->run();