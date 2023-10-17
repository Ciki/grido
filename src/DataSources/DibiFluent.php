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

use Dibi\Fluent;
use Dibi\Row;
use Grido\Components\Filters\Condition;
use Grido\Exception;
use Latte\Runtime\Filters;
use Nette\SmartObject;

/**
 * Dibi Fluent data source.
 *
 * @property-read Fluent $fluent
 * @property-read int $limit
 * @property-read int $offset
 * @property-read int $count
 * @property-read array $data
 */
final class DibiFluent implements IDataSource
{
	use SmartObject;

	protected int $limit;

	protected int $offset;


	public function __construct(
		protected Fluent $fluent
	) {
	}


	public function getFluent(): Fluent
	{
		return $this->fluent;
	}


	public function getLimit(): int
	{
		return $this->limit;
	}


	public function getOffset(): int
	{
		return $this->offset;
	}


	protected function makeWhere(Condition $condition, Fluent $fluent = null): void
	{
		$fluent ??= $this->fluent;

		if ($condition->callback) {
			call_user_func_array($condition->callback, [$condition->value, $fluent]);
		} else {
			call_user_func_array($fluent->where(...), $condition->__toArray('[', ']'));
		}
	}


	/*	 * ******************************** inline editation helpers *********************************** */


	/**
	 * Default callback used when an editable column has customRender.
	 */
	public function getRow(mixed $id, string $idCol): Row
	{
		$fluent = clone $this->fluent;
		return $fluent
			->where('%n = %s', $idCol, $id)
			->fetch();
	}


	/*	 * ********************************* interface IDataSource *********************************** */

	public function getCount(): int
	{
		$fluent = clone $this->fluent;
		return $fluent->count();
	}


	public function getData(): array
	{
		return $this->fluent->fetchAll($this->offset, $this->limit);
	}


	public function filter(array $conditions): void
	{
		foreach ($conditions as $condition) {
			$this->makeWhere($condition);
		}
	}


	public function limit(int $offset, int $limit): void
	{
		$this->offset = $offset;
		$this->limit = $limit;
	}


	public function sort(array $sorting): void
	{
		foreach ($sorting as $column => $sort) {
			$this->fluent->orderBy('%n', $column, $sort);
		}
	}


	/**
	 * @throws Exception
	 */
	public function suggest(mixed $column, array $conditions, int $limit): array
	{
		$fluent = clone $this->fluent;
		if (is_string($column)) {
			$fluent->removeClause('SELECT')->select('DISTINCT %n', $column)->orderBy('%n', $column, 'ASC');
		}

		foreach ($conditions as $condition) {
			$this->makeWhere($condition, $fluent);
		}

		$items = [];
		$data = $fluent->fetchAll(0, $limit);
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

		is_callable($column) && sort($items);
		return array_values($items);
	}
}
