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
use DateTimeInterface;
use Grido\Grid;
use Latte\Runtime\HtmlHelpers;

/**
 * Date column.
 *
 * @property string $dateFormat
 */
/*final*/ class Date extends Editable
{
	public const FORMAT_TEXT = 'd M Y';
	public const FORMAT_DATE = 'd.m.Y';
	public const FORMAT_DATETIME = 'd.m.Y H:i:s';


	public function __construct(
		Grid $grid,
		string $name,
		string $label,
		protected string $dateFormat = self::FORMAT_DATE
	) {
		parent::__construct($grid, $name, $label);
	}


	public function setDateFormat(string $format): static
	{
		$this->dateFormat = $format;
		return $this;
	}


	public function getDateFormat(): string
	{
		return $this->dateFormat;
	}


	#[Override]
	protected function formatValue(mixed $value): mixed
	{
		if ($value === null || is_bool($value)) {
			return $this->applyReplacement($value);
		} elseif (is_scalar($value)) {
			$value = HtmlHelpers::escapeText($value);
			$replaced = $this->applyReplacement($value);
			if ($value !== $replaced && is_scalar($replaced)) {
				return $replaced;
			}
		}

		if ($value instanceof DateTimeInterface) {
			return $value->format($this->dateFormat);
		}

		// a timestamp arrives escaped to a string by now; an unparsable value is shown as it is
		$timestamp = is_numeric($value) ? (int) $value : strtotime((string) $value);
		return $timestamp === false ? $value : date($this->dateFormat, $timestamp);
	}


	/**
	 * @internal
	 */
	#[Override]
	public function renderExport(mixed $row): mixed
	{
		if (is_callable($this->customRenderExport)) {
			return call_user_func_array($this->customRenderExport, [$row]);
		}

		$value = $this->getValue($row);
		return $this->formatValue($value);
	}
}
