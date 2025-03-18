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
use DateTime;
use Exception;
use Nette\Forms\Controls\TextInput;
use Nette\Utils\Strings;

/**
 * Date-range input filter.
 *
 * @property string $mask
 */
final class DateRange extends Text //Date
{
	protected mixed $condition = 'BETWEEN ? AND ?';

	protected string $mask = '/(.*)\s?-\s?(.*)/';

	protected string $dateFormatInput = 'd.m.Y';

	protected array $dateFormatOutput = ['Y-m-d', 'Y-m-d G:i:s'];


	/**
	 * Sets mask by regular expression.
	 */
	public function setMask(string $mask): static
	{
		$this->mask = $mask;
		return $this;
	}


	public function getMask(): string
	{
		return $this->mask;
	}


	public function setDateFormatInput(string $format): static
	{
		$this->dateFormatInput = $format;
		return $this;
	}


	public function getDateFormatInput(): string
	{
		return $this->dateFormatInput;
	}


	public function setDateFormatOutput(string $formatFrom, ?string $formatTo = null): static
	{
		$formatTo ??= $formatFrom;

		$this->dateFormatOutput = [$formatFrom, $formatTo];
		return $this;
	}


	public function getDateFormatOutput(): array
	{
		return $this->dateFormatOutput;
	}


	#[Override]
	protected function getFormControl(): TextInput
	{
		$control = parent::getFormControl();

		$prototype = $control->getControlPrototype();
		array_pop($prototype->class); //remove "date" class
		$prototype->class[] = 'daterange';

		return $control;
	}


	/**
	 * @throws Exception
	 * @internal
	 */
	#[Override]
	public function __getCondition(mixed $value): ?Condition
	{
		if ($value === '' || $value === null) {
			return null; //skip
		}

		if ($this->where === null && is_string($this->condition)) {
			[, $from, $to] = Strings::match($value, $this->mask);
			$from = DateTime::createFromFormat($this->dateFormatInput, trim((string) $from));
			$to = DateTime::createFromFormat($this->dateFormatInput, trim((string) $to));

			if ($to && !Strings::match($this->dateFormatInput, '/G|H/i')) { //input format haven't got hour option
				\str_contains($this->dateFormatOutput[1], 'G') || \str_contains($this->dateFormatOutput[1], 'H')
					? $to->setTime(23, 59, 59)
					: $to->setTime(11, 59, 59);
			}

			$values = $from && $to
				? [$from->format($this->dateFormatOutput[0]), $to->format($this->dateFormatOutput[1])]
				: null;

			return $values
				? Condition::setup($this->getColumn(), $this->condition, $values)
				: Condition::setupEmpty();
		}

		return parent::__getCondition($value);
	}
}
