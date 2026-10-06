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

namespace Grido\Components\Columns;

use Override;
use Grido\Grid;
use Nette\Utils\Strings;

/**
 * Number column.
 *
 * @property array $numberFormat
 */
/*final*/ class Number extends Editable
{
	protected array $numberFormat = [
		self::NUMBER_FORMAT_DECIMALS => null,
		self::NUMBER_FORMAT_DECIMAL_POINT => '.',
		self::NUMBER_FORMAT_THOUSANDS_SEPARATOR => ',',
		self::NUMBER_FORMAT_DECIMALS_MAX => 2,
	];

	/** @const keys of array $numberFormat */
	public const int NUMBER_FORMAT_DECIMALS = 0;
	public const int NUMBER_FORMAT_DECIMAL_POINT = 1;
	public const int NUMBER_FORMAT_THOUSANDS_SEPARATOR = 2;
	public const int NUMBER_FORMAT_DECIMALS_MAX = 3;


	/**
	 * @param ?int $decimals number of decimal points
	 * @param ?string $decPoint separator for the decimal point
	 * @param ?string $thousandsSep thousands separator
	 * @param ?int $decimalsMax max number of decimal points, applies if $decimals === null
	 */
	public function __construct(
		Grid $grid,
		string $name,
		string $label,
		?int $decimals = null,
		?string $decPoint = null,
		?string $thousandsSep = null,
		?int $decimalsMax = null,
	) {
		parent::__construct($grid, $name, $label);

		$this->setNumberFormat($decimals, $decPoint, $thousandsSep, $decimalsMax);
	}


	/**
	 * Sets number format. Params are similar to internal function number_format() params.
	 * @param ?int $decimals number of decimal points
	 * @param ?string $decPoint separator for the decimal point
	 * @param ?string $thousandsSep thousands separator
	 * @param ?int $decimalsMax max number of decimal points, applies if $decimals === null
	 */
	public function setNumberFormat(
		?int $decimals = null,
		?string $decPoint = null,
		?string $thousandsSep = null,
		?int $decimalsMax = null,
	): static {
		$this->numberFormat[self::NUMBER_FORMAT_DECIMALS] = $decimals;

		if ($decPoint !== null) {
			$this->numberFormat[self::NUMBER_FORMAT_DECIMAL_POINT] = $decPoint;
		}

		if ($thousandsSep !== null) {
			$this->numberFormat[self::NUMBER_FORMAT_THOUSANDS_SEPARATOR] = $thousandsSep;
		}

		if ($decimalsMax !== null) {
			$this->numberFormat[self::NUMBER_FORMAT_DECIMALS_MAX] = $decimalsMax;
		}

		return $this;
	}


	public function getNumberFormat(): array
	{
		return $this->numberFormat;
	}


	#[Override]
	protected function formatValue(mixed $value): mixed
	{
		$value = parent::formatValue($value);
		if (!is_numeric($value)) {
			return $value;
		}

		$decimals = $this->numberFormat[self::NUMBER_FORMAT_DECIMALS];
		$decPoint = $this->numberFormat[self::NUMBER_FORMAT_DECIMAL_POINT];
		$thousandsSep = $this->numberFormat[self::NUMBER_FORMAT_THOUSANDS_SEPARATOR];
		$decimalsMax = $this->numberFormat[self::NUMBER_FORMAT_DECIMALS_MAX];

		$value = (float) $value;
		// show as many decimals as needed, max self::NUMBER_FORMAT_DECIMALS_MAX
		if ($decimals === null) {
			$valueStr = (string) $value;
			$decimals = min(strlen(Strings::after($valueStr, '.', -1) ?? ''), $decimalsMax);
		}
		return number_format($value, $decimals, $decPoint, $thousandsSep);
	}
}
