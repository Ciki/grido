<?php

declare(strict_types=1);

/**
 * This file is part of the Grido (http://grido.bugyik.cz)
 *
 * Copyright (c) 2011 Petr Bugyík (http://petr.bugyik.cz)
 *
 * For the full copyright and license information, please view
 * the file license.md that was distributed with this source code.
 */

namespace Grido\Components;

use Grido\Components\Actions\Action;
use Grido\Components\Actions\Event;
use Grido\Components\Actions\Href;
use Grido\Components\Columns\Column;
use Grido\Components\Columns\Date;
use Grido\Components\Columns\Editable;
use Grido\Components\Columns\Email;
use Grido\Components\Columns\Link;
use Grido\Components\Columns\Number;
use Grido\Components\Columns\Text;
use Grido\Components\Exports\BaseExport;
use Grido\Components\Exports\CsvExport;
use Grido\Components\Filters\Check;
use Grido\Components\Filters\Custom;
use Grido\Components\Filters\DateRange;
use Grido\Components\Filters\Filter;
use Grido\Components\Filters\Select;
use Grido\Grid;
use Grido\Helpers;
use Iterator;
use Nette\Forms\Control;

/**
 * Container of grid components.
 */
abstract class Container extends \Nette\Application\UI\Control
{
	protected ?bool $hasColumns = null;

	protected ?bool $hasFilters = null;

	protected ?bool $hasActions = null;

	protected ?bool $hasOperation = null;

	protected ?bool $hasExport = null;

	protected ?bool $hasButtons = null;


	public function getColumn(string $name, bool $need = true): ?Column
	{
		return $this->hasColumns()
			? $this->getComponent(Column::ID)->getComponent(Helpers::formatColumnName($name), $need)
			: null;
	}


	public function getFilter(string $name, bool $need = true): ?Filter
	{
		return $this->hasFilters()
			? $this->getComponent(Filter::ID)->getComponent(Helpers::formatColumnName($name), $need)
			: null;
	}


	public function getAction(string $name, bool $need = true): ?Action
	{
		return $this->hasActions()
			? $this->getComponent(Action::ID)->getComponent($name, $need)
			: null;
	}


	public function getOperation(bool $need = true): ?Operation
	{
		return $this->getComponent(Operation::ID, $need);
	}


	public function getExport(?string $name = null, bool $need = true): ?BaseExport
	{
		if (is_bool($name) || $name === null) { // deprecated
			trigger_error('This usage of ' . __METHOD__ . '() is deprecated,
            please write name of export to first parameter.', E_USER_DEPRECATED);
			$export = $this->getComponent(BaseExport::ID, $name);
			if ($export) {
				$export = $export->getComponent(CsvExport::CSV_ID, is_bool($name) ? $name : true);
			}
			return $export;
		}
		return $this->hasExport()
			? $this->getComponent(BaseExport::ID)->getComponent(Helpers::formatColumnName($name), $need)
			: null;
	}


	/**
	 * @return ?BaseExport[]
	 * @return Iterator<(int | string), IComponent>
	 */
	public function getExports(bool $need = true)//: ?array
	{
		$export = $this->getComponent(BaseExport::ID, $need);
		if ($export) {
			$export = $export->getComponents();
		}
		return $export;
	}


	public function getButton(string $name, bool $need = true): Button
	{
		return $this->hasButtons()
			? $this->getComponent(Button::ID)->getComponent($name, $need)
			: null;
	}


	/**********************************************************************************************/


	/**
	 * @internal
	 */
	public function hasColumns(bool $useCache = true): bool
	{
		$hasColumns = $this->hasColumns;

		if ($hasColumns === null || $useCache === false) {
			$container = $this->getComponent(Column::ID, false);
			$hasColumns = $container && count($container->getComponents()) > 0;
			$this->hasColumns = $useCache ? $hasColumns : null;
		}

		return $hasColumns;
	}


	/**
	 * @internal
	 */
	public function hasFilters(bool $useCache = true): bool
	{
		$hasFilters = $this->hasFilters;

		if ($hasFilters === null || $useCache === false) {
			$container = $this->getComponent(Filter::ID, false);
			$hasFilters = $container && count($container->getComponents()) > 0;
			$this->hasFilters = $useCache ? $hasFilters : null;
		}

		return $hasFilters;
	}


	/**
	 * @internal
	 */
	public function hasActions(bool $useCache = true): bool
	{
		$hasActions = $this->hasActions;

		if ($hasActions === null || $useCache === false) {
			$container = $this->getComponent(Action::ID, false);
			$hasActions = $container && count($container->getComponents()) > 0;
			$this->hasActions = $useCache ? $hasActions : null;
		}

		return $hasActions;
	}


	/**
	 * @internal
	 */
	public function hasOperation(bool $useCache = true): bool
	{
		$hasOperation = $this->hasOperation;

		if ($hasOperation === null || $useCache === false) {
			$hasOperation = (bool) $this->getComponent(Operation::ID, false);
			$this->hasOperation = $useCache ? $hasOperation : null;
		}

		return $hasOperation;
	}


	/**
	 * @internal
	 */
	public function hasExport(bool $useCache = true): bool
	{
		$hasExport = $this->hasExport;

		if ($hasExport === null || $useCache === false) {
			$hasExport = (bool) $this->getExports(false);
			$this->hasExport = $useCache ? $hasExport : null;
		}

		return $hasExport;
	}


	/**
	 * @internal
	 */
	public function hasButtons(bool $useCache = true): bool
	{
		$hasButtons = $this->hasButtons;

		if ($hasButtons === null || $useCache === false) {
			$hasButtons = (bool) $this->getComponent(Button::ID, false);
			$this->hasButtons = $useCache ? $hasButtons : null;
		}

		return $hasButtons;
	}


	/**********************************************************************************************/

	public function addColumnText(string $name, string $label): Text
	{
		return new Text($this, $name, $label);
	}


	public function addColumnEmail(string $name, string $label): Email
	{
		return new Email($this, $name, $label);
	}


	public function addColumnLink(string $name, string $label): Link
	{
		return new Link($this, $name, $label);
	}


	public function addColumnDate(string $name, string $label, string $dateFormat = Date::FORMAT_DATE): Date
	{
		return new Date($this, $name, $label, $dateFormat);
	}


	public function addColumnNumber(
		string $name,
		string $label,
		int $decimals = 0,
		?string $decPoint = null,
		?string $thousandsSep = null
	): Number {
		return new Number($this, $name, $label, $decimals, $decPoint, $thousandsSep);
	}


	/**********************************************************************************************/

	public function addFilterText(string $name, string $label): Filters\Text
	{
		return new Filters\Text($this, $name, $label);
	}


	public function addFilterDate(string $name, string $label): Filters\Date
	{
		return new Filters\Date($this, $name, $label);
	}


	public function addFilterDateRange(string $name, string $label): DateRange
	{
		return new DateRange($this, $name, $label);
	}


	public function addFilterCheck(string $name, string $label): Check
	{
		return new Check($this, $name, $label);
	}


	public function addFilterSelect(string $name, string $label, ?array $items = null, bool $multiple = false): Select
	{
		return new Select($this, $name, $label, $items, $multiple);
	}


	public function addFilterNumber(string $name, string $label): Filters\Number
	{
		return new Filters\Number($this, $name, $label);
	}


	public function addFilterCustom(string $name, Control $formControl): Custom
	{
		return new Custom($this, $name, '', $formControl);
	}


	/**********************************************************************************************/

	public function addActionHref(string $name, string $label, ?string $destination = null, array $arguments = []): Href
	{
		return new Href($this, $name, $label, $destination, $arguments);
	}


	public function addActionEvent(string $name, string $label, ?callable $onClick = null): Event
	{
		return new Event($this, $name, $label, $onClick);
	}


	/**********************************************************************************************/

	public function setOperation(array $operations, callable $onSubmit): Operation
	{
		return new Operation($this, $operations, $onSubmit);
	}


	/**
	 * @deprecated
	 */
	public function setExport(?string $label = null): CsvExport
	{
		trigger_error(__METHOD__ . '() is deprecated; use addExport instead.', E_USER_DEPRECATED);
		return $this->addExport(new CsvExport($label), CsvExport::CSV_ID);
	}


	public function addExport(BaseExport $export, string $name): BaseExport
	{
		$container = $this->getComponent(BaseExport::ID, false);
		if (!$container) {
			$container = new \Nette\ComponentModel\Container();
			$this->addComponent($container, BaseExport::ID);
		}
		$container->addComponent($export, $name);
		return $export;
	}


	/**
	 * @param ?string $destination - first param for method $presenter->link()
	 * @param array $arguments - second param for method $presenter->link()
	 */
	public function addButton(string $name, ?string $label = null, ?string $destination = null, array $arguments = []): Button
	{
		return new Button($this, $name, $label, $destination, $arguments);
	}


	/**
	 * Sets all columns as editable.
	 * First parameter is optional and is for implementation of method for saving modified data.
	 * @param ?callback $callback function($id, $newValue, $oldValue, Editable $column) {}
	 */
	public function setEditableColumns(?callable $callback = null): static
	{
		$this->onRender[] = function (Grid $grid) use ($callback): void {
			if (!$grid->hasColumns()) {
				return;
			}

			foreach ($grid->getComponent(Column::ID)->getComponents() as $column) {
				if ($column instanceof Editable && !$column->isEditableDisabled() && !$column->editableCallback) {
					$column->setEditable($callback);
				}
			}
		};

		return $this;
	}
}
