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

namespace Grido;

/**
 * Helpers.
 */
final class Helpers
{
	public static function formatColumnName(string $name): string
	{
		return str_replace('.', '__', $name);
	}


	public static function unformatColumnName(string $name): string
	{
		return str_replace('__', '.', $name);
	}
}
