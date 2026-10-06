<?php

declare(strict_types=1);

/**
 * This file is part of the Grido (http://grido.bugyik.cz)
 *
 * Copyright (c) 2011 Petr Bugyík (http://petr.bugyik.cz)
 *
 * For the full copyright and license information, please view
 * the file LICENSE.md that was distributed with this source code.
 */

namespace Grido\DataSources;

use DateTimeInterface;
use Grido\Components\Filters\Condition;
use Grido\Exception;
use Latte\Runtime\HtmlHelpers;
use Nette\SmartObject;
use Nette\Utils\Strings;

/**
 * Array data source - rows are arrays or ArrayAccess objects (DTOs, dibi rows).
 *
 * Filtering and sorting work on the PHP values like the SQL sources do: null first, numbers as numbers, dates
 * (DateTimeInterface, or a date string compared with one) by their timestamp, the rest as strings. LIKE sees a date
 * as 'Y-m-d H:i:s' in its own time zone.
 *
 * @property-read array $data
 * @property-read int $count
 */
/*final*/ class ArraySource implements IDataSource
{
	use SmartObject;


	public function __construct(
		protected array $data
	) {
	}


	public function getCount(): int
	{
		return count($this->data);
	}


	public function getData(): array
	{
		return $this->data;
	}


	/**
	 * @param Condition[] $conditions
	 */
	public function filter(array $conditions): void
	{
		$this->data = $this->applyConditions($this->data, $conditions);
	}


	public function limit(int $offset, int $limit): void
	{
		$this->data = array_slice($this->data, $offset, $limit);
	}


	/**
	 * @param array<string, string> $sorting column => ASC|DESC, the first column has the highest priority
	 */
	public function sort(array $sorting): void
	{
		$directions = [];
		foreach ($sorting as $column => $direction) {
			$directions[$column] = strtoupper($direction) === 'DESC' ? -1 : 1;
		}

		// usort is stable - rows equal in every sorted column keep their order
		usort($this->data, function (mixed $a, mixed $b) use ($directions): int {
			foreach ($directions as $column => $direction) {
				$result = self::order($a[$column] ?? null, $b[$column] ?? null);
				if ($result !== 0) {
					return $result * $direction;
				}
			}
			return 0;
		});
	}


	/**
	 * @param string|callable $column a column name, or a callback returning the suggested value of a row
	 * @param Condition[] $conditions
	 * @return list<string> distinct values, sorted, escaped for HTML
	 * @throws Exception
	 */
	public function suggest(mixed $column, array $conditions, int $limit): array
	{
		if (!is_string($column) && !is_callable($column)) {
			$type = gettype($column);
			throw new Exception("Column of suggestion must be string or callback, {$type} given.");
		}

		$values = [];
		foreach ($this->applyConditions($this->data, $conditions) as $row) {
			$values[self::toText(is_string($column) ? ($row[$column] ?? null) : $column($row))] = true;
		}

		$values = array_map('strval', array_keys($values));
		sort($values);
		return array_map(HtmlHelpers::escapeText(...), array_slice($values, 0, $limit));
	}


	/**
	 * One comparison of a filter condition.
	 * @param string $condition LIKE ?, = ?, <> ?, < ?, <= ?, > ?, >= ?, BETWEEN ? AND ?, IS NULL, IS NOT NULL
	 * @param mixed $expected the value, or [from, to] for BETWEEN
	 * @throws Exception
	 */
	public function compare(mixed $actual, string $condition, mixed $expected): bool
	{
		$operator = strtoupper(trim((string) preg_replace('/\s+/', ' ', str_replace('?', '', $condition))));
		if (is_array($expected) && $operator !== 'BETWEEN AND') {
			$expected = reset($expected);
		}

		switch ($operator) {
			case 'LIKE':
				$pattern = str_replace('%', '.*', preg_quote(Strings::toAscii(self::toText($expected)), '/'));
				return (bool) preg_match("/^{$pattern}$/is", Strings::toAscii(self::toText($actual)));
			case '=':
				return self::order($actual, $expected) === 0;
			case '<>':
			case '!=':
				return self::order($actual, $expected) !== 0;
			case '<':
				return self::order($actual, $expected) < 0;
			case '<=':
				return self::order($actual, $expected) <= 0;
			case '>':
				return self::order($actual, $expected) > 0;
			case '>=':
				return self::order($actual, $expected) >= 0;
			case 'BETWEEN AND':
				[$from, $to] = array_values((array) $expected) + [null, null];
				return $actual !== null && self::order($actual, $from) >= 0 && self::order($actual, $to) <= 0;
			case 'IS NULL':
				return $actual === null;
			case 'IS NOT NULL':
				return $actual !== null;
		}

		throw new Exception("Condition '{$condition}' is not implemented yet.");
	}


	/**
	 * @param Condition[] $conditions
	 */
	protected function applyConditions(array $data, array $conditions): array
	{
		foreach ($conditions as $condition) {
			$data = array_filter($data, fn(mixed $row): bool => $this->matches($row, $condition));
		}
		return $data;
	}


	/**
	 * A condition is a chain "column OPERATOR column ..." of comparisons joined by AND / OR (AND binds first, as in
	 * SQL). With one comparison all columns use it and its values; with several, each column has its own comparison
	 * and they take the values in order.
	 */
	protected function matches(mixed $row, Condition $condition): bool
	{
		$callback = $condition->getCallback();
		if ($callback !== null) {
			return (bool) $callback($condition->getValue(), $row);
		}

		$comparisons = $condition->getCondition();
		$values = array_values((array) $condition->getValue());
		$shared = count($comparisons) === 1;

		$orGroups = [[]];
		$index = 0;
		$offset = 0;
		foreach ($condition->getColumn() as $column) {
			if (Condition::isOperator($column)) {
				if (strtoupper($column) === Condition::OPERATOR_OR) {
					$orGroups[] = [];
				}
				continue;
			}

			$comparison = $comparisons[$shared ? 0 : $index];
			$placeholders = substr_count($comparison, '?');
			$columnValues = array_slice($values, $shared ? 0 : $offset, $placeholders);
			$offset += $placeholders;
			$index++;

			$orGroups[array_key_last($orGroups)][] = $this->compare(
				$row[$column] ?? null,
				$comparison,
				$placeholders > 1 ? $columnValues : ($columnValues[0] ?? null),
			);
		}

		foreach ($orGroups as $group) {
			if ($group !== [] && !in_array(false, $group, true)) {
				return true;
			}
		}
		return false;
	}


	/**
	 * Three-way comparison: null first, dates by timestamp, numbers as numbers, the rest as strings.
	 */
	private static function order(mixed $a, mixed $b): int
	{
		if ($a === null || $b === null) {
			return ($a !== null) <=> ($b !== null);
		}

		if ($a instanceof DateTimeInterface || $b instanceof DateTimeInterface) {
			return self::toTimestamp($a) <=> self::toTimestamp($b);
		}

		if (is_numeric($a) && is_numeric($b)) {
			return +$a <=> +$b;
		}

		return strcmp(self::toText($a), self::toText($b));
	}


	private static function toTimestamp(mixed $value): ?int
	{
		if ($value instanceof DateTimeInterface) {
			return $value->getTimestamp();
		}
		if (is_numeric($value)) {
			return (int) $value;
		}
		$timestamp = strtotime(self::toText($value));
		return $timestamp === false ? null : $timestamp;
	}


	private static function toText(mixed $value): string
	{
		return match (true) {
			$value === null => '',
			$value instanceof DateTimeInterface => $value->format('Y-m-d H:i:s'),
			is_bool($value) => $value ? '1' : '0',
			default => (string) $value,
		};
	}
}
