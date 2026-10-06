<?php

/**
 * Test: ArraySource filtering and sorting on plain PHP values - no grid, no database.
 * Run: vendor/bin/tester tests/DataSources/ArraySource.Values.phpt
 *
 * The source used to cast every sort key to a string (a DateTimeInterface value threw), compared numbers through
 * (int) (a 'Y-m-d' date became its year) and knew no BETWEEN, so the date range filter failed on an array model.
 */

declare(strict_types=1);

use Grido\Components\Filters\Condition;
use Grido\DataSources\ArraySource;
use Tester\Assert;

require __DIR__ . '/../../vendor/autoload.php';

Tester\Environment::setup();
Tester\Environment::setupFunctions();
date_default_timezone_set('Europe/Bratislava');


function source(array $data): ArraySource
{
	return new ArraySource($data);
}


test('compare keeps the original semantics', function (): void {
	$source = source([]);

	Assert::true($source->compare('Lucie', 'LIKE ?', '%Lu%'));
	Assert::true($source->compare('Lucie', 'LIKE ?', '%ie'));
	Assert::true($source->compare('Lucie', 'LIKE ?', 'lu%'));
	Assert::true($source->compare('Lucie/Lucy', 'LIKE ?', 'Lucie/L%'));
	Assert::false($source->compare('Lucie', 'LIKE ?', 'ie%'));
	Assert::false($source->compare('Lucie', 'LIKE ?', '%lu'));
	Assert::true($source->compare('Žluťoučký kůň', 'LIKE ?', 'zlutou%'));
	Assert::true($source->compare('Žluťoučký kůň', 'LIKE ?', 'žlutou%'));

	Assert::true($source->compare('Lucie', '= ?', 'Lucie'));
	Assert::false($source->compare('Lucie', '= ?', 'lucie'));
	Assert::true($source->compare('Lucie', '<> ?', 'Petr'));
	Assert::false($source->compare('Lucie', '<> ?', 'Lucie'));

	Assert::true($source->compare(null, 'IS NULL', null));
	Assert::false($source->compare('', 'IS NULL', null));
	Assert::true($source->compare('', 'IS NOT NULL', null));

	Assert::true($source->compare('3', '> ?', 2));
	Assert::false($source->compare(3, '> ?', 4));
	Assert::true($source->compare('3', '>= ?', 3));
	Assert::true($source->compare('3', '<= ?', 3));
	Assert::true($source->compare(2, '< ?', 3));
	Assert::true($source->compare(null, '< ?', 3));

	Assert::exception(fn() => $source->compare(2, 'SOMETHING ?', 3), Grido\Exception::class, "Condition 'SOMETHING ?' is not implemented yet.");
});


test('numbers compare as numbers, not truncated to integers', function (): void {
	$source = source([]);
	Assert::true($source->compare('2.5', '> ?', 2));
	Assert::true($source->compare(10, '> ?', '9'));
	Assert::false($source->compare('10', '< ?', '9'));
});


test('dates compare by timestamp, also against date strings from the filters', function (): void {
	$source = source([]);
	$date = new DateTimeImmutable('2026-10-03 14:30');

	Assert::true($source->compare($date, '> ?', '2026-10-01'));
	Assert::true($source->compare($date, '< ?', '2026-10-04'));
	Assert::true($source->compare($date, 'BETWEEN ? AND ?', ['2026-10-01', '2026-10-05 23:59:59']));
	Assert::false($source->compare($date, 'BETWEEN ? AND ?', ['2026-10-04', '2026-10-05 23:59:59']));
	Assert::true($source->compare($date, 'BETWEEN ? AND ?', [strtotime('2026-10-03'), strtotime('2026-10-03 23:59:59')]));
	Assert::false($source->compare(null, 'BETWEEN ? AND ?', ['2026-10-01', '2026-10-05']));
	Assert::true($source->compare($date, 'LIKE ?', '2026-10-03%'), 'the date filter matches a day by LIKE');
	Assert::true($source->compare(1, 'BETWEEN ? AND ?', [1, 3]));
});


test('makeWhere: AND binds before OR, values per column', function (): void {
	$data = [
		['name' => 'AA', 'surname' => 'BB', 'city' => 'CC'],
		['name' => 'CC', 'surname' => 'DD', 'city' => 'AA'],
		['name' => 'EE', 'surname' => 'AA', 'city' => 'FF'],
		['name' => 'AA', 'surname' => 'AA', 'city' => 'BB'],
		['name' => 'AA', 'surname' => 'AA', 'city' => 'AA'],
	];

	$source = source($data);
	$source->filter([Condition::setup(['name'], '= ?', 'CC')]);
	Assert::same([1 => $data[1]], $source->getData());

	$source = source($data);
	$source->filter([Condition::setup(['name', 'OR', 'surname'], '= ?', 'AA')]);
	$expected = $data;
	unset($expected[1]);
	Assert::same($expected, $source->getData());

	$source = source($data);
	$source->filter([Condition::setup(['name', 'AND', 'surname'], '= ?', 'AA')]);
	Assert::same([3 => $data[3], 4 => $data[4]], $source->getData());

	// one shared value: name = EE OR (surname = EE AND city = EE)
	$source = source($data);
	$source->filter([Condition::setup(['name', 'OR', 'surname', 'AND', 'city'], '= ?', ['EE'])]);
	Assert::same([2 => $data[2]], $source->getData());

	// name = EE OR (surname = AA AND city = AA)
	$source = source($data);
	$source->filter([Condition::setup(['name', 'OR', 'surname', 'AND', 'city'], ['= ?', '= ?', '= ?'], ['EE', 'AA', 'AA'])]);
	Assert::same([2 => $data[2], 4 => $data[4]], $source->getData());
});


test('a date range filter on DateTime rows', function (): void {
	$data = [
		['id' => 1, 'created' => new DateTimeImmutable('2026-09-30 23:59')],
		['id' => 2, 'created' => new DateTimeImmutable('2026-10-01 00:00')],
		['id' => 3, 'created' => new DateTimeImmutable('2026-10-05 23:59')],
		['id' => 4, 'created' => null],
	];
	$source = source($data);
	$source->filter([Condition::setup(['created'], 'BETWEEN ? AND ?', ['2026-10-01', '2026-10-05 23:59:59'])]);
	Assert::same([2, 3], array_column(array_values($source->getData()), 'id'));
});


test('callback condition gets the value and the row', function (): void {
	$source = source([['cm' => 170], ['cm' => 185]]);
	$source->filter([Condition::setupFromCallback(fn(mixed $value, array $row): bool => $row['cm'] >= 180, true)]);
	Assert::same([1 => ['cm' => 185]], $source->getData());
});


test('sorting: dates, numbers, null first, several columns, stable', function (): void {
	$data = [
		['id' => 1, 'at' => new DateTimeImmutable('2026-10-02'), 'n' => '10', 'g' => 'b'],
		['id' => 2, 'at' => null, 'n' => '9', 'g' => 'a'],
		['id' => 3, 'at' => new DateTimeImmutable('2026-10-01'), 'n' => '9', 'g' => 'b'],
		['id' => 4, 'at' => new DateTimeImmutable('2026-10-03'), 'n' => '2.5', 'g' => 'a'],
	];
	$ids = function (array $sorting) use ($data): array {
		$source = source($data);
		$source->sort($sorting);
		return array_column($source->getData(), 'id');
	};

	Assert::same([2, 3, 1, 4], $ids(['at' => 'ASC']));
	Assert::same([4, 1, 3, 2], $ids(['at' => 'DESC']));
	Assert::same([4, 2, 3, 1], $ids(['n' => 'ASC']), 'numeric strings as numbers');
	Assert::same([2, 4, 1, 3], $ids(['g' => 'ASC', 'n' => 'DESC']));
	Assert::same([2, 4, 1, 3], $ids(['g' => 'asc']), 'equal rows keep their order, lowercase direction works');
});


test('rows may be ArrayAccess objects', function (): void {
	$row = fn(int $id, string $name): ArrayObject => new ArrayObject(['id' => $id, 'name' => $name]);
	$source = source([$row(1, 'b'), $row(2, 'a')]);
	$source->filter([Condition::setup(['name'], 'LIKE ?', '%')]);
	$source->sort(['name' => 'ASC']);
	Assert::same(2, $source->getData()[0]['id']);
});


test('suggest returns distinct sorted values within the limit', function (): void {
	$source = source([['c' => 'Bratislava'], ['c' => 'Brno'], ['c' => 'Bratislava'], ['c' => 'Banská <Bystrica>'], ['c' => 'Košice']]);
	Assert::same(['Banská &lt;Bystrica&gt;', 'Bratislava'], $source->suggest('c', [Condition::setup(['c'], 'LIKE ?', 'B%')], 2));
	Assert::same(['3'], source([['a' => 1, 'b' => 2]])->suggest(fn(array $row): int => $row['a'] + $row['b'], [], 10));
	Assert::exception(fn() => $source->suggest(5, [], 10), Grido\Exception::class);
});
