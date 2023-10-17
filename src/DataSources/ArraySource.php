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

use Grido\Components\Filters\Condition;
use Grido\Exception;
use Latte\Runtime\Filters;
use Nette\SmartObject;
use Nette\Utils\Strings;

/**
 * Array data source.
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


	/**
	 * This method needs tests!
	 */
	protected function makeWhere(Condition $condition, array $data = null): array
	{
		$data ??= $this->data;

		return array_filter($data, function (array $row) use ($condition) {
			if ($condition->callback) {
				return call_user_func_array($condition->callback, [$condition->value, $row]);
			}

			$i = 0;
			$results = [];
			foreach ($condition->column as $column) {
				if (Condition::isOperator($column)) {
					$results[] = " {$column} ";
				} else {
					$i = count($condition->condition) > 1 ? $i : 0;
					$results[] = (int) $this->compare(
						$row[$column],
						$condition->condition[$i],
						$condition->value[$i] ?? null
					);

					$i++;
				}
			}

			$result = implode('', $results);
			return count($condition->column) === 1 ? (bool) $result : eval("return {$result};"); // QUESTION: How to remove this eval? hmmm?
		});
	}


	/**
	 * @throws Exception
	 */
	public function compare(string $actual, string $condition, mixed $expected): bool
	{
		$expected = (array) $expected;
		$expected = current($expected);
		$cond = str_replace(' ?', '', $condition);

		if ($cond === 'LIKE') {
			$actual = Strings::toAscii($actual);
			$expected = Strings::toAscii($expected);

			$pattern = str_replace('%', '(.|\s)*', preg_quote($expected, '/'));
			return (bool) preg_match("/^{$pattern}$/i", $actual);
		} elseif ($cond === '=') {
			return $actual == $expected;
		} elseif ($cond === '<>') {
			return $actual != $expected;
		} elseif ($cond === 'IS null') {
			return $actual === null;
		} elseif ($cond === 'IS NOT null') {
			return $actual !== null;
		} elseif ($cond === '<') {
			return (int) $actual < $expected;
		} elseif ($cond === '<=') {
			return (int) $actual <= $expected;
		} elseif ($cond === '>') {
			return (int) $actual > $expected;
		} elseif ($cond === '>=') {
			return (int) $actual >= $expected;
		}
		throw new Exception("Condition '{$condition}' is not implemented yet.");
	}


	/*	 * ********************************* interface IDataSource *********************************** */

	public function getCount(): int
	{
		return count($this->data);
	}


	public function getData(): array
	{
		return $this->data;
	}


	public function filter(array $conditions): void
	{
		foreach ($conditions as $condition) {
			$this->data = $this->makeWhere($condition);
		}
	}


	public function limit(int $offset, int $limit): void
	{
		$this->data = array_slice($this->data, $offset, $limit);
	}


	/**
	 * @throws Exception
	 */
	public function sort(array $sorting): void
	{
		if (count($sorting) > 1) {
			throw new Exception('Multi-column sorting is not implemented yet.');
		}

		foreach ($sorting as $column => $sort) {
			$data = [];
			foreach ($this->data as $item) {
				$sorter = (string) $item[$column];
				$data[$sorter][] = $item;
			}

			if ($sort === 'ASC') {
				ksort($data);
			} else {
				krsort($data);
			}

			$this->data = [];
			foreach ($data as $i) {
				foreach ($i as $item) {
					$this->data[] = $item;
				}
			}
		}
	}


	/**
	 * @throws Exception
	 */
	public function suggest(mixed $column, array $conditions, int $limit): array
	{
		$data = $this->data;
		foreach ($conditions as $condition) {
			$data = $this->makeWhere($condition, $data);
		}

		array_slice($data, 1, $limit);

		$items = [];
		foreach ($data as $row) {
			if (is_string($column)) {
				$value = (string) $row[$column];
			} elseif (is_callable($column)) {
				$value = (string) $column($row);
			} else {
				$type = gettype($column);
				throw new Exception("Column of suggestion must be string or callback, {$type} given.");
			}

			$items[$value] = Filters::escapeHtml($value);
		}

		sort($items);
		return array_values($items);
	}
}
