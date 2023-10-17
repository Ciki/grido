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

namespace Grido\Components\Filters;

use Grido\Components\Component;
use Grido\Exception;
use Grido\Grid;
use Grido\Helpers;
use Nette\Forms\Controls\BaseControl;
use Nette\Utils\Html;

/**
 * Data filtering.
 *
 * @property-read array $column
 * @property-read ?Html $wrapperPrototype
 * @property-read ?BaseControl $control
 * @property-write mixed $condition
 * @property-write ?callable $where
 * @property-write ?string $formatValue
 * @property-write string $defaultValue
 */
abstract class Filter extends Component
{
	public const ID = 'filters';
	public const VALUE_IDENTIFIER = '%value';
	public const RENDER_INNER = 'inner';
	public const RENDER_OUTER = 'outer';

	protected mixed $optional;

	protected array $column = [];

	protected mixed $condition = '= ?';

	/** @var ?callable */
	protected $where = null;

	protected ?string $formatValue = null;

	protected ?Html $wrapperPrototype = null;

	protected ?BaseControl $control = null;


	public function __construct(Grid $grid, string $name, string $label)
	{
		$name = Helpers::formatColumnName($name);
		$this->addComponentToGrid($grid, $name);

		$this->label = $label;
		$this->type = static::class;

		$form = $this->getForm();
		$filters = $form->getComponent(self::ID, false);
		if ($filters === null) {
			$filters = $form->addContainer(self::ID);
		}

		$filters->addComponent($this->getFormControl(), $name);
	}


	/**********************************************************************************************/


	/**
	 * Map to database column.
	 * @throws Exception
	 */
	public function setColumn(string $column, string $operator = Condition::OPERATOR_OR): static
	{
		$columnAlreadySet = count($this->column) > 0;
		if (!Condition::isOperator($operator) && $columnAlreadySet) {
			$msg = sprintf("Operator must be '%s' or '%s'.", Condition::OPERATOR_AND, Condition::OPERATOR_OR);
			throw new Exception($msg);
		}

		if ($columnAlreadySet) {
			$this->column[] = $operator;
			$this->column[] = $column;
		} else {
			$this->column[] = $column;
		}

		return $this;
	}


	/**
	 * Sets custom condition.
	 */
	public function setCondition(mixed $condition): static
	{
		$this->condition = $condition;
		return $this;
	}


	/**
	 * Sets custom "sql" where.
	 * @param callable $callback function($value, $source) {}
	 */
	public function setWhere(callable $callback): static
	{
		$this->where = $callback;
		return $this;
	}


	/**
	 * Sets custom format value.
	 * @param string $format for example: "%%value%"
	 */
	public function setFormatValue(string $format): static
	{
		$this->formatValue = $format;
		return $this;
	}


	public function setDefaultValue(string $value): static
	{
		$this->grid->setDefaultFilter([
			$this->getName() => $value,
		]);
		return $this;
	}


	/**********************************************************************************************/


	/**
	 * @internal
	 */
	public function getColumn(): array
	{
		if (empty($this->column)) {
			$column = $this->getName();
			if ($columnComponent = $this->grid->getColumn($column, false)) {
				$column = $columnComponent->column; //use db column from column compoment
			}

			$this->setColumn($column);
		}

		return $this->column;
	}


	/**
	 * @internal
	 */
	public function getControl(): BaseControl
	{
		if ($this->control === null) {
			$this->control = $this->getForm()->getComponent(self::ID)->getComponent($this->getName());
		}

		return $this->control;
	}


	/**
	 * @throws Exception
	 */
	protected function getFormControl()
	{
		throw new Exception("Filter {$this->name} cannot be use, because it is not implement getFormControl() method.");
	}


	/**
	 * Returns wrapper prototype (<th> html tag).
	 */
	public function getWrapperPrototype(): Html
	{
		if ($this->wrapperPrototype === null) {
			$this->wrapperPrototype = Html::el('th')
				->setClass(['grid-filter-' . $this->getName()]);
		}

		return $this->wrapperPrototype;
	}


	public function getCondition(): mixed
	{
		return $this->condition;
	}


	/**
	 * @throws Exception
	 * @internal
	 */
	public function __getCondition(mixed $value): ?Condition
	{
		if ($value === '' || $value === null) {
			return null; //skip
		}

		$condition = $this->getCondition();

		if ($this->where !== null) {
			$condition = Condition::setupFromCallback($this->where, $value);
		} elseif (is_string($condition)) {
			$condition = Condition::setup($this->getColumn(), $condition, $this->formatValue($value));
		} elseif (is_callable($condition)) {
			$condition = call_user_func_array($condition, [$value]);
		} elseif (is_array($condition)) {
			$condition = $condition[$value] ?? Condition::setupEmpty();
		}

		if (is_array($condition)) { //for user-defined condition by array or callback
			$condition = Condition::setupFromArray($condition);
		} elseif ($condition !== null && !$condition instanceof Condition) {
			$type = gettype($condition);
			throw new Exception("Condition must be array or Condition object. {$type} given.");
		}

		return $condition;
	}


	/**********************************************************************************************/


	/**
	 * Format value for database.
	 */
	protected function formatValue(mixed $value): mixed
	{
		if ($this->formatValue !== null) {
			return str_replace(static::VALUE_IDENTIFIER, (is_array($value) ? $value : (string) $value), $this->formatValue);
		}
		return $value;
	}


	/**
	 * Value representation in URI.
	 * @internal
	 */
	public function changeValue(mixed $value): mixed
	{
		return $value;
	}
}
