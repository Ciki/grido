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

use DateTime;
use Exception;
use Nette\Forms\Controls\TextInput;

/**
 * Date input filter.
 *
 * @property string $dateFormatInput
 * @property string $dateFormatOutput
 */
final class Date extends Text
{
	// protected ?string $formatValue;

	protected string $dateFormatInput = 'd.m.Y';

	protected string $dateFormatOutput = 'Y-m-d%';


	public function setDateFormatInput(string $format): static
	{
		$this->dateFormatInput = $format;
		return $this;
	}


	public function getDateFormatInput(): string
	{
		return $this->dateFormatInput;
	}


	public function setDateFormatOutput(string $format): static
	{
		$this->dateFormatOutput = $format;
		return $this;
	}


	public function getDateFormatOutput(): string
	{
		return $this->dateFormatOutput;
	}


	protected function getFormControl(): TextInput
	{
		$control = parent::getFormControl();
		$control->getControlPrototype()->class[] = 'date';
		$control->getControlPrototype()->attrs['autocomplete'] = 'off';

		return $control;
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

		$condition = $this->condition;
		if ($this->where === null && is_string($condition)) {
			$column = $this->getColumn();
			return ($date = DateTime::createFromFormat($this->dateFormatInput, $value))
				? Condition::setupFromArray([$column, $condition, $date->format($this->dateFormatOutput)])
				: Condition::setupEmpty();
		}

		return parent::__getCondition($value);
	}
}
