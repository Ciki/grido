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
use Nette;
use Nette\Database\Table\ActiveRow;
use Nette\Database\Table\Selection;
use Nette\SmartObject;

/**
 * Nette Database data source.
 *
 * @property-read Selection $selection
 * @property-read int $count
 * @property-read array $data
 */
/*final*/ class NetteDatabase implements IDataSource
{
	use SmartObject;


	public function __construct(
		protected Selection $selection
	) {
	}


	public function getSelection(): Selection
	{
		return $this->selection;
	}


	protected function makeWhere(Condition $condition, ?Selection $selection = null): void
	{
		$selection ??= $this->selection;

		if ($condition->callback) {
			call_user_func_array($condition->callback, [$condition->value, $selection]);
		} else {
			call_user_func_array($selection->where(...), $condition->__toArray());
		}
	}


	/*	 * ******************************** inline editation helpers *********************************** */


	/**
	 * Default callback for an inline editation save.
	 */
	public function update(mixed $id, array $values, string $idCol): bool
	{
		return (bool) $this->getSelection()
			->where('?name = ?', $idCol, $id)
			->update($values);
	}


	/**
	 * Default callback used when an editable column has customRender.
	 */
	public function getRow(mixed $id, string $idCol): ActiveRow|bool
	{
		return $this->getSelection()
			->where('?name = ?', $idCol, $id)
			->fetch();
	}


	/*	 * ******************************** interface IDataSource *********************************** */

	public function getCount(): int
	{
		return (int) $this->selection->count('*');
	}


	public function getData(): array
	{
		return $this->selection;
	}


	public function filter(array $conditions): void
	{
		foreach ($conditions as $condition) {
			$this->makeWhere($condition);
		}
	}


	public function limit(int $offset, int $limit): void
	{
		$this->selection->limit($limit, $offset);
	}


	public function sort(array $sorting): void
	{
		foreach ($sorting as $column => $sort) {
			$this->selection->order("{$column} {$sort}");
		}
	}


	/**
	 * @throws Exception
	 */
	public function suggest(mixed $column, array $conditions, int $limit): array
	{
		$selection = clone $this->selection;
		is_string($column) && $selection->select("DISTINCT {$column}")->order($column);
		$selection->limit($limit);

		foreach ($conditions as $condition) {
			$this->makeWhere($condition, $selection);
		}

		$items = [];
		foreach ($selection as $row) {
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
