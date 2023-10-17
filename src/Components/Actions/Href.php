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

use Grido\Grid;
use Nette\Utils\Html;

/**
 * Href action.
 *
 * @property-write array $customHref
 * @property-read string $destination
 * @property-read array $arguments
 */
final class Href extends Action
{
	/** @var ?callable for custom href attribute creating */
	protected $customHref = null;


	/**
	 * @param string $destination - first param for method $presenter->link()
	 * @param array $arguments - second param for method $presenter->link()
	 */
	public function __construct(
		Grid $grid,
		string $name,
		string $label,
		protected ?string $destination = null,
		protected array $arguments = []
	) {
		parent::__construct($grid, $name, $label);
	}


	public function setCustomHref(callable $callback): static
	{
		$this->customHref = $callback;
		return $this;
	}


	/**********************************************************************************************/


	/**
	 * @internal
	 */
	public function getElement(mixed $row): Html
	{
		$element = parent::getElement($row);

		if ($this->customHref) {
			$href = call_user_func_array($this->customHref, [$row]);
		} else {
			$primaryKey = $this->getPrimaryKey();
			$primaryValue = $this->grid->getProperty($row, $primaryKey);

			$this->arguments[$primaryKey] = $primaryValue;
			$href = $this->presenter->link($this->getDestination(), $this->arguments);
		}

		$element->href($href);

		return $element;
	}


	/**
	 * @internal
	 */
	public function getDestination(): string
	{
		if ($this->destination === null) {
			$this->destination = $this->getName();
		}

		return $this->destination;
	}


	/**
	 * @internal
	 */
	public function getArguments(): array
	{
		return $this->arguments;
	}
}
