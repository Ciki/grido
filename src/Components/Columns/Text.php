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
use Closure;
use Nette\Utils\Strings;

/**
 * Text column.
 */
class Text extends Editable
{
	protected ?Closure $truncate = null;


	/**
	 * @param int $maxLen maximal length in characters
	 * @param string $append UTF-8 encoding
	 */
	public function setTruncate(int $maxLen, string $append = "\xE2\x80\xA6"): Column
	{
		$this->truncate = fn (string $string): string => Strings::truncate($string, $maxLen, $append);

		return $this;
	}


	#[Override]
	protected function formatValue(mixed $value): mixed
	{
		$value = parent::formatValue($value);

		if ($this->truncate) {
			$truncate = $this->truncate;
			$value = $truncate($value);
		}

		return $value;
	}
}
