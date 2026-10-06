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

use Grido\Grid;
use Nette\Application\UI\Form;
use Nette\ComponentModel\Container;

/**
 * Base of grid components.
 *
 * @property-read string $label
 * @property-read string $type
 * @property-read Grid $grid
 * @property-read Form $form
 */
abstract class Component extends \Nette\Application\UI\Component
{
	/** Name of the grid container holding this type of component - every type sets its own */
	public const string ID = '';

	protected string $label;

	protected string $type;

	protected Grid $grid;

	protected ?Form $form = null;


	public function getGrid(): Grid
	{
		return $this->grid;
	}


	public function getForm(): Form
	{
		if ($this->form === null) {
			$this->form = $this->grid->getComponent('form');
		}

		return $this->form;
	}


	/**
	 * @internal
	 */
	public function getLabel(): string
	{
		return $this->label;
	}


	/**
	 * @internal
	 */
	public function getType(): string
	{
		return $this->type;
	}


	protected function addComponentToGrid(Grid $grid, string $name): Container
	{
		$this->grid = $grid;

		// check container exist
		$container = $this->grid->getComponent($this::ID, false);
		if (!$container) {
			$this->grid->addComponent(new Container(), $this::ID);
			$container = $this->grid->getComponent($this::ID);
		}

		return $container->addComponent($this, $name);
	}


	protected function translate(string $message): string
	{
		return call_user_func_array([$this->grid->getTranslator(), 'translate'], func_get_args());
	}
}
