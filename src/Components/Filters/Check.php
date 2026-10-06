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

use Override;
use Nette\Forms\Controls\Checkbox;

/**
 * Check box filter.
 *
 * @property-read Checkbox|null $control
 * @method Checkbox|null getControl()
 */
final class Check extends Filter
{
	/* representation true in URI */
	public const TRUE = '✓';

	protected mixed $condition = 'IS NOT NULL';


	protected function getFormControl(): Checkbox
	{
		$control = new Checkbox($this->label);
		$control->getControlPrototype()->class[] = 'checkbox';
		return $control;
	}


	/**
	 * @internal
	 */
	#[Override]
	public function __getCondition(mixed $value): ?Condition
	{
		// the URL carries the check mark, a default filter or older URLs true / 1
		$value = in_array($value, [self::TRUE, true, 1, '1'], true);

		return parent::__getCondition($value);
	}


	/**
	 * @internal
	 */
	#[Override]
	public function formatValue(mixed $value): mixed
	{
		return null;
	}


	/**
	 * @internal
	 */
	#[Override]
	public function changeValue(mixed $value): mixed
	{
		return (bool) $value === true
			? self::TRUE
			: $value;
	}
}
