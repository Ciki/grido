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

namespace Grido\Components;

use Grido\Exception;
use Grido\Grid;
use Grido\Helpers;
use Nette\Forms\Container;
use Nette\Forms\Controls\SubmitButton;

/**
 * Operation with one or more rows.
 *
 * @property-read string $primaryKey
 * @method void onSubmit(string $operation, array $ids) Description
 */
final class Operation extends Component
{
	public const ID = 'operations';

	// callback on operation submit
	public array $onSubmit;

	protected ?string $primaryKey = null;


	public function __construct(Grid $grid, array $operations, callable $onSubmit)
	{
		$this->grid = $grid;
		$grid->addComponent($this, self::ID);

		$grid['form'][$grid::BUTTONS]->addSubmit(self::ID, 'OK')
			->onClick[] = $this->handleOperations(...);

		$grid['form']->addContainer(self::ID)
			->addSelect(self::ID, 'Selected', $operations)
			->setPrompt('Grido.Selected');

		$grid->onRender[] = function (Grid $grid): void {
			$this->addCheckers($grid['form'][self::ID]);
		};

		$this->onSubmit[] = $onSubmit;
	}


	/**
	 * Set client side confirm for operation.
	 */
	public function setConfirm(string $operation, string $message): self
	{
		$message = $this->translate($message);
		$this->grid->onRender[] = function (Grid $grid) use ($operation, $message): void {
			$grid['form'][self::ID][self::ID]->getControlPrototype()->setAttribute(
				"data-grido-confirm-{$operation}",
				$message
			);
		};

		return $this;
	}


	public function setPrimaryKey(string $primaryKey): self
	{
		$this->primaryKey = $primaryKey;
		return $this;
	}


	/*	 * ******************************************************************************************* */

	public function getPrimaryKey(): string
	{
		if ($this->primaryKey === null) {
			$this->primaryKey = $this->grid->primaryKey;
		}

		return $this->primaryKey;
	}


	/*	 * ******************************************************************************************* */


	/**
	 * @internal
	 */
	public function handleOperations(SubmitButton $button): void
	{
		$grid = $this->getGrid();
		!empty($grid->onRegistered) && $grid->onRegistered($grid);
		$form = $button->getForm();
		$this->addCheckers($form[self::ID]);

		$values = $form[self::ID]->values;
		if (empty($values[self::ID])) {
			$httpData = $form->getHttpData();
			if (!empty($httpData[self::ID][self::ID]) && $operation = $httpData[self::ID][self::ID]) {
				$grid->__triggerUserNotice("Operation with name '{$operation}' does not exist.");
			}

			$grid->reload();
		}

		$ids = [];
		$operation = $values[self::ID];
		unset($values[self::ID]);

		foreach ($values as $key => $val) {
			if ($val) {
				$ids[] = $key;
			}
		}

		$this->onSubmit($operation, $ids);
		$grid->page = 1;

		if ($this->getPresenter()->isAjax()) {
			$grid['form'][self::ID][self::ID]->setValue(null);
			$grid->getData(true, false);
		}

		$grid->reload();
	}


	/**
	 * @throws Exception
	 * @internal
	 */
	public function addCheckers(Container $container): void
	{
		$items = $this->grid->getData();
		$primaryKey = $this->getPrimaryKey();

		foreach ($items as $item) {
			try {
				$primaryValue = $this->grid->getProperty($item, $primaryKey);
				if (!isset($container[$primaryValue])) {
					$container->addCheckbox(Helpers::formatColumnName((string) $primaryValue))
						->controlPrototype->title = $primaryValue;
				}
			} catch (\Exception) {
				throw new Exception(
					'You should define some else primary key via $grid->setPrimaryKey() ' .
						"because currently defined '{$primaryKey}' key is not suitable for operation feature."
				);
			}
		}
	}
}
