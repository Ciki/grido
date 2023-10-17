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

namespace Grido\Components\Actions;

use Grido\Components\Component;
use Grido\Exception;
use Grido\Grid;
use Nette\Utils\Html;

/**
 * Action on one row.
 *
 * @property-read Html $element
 * @property-write ?callable $customRender
 * @property-write ?callable $disable
 * @property ?Html $elementPrototype
 * @property ?string $primaryKey
 * @property array $options
 */
abstract class Action extends Component
{
	final public const ID = 'actions';

	protected ?Html $elementPrototype = null;

	/** @var callable for custom rendering */
	protected $customRender = null;

	// name of primary key f.e.: link->('Article:edit', array($primaryKey => 1))
	protected ?string $primaryKey = null;

	/** @var callable for disabling */
	protected $disable = null;

	protected array $options = [];


	public function __construct(Grid $grid, string $name, string $label)
	{
		$this->addComponentToGrid($grid, $name);

		$this->type = static::class;
		$this->label = $this->translate($label);
	}


	public function setElementPrototype(Html $elementPrototype): static
	{
		$this->elementPrototype = $elementPrototype;
		return $this;
	}


	public function setCustomRender(callable $callback): static
	{
		$this->customRender = $callback;
		return $this;
	}


	public function setPrimaryKey(string $primaryKey): static
	{
		$this->primaryKey = $primaryKey;
		return $this;
	}


	/**
	 * Sets callback for disable.
	 * Callback should return true if the action is not allowed for current item.
	 */
	public function setDisable(callable $callback): static
	{
		$this->disable = $callback;
		return $this;
	}


	/**
	 * Sets client side confirm.
	 */
	public function setConfirm(string|callable $confirm): static
	{
		$this->setOption('confirm', $confirm);
		return $this;
	}


	public function setIcon(string $name): static
	{
		$this->setOption('icon', $name);
		return $this;
	}


	/**
	 * Sets user-specific option.
	 */
	public function setOption(string $key, mixed $value): static
	{
		if ($value === null) {
			unset($this->options[$key]);
		} else {
			$this->options[$key] = $value;
		}

		return $this;
	}


	/**********************************************************************************************/


	/**
	 * @throws Exception
	 */
	public function getElementPrototype(): Html
	{
		if ($this->elementPrototype === null) {
			$this->elementPrototype = Html::el('a')
				->setClass(['grid-action-' . $this->getName()])
				->setText($this->label);
		}

		if (isset($this->elementPrototype->class)) {
			$this->elementPrototype->class = (array) $this->elementPrototype->class;
		}

		return $this->elementPrototype;
	}


	/**
	 * @internal
	 */
	public function getPrimaryKey(): string
	{
		if ($this->primaryKey === null) {
			$this->primaryKey = $this->grid->getPrimaryKey();
		}

		return $this->primaryKey;
	}


	/**
	 * @internal
	 */
	public function getElement(mixed $row): Html
	{
		$element = clone $this->getElementPrototype();

		if ($confirm = $this->getOption('confirm')) {
			$confirm = is_callable($confirm)
				? call_user_func_array($confirm, [$row])
				: $confirm;

			$value = is_array($confirm)
				? vsprintf($this->translate(array_shift($confirm)), $confirm)
				: $this->translate($confirm);

			$element->setAttribute('data-grido-confirm', $value);
		}

		return $element;
	}


	/**
	 * Returns user-specific option.
	 */
	public function getOption(string $key, mixed $default = null): mixed
	{
		return $this->options[$key] ?? $default;
	}


	/**
	 * Returns user-specific options.
	 */
	public function getOptions(): array
	{
		return $this->options;
	}


	/**********************************************************************************************/


	/**
	 * @throws Exception
	 */
	public function render(mixed $row): void
	{
		if (!$row || ($this->disable && call_user_func_array($this->disable, [$row]))) {
			return;
		}

		$element = $this->getElement($row);

		if ($this->customRender) {
			echo call_user_func_array($this->customRender, [$row, $element]);
			return;
		}

		echo $element->render();
	}
}
