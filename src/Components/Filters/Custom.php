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

use Grido\Grid;
use Nette\Forms\Control;

/**
 * Filter with custom form control.
 *
 * @property-read Control $formControl
 * @property-read ?Control $control
 * @method ?Control getControl()
 */
final class Custom extends Filter
{
	public function __construct(
		Grid $grid,
		string $name,
		string $label,
		protected Control $formControl
	) {
		parent::__construct($grid, $name, $label);
	}


	/**
	 * @internal
	 */
	public function getFormControl(): Control
	{
		return $this->formControl;
	}
}
