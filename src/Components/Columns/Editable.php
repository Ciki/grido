<?php

declare(strict_types=1);

/**
 * This file is part of the Grido (http://grido.bugyik.cz)
 *
 * Copyright (c) 2014 Petr Bugyík (http://petr.bugyik.cz)
 *
 * For the full copyright and license information, please view
 * the file LICENSE.md that was distributed with this source code.
 */

namespace Grido\Components\Columns;

use Grido\DataSources\Model;
use Grido\Exception;
use Grido\Grid;
use Nette\Application\Responses\JsonResponse;
use Nette\Application\Responses\TextResponse;
use Nette\Forms\Control;
use Nette\Forms\Controls\TextInput;
use Nette\Utils\Html;

/**
 * An inline editable column.
 *
 * @property ?Control $editableControl
 * @property ?callable $editableCallback
 * @property ?callable $editableValueCallback
 * @property ?callable $editableRowCallback
 * @property bool $editable
 * @property bool $editableDisabled
 */
abstract class Editable extends Column
{
	protected bool $editable = false;

	protected bool $editableDisabled = false;

	// Custom control for inline editing
	protected ?Control $editableControl = null;

    /** @var ?callable for custom handling with edited data; function($id, $newValue, $oldValue, Editable $column) {} */
    protected $editableCallback = null;

    /** @var ?callable for custom value; function($row, Columns\Editable $column) {} */
    protected $editableValueCallback = null;

    /** @var ?callable for getting row; function($row, Columns\Editable $column) {} */
    protected $editableRowCallback = null;

	/**
	 * @param ?callable $callback function($id, $newValue, $oldValue, Columns\Editable $column) {}
	 */
	public function setEditable(?callable $callback = null, ?Control $control = null): static
	{
		$this->editable = true;
		$this->setClientSideOptions();

		$callback && $this->setEditableCallback($callback);
		$control && $this->setEditableControl($control);

		return $this;
	}


	/**
	 * Sets control for inline editation.
	 */
	public function setEditableControl(Control $control): static
	{
		$this->isEditable() ?: $this->setEditable();
		$this->editableControl = $control;

		return $this;
	}


	/**
	 * @param callable $callback function($id, $newValue, $oldValue, Columns\Editable $column) {}
	 */
	public function setEditableCallback(callable $callback): static
	{
		$this->isEditable() ?: $this->setEditable();
		$this->editableCallback = $callback;

		return $this;
	}


	/**
	 * @param callable $callback for custom value; function($row, Columns\Editable $column) {}
	 */
	public function setEditableValueCallback(callable $callback): static
	{
		$this->isEditable() ?: $this->setEditable();
		$this->editableValueCallback = $callback;

		return $this;
	}


	/**
	 * Sets editable row callback - it's required when used editable collumn with customRenderCallback
	 * @param callable $callback for getting row; function($id, Columns\Editable $column) {}
	 */
	public function setEditableRowCallback(callable $callback): static
	{
		$this->isEditable() ?: $this->setEditable();
		$this->editableRowCallback = $callback;

		return $this;
	}


	public function disableEditable(): static
	{
		$this->editable = false;
		$this->editableDisabled = true;

		return $this;
	}


	/**
	 * @throws Exception
	 */
	protected function setClientSideOptions(): void
	{
		$options = $this->grid->getClientSideOptions();
		if (!isset($options['editable'])) { //only once
			$this->grid->setClientSideOptions([
				'editable' => true,
			]);
			$this->grid->onRender[] = function (Grid $grid): void {
				foreach ($grid->getComponent(Column::ID)->getComponents() as $column) {
					if (!$column instanceof self || !$column->isEditable()) {
						continue;
					}

					$colDb = $column->getColumn();
					$colName = $column->getName();
					$isMissing = fn (string $method): bool => $grid->model instanceof Model
						? !method_exists($grid->model->dataSource, $method)
						: true;

					if (($column->editableCallback === null && (!is_string($colDb) || strpos($colDb, '.'))) ||
						($column->editableCallback === null && $isMissing('update'))
					) {
						$msg = "Column '{$colName}' has error: You must define callback via setEditableCallback().";
						throw new Exception($msg);
					}

					if ($column->editableRowCallback === null && $column->customRender && $isMissing('getRow')) {
						$msg = "Column '{$colName}' has error: You must define callback via setEditableRowCallback().";
						throw new Exception($msg);
					}
				}
			};
		}
	}


	/**********************************************************************************************/


	/**
	 * Returns header cell prototype (<th> html tag).
	 */
	public function getHeaderPrototype(): Html
	{
		$th = parent::getHeaderPrototype();

		if ($this->isEditable()) {
			$th->setAttribute('data-grido-editable-handler', $this->link('editable!'));
			$th->setAttribute('data-grido-editableControl-handler', $this->link('editableControl!'));
		}

		return $th;
	}


	/**
	 * Returns cell prototype (<td> html tag).
	 */
	public function getCellPrototype(mixed $row = null): Html
	{
		$td = parent::getCellPrototype($row);

		if ($this->isEditable() && $row !== null) {
			if (!in_array('editable', $td->class, true)) {
				$td->class[] = 'editable';
			}

			$value = $this->editableValueCallback === null
				? $this->getValue($row)
				: call_user_func_array($this->editableValueCallback, [$row, $this]);

			$td->setAttribute('data-grido-editable-value', $value);
		}

		return $td;
	}


	public function getEditableControl(): TextInput|Control
	{
		if ($this->editableControl === null) {
			$this->editableControl = new TextInput();
			$this->editableControl->controlPrototype->class[] = 'form-control';
		}

		return $this->editableControl;
	}


	/**
	 * @internal
	 */
	public function getEditableCallback(): ?callable
	{
		return $this->editableCallback;
	}


	/**
	 * @internal
	 */
	public function getEditableValueCallback(): ?callable
	{
		return $this->editableValueCallback;
	}


	/**
	 * @internal
	 */
	public function getEditableRowCallback(): ?callable
	{
		return $this->editableRowCallback;
	}


	/**
	 * @internal
	 */
	public function isEditable(): bool
	{
		return $this->editable;
	}


	/**
	 * @internal
	 */
	public function isEditableDisabled(): bool
	{
		return $this->editableDisabled;
	}


	/**********************************************************************************************/


	/**
	 * @internal
	 */
	public function handleEditable(mixed $id, mixed $newValue, mixed $oldValue): void
	{
		$this->grid->onRender($this->grid);

		if (!$this->presenter->isAjax() || !$this->isEditable()) {
			$this->presenter->terminate();
		}

		$success = $this->editableCallback
			? call_user_func_array($this->editableCallback, [$id, $newValue, $oldValue, $this])
			: $this->grid->model->update($id, [
				$this->getColumn() => $newValue,
			], $this->grid->primaryKey);

		if (is_callable($this->customRender)) {
			$row = $this->editableRowCallback
				? call_user_func_array($this->editableRowCallback, [$id, $this])
				: $this->grid->model->getRow($id, $this->grid->primaryKey);
			$html = call_user_func_array($this->customRender, [$row]);
		} else {
			$html = $this->formatValue($newValue);
		}

		$payload = [
			'updated' => (bool) $success,
			'html' => (string) $html,
		];
		$response = new JsonResponse($payload);
		$this->presenter->sendResponse($response);
	}


	/**
	 * @internal
	 */
	public function handleEditableControl(mixed $value): void
	{
		$this->grid->onRender($this->grid);

		if (!$this->presenter->isAjax() || !$this->isEditable()) {
			$this->presenter->terminate();
		}

		$control = $this->getEditableControl();
		$control->setValue($value);

		$this->getForm()->addComponent($control, 'edit' . $this->getName());

		$response = new TextResponse($control->getControl()->render());
		$this->presenter->sendResponse($response);
	}
}
