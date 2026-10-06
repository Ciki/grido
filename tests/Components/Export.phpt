<?php

/**
 * Test: Export.
 *
 * @author     Petr Bugyík
 * @package    Grido\Tests
 */

namespace Grido\Tests;

use Grido\Components\Exports\BaseExport;
use Grido\Components\Exports\CsvExport;
use Tester\Assert,
    Grido\Grid,
    Grido\Tests\Helper,
    Grido\Components\Export,
    Grido\DataSources\ArraySource;

require_once __DIR__ . '/../bootstrap.php';

class Response implements \Nette\Http\IResponse
{
    public static $headers = [];

    function setHeader(string $name, string $value)
    {
        self::$headers[$name] = $value;
        return $this;
    }

    function setCode(int $code, ?string $reason = null) {}
    function getCode(): int { return 200; }
    function addHeader(string $name, string $value) {}
    function getHeader(string $header): ?string { return null; }
    function setContentType(string $type, ?string $charset = null) {}
    function redirect(string $url, int $code = self::S302_Found): void {}
    function setExpiration(?string $expire) {}
    function isSent(): bool { return false; }
    function getHeaders(): array { return []; }
    function setCookie(string $name, string $value, string|int|\DateTimeInterface|null $expire, ?string $path = null, ?string $domain = null, ?bool $secure = null, ?bool $httpOnly = null) {}
    function deleteCookie(string $name, ?string $path = null, ?string $domain = null, ?bool $secure = null) {}
}

class ExportTest extends \Tester\TestCase
{
    function testHasExport()
    {
        $grid = new Grid;
        Assert::false($grid->hasExport());

        $grid->addExport(new CsvExport(), 'csv');
        Assert::false($grid->hasExport());
        Assert::true($grid->hasExport(FALSE));
    }

    function testSetExport()
    {
        $grid = new Grid;
        $label = 'export';

        $grid->addExport(new CsvExport($label), 'csv');
        $component = $grid->getExport('csv');
        Assert::type('\Grido\Components\Exports\BaseExport', $component);
        Assert::same($label, $component->label);

        $grid[BaseExport::ID]->removeComponent($grid->getExport('csv'));
        // getter
        Assert::exception(function() use ($grid) {
            $grid->getExport('csv');
        }, 'Nette\InvalidArgumentException');
    }

    function testHandleExport()
    {
        $this->exportScenario('Testovací export');
    }

    function testLabelGeneration()
    {
        $this->exportScenario();
    }

    private function exportScenario($label = NULL)
    {
        Helper::grid(function(Grid $grid) use ($label) {
            $grid->setModel([
                ['id' => 1, 'name' => 'Lucy', 'country' => 'Switzerland'],
                ['id' => 2, 'name' => "Příliš; žlouťoucký, \"kůň\" \n ďábelsky \tpěl 'ódy", 'country' => 'Switzerland'],
                ['id' => 3, 'name' => 'Silvia', 'country' => 'Switzerland'],
                ['id' => 4, 'name' => 'Mary', 'country' => 'Australia'],
                ['id' => 5, 'name' => 'Michelle', 'country' => 'Australia'],
            ]);

            $grid->setDefaultPerPage(2);
            $grid->addColumnText('name', 'Name')
                ->setSortable();
            $grid->addColumnText('country', 'Country')
                ->setFilterText();
            $grid->addExport(new CsvExport($label, null, ['encoding' => CsvExport::ENCODING_UTF8, 'delimiter' => ',']), 'csv');
        });

        $params = [
            'do' => 'grid-export-csv-export',
            'grid-sort' => ['name' => \Grido\Components\Columns\Column::ORDER_DESC],
            'grid-filter' => ['country' => 'Switzerland'],
            'grid-page' => 2
        ];

        ob_start();
            Helper::request($params)->send(mock('\Nette\Http\IRequest'), new Response);
        $output = ob_get_clean();
        Assert::same(file_get_contents(__DIR__ . '/files/Export.expect'), $output);

        $label = $label ? ucfirst(\Nette\Utils\Strings::webalize($label)) : 'Grid';

        Assert::same([
            'Content-Encoding' => 'UTF-8',
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"$label.csv\"",
        ], Response::$headers);
    }

    function testCustomData()
    {
        Helper::grid(function(Grid $grid) {
            $grid->setModel(new ArraySource([
                ['firstname' => 'Satu', 'surname' => 'Tukio', 'card' => 'Visa'],
                ['firstname' => 'Ronald', 'surname' => 'Olivo', 'card' => 'MasterCard'],
                ['firstname' => 'Feorie', 'surname' => 'Hamid', 'card' => 'MasterCard'],
                ['firstname' => 'Hyiab', 'surname' => 'Haylom', 'card' => 'MasterCard'],
                ['firstname' => 'Ambessa', 'surname' => 'Ali', 'card' => 'Visa'],
                ['firstname' => 'Mateo', 'surname' => 'Topić', 'card' => "Příliš; žlouťoucký, \"kůň\" \n ďábelsky \tpěl 'ódy"],
            ]));

            $grid->addColumnText('firstname', 'Name')
                ->setSortable();

            $grid->addExport(new CsvExport(null, null, ['encoding' => CsvExport::ENCODING_UTF8, 'delimiter' => ',']), 'csv')
                ->setHeader(['"Jméno"', "Příjmení\t", "Karta\n", 'Jméno,Příjmení'])
                ->setCustomData(function(ArraySource $source) {
                    $data = $source->getData();
                    $outData = [];
                    foreach ($data as $item) {
                        $outData[] = [
                            $item['firstname'],
                            $item['surname'],
                            $item['card'],
                            $item['firstname'] . ',' .$item['surname'],
                        ];
                    }
                    return $outData;
                });
        });

        $params = ['do' => 'grid-export-csv-export'];

        ob_start();
            Helper::request($params)->send(mock('\Nette\Http\IRequest'), new Response);
        $output = ob_get_clean();
        Assert::same(file_get_contents(__DIR__ . '/files/Export.custom.expect'), $output);
    }
}

run(__FILE__);
