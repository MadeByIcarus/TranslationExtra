<?php
declare(strict_types=1);

namespace Icarus\TranslationExtra\Tests\Dumper;


require __DIR__ . "/../bootstrap.php";

use Icarus\TranslationExtra\Dumper\NeonFileDumper;
use Symfony\Component\Translation\Exception\InvalidArgumentException;
use Symfony\Component\Translation\MessageCatalogue;
use Tester\Assert;
use Tester\FileMock;
use Tester\TestCase;


class NeonFileDumperTest extends TestCase
{

    private const NONE = INF;



    /**
     * @dataProvider getExtensions
     */
    public function testConstructor($extension, $shouldBeOk)
    {
        $function = function () use ($extension) {
            if ($extension === self::NONE) {
                new NeonFileDumper();
            } else {
                new NeonFileDumper($extension);
            }
        };

        if ($shouldBeOk) {
            Assert::noError($function);
        } else {
            Assert::exception($function, \Throwable::class);
        }
    }



    public function testFormatCatalogue()
    {
        $catalogue = new MessageCatalogue('cs');

        $catalogue->set('hello.world', 'hello');
        $catalogue->set('hello.world2', 'hello2');
        $catalogue->set('file', <<<EOF
    {count, plural,
        =0 {Ahoj.}
        one {Ahoj 1}
        other {Ahoj svete}
    }
EOF
, 'multiline');

        $dumper = new NeonFileDumper();
        $options = [
            'inline' => false,
            'as_tree' => false
        ];

        $actual = $dumper->formatCatalogue($catalogue, 'messages', $options);
        Assert::same("hello.world: hello\nhello.world2: hello2\n", $actual);

        $options['inline'] = true;
        $actual = $dumper->formatCatalogue($catalogue, 'messages', $options);
        Assert::same("{hello.world: hello, hello.world2: hello2}", $actual);

        $options['as_tree'] = true;
        $actual = $dumper->formatCatalogue($catalogue, 'messages', $options);
        Assert::same("{hello: {world: hello, world2: hello2}}", $actual);

        $options['inline'] = false;
        $options['as_tree'] = true;
        $actual = $dumper->formatCatalogue($catalogue, 'messages', $options);
        Assert::same(<<<EOF
hello:
\tworld: hello
\tworld2: hello2


EOF
        , $actual);


        $actual = $dumper->formatCatalogue($catalogue, 'multiline', $options);
        Assert::equal(<<<EOF
file: '''
    {count, plural,
        =0 {Ahoj.}
        one {Ahoj 1}
        other {Ahoj svete}
    } 
'''

EOF
            , $actual);
    }



    //


    public function getExtensions(): array
    {
        return [
            [self::NONE, true],
            ['neon', true],
            // TypeError
            [null, false],
            [1, false],
            [2.0, false],
            // InvalidArgument
            ['yaml', false],
            ['xml', false],
            ['json', false]
        ];
    }
}

(new NeonFileDumperTest())->run();