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

use Ciki\Forms\Controls\MultiSelectBox;
use Ciki\Forms\Controls\SelectBox;
use Grido\Grid;

/**
 * Select box filter.
 *
 * @package     Grido
 * @subpackage  Components\Filters
 * @author      Petr Bugyík
 *
 * @property-read SelectBox|MultiSelectBox|null $control
 * @method SelectBox|MultiSelectBox|null getControl()
 */
class Select extends Filter
{
	private bool $multiple = false;


	/**
	 * @param array $items for select
	 */
	public function __construct(Grid $grid, string $name, string $label, array $items = null, bool $multiple = false)
	{
		$this->multiple = $multiple;
		parent::__construct($grid, $name, $label);

		if ($items !== null) {
			$this->getControl()->setItems($items);
		}
	}


	protected function getFormControl(): SelectBox|MultiSelectBox
	{
		return $this->multiple ? new MultiSelectBox($this->label) : new SelectBox($this->label);
	}
}
