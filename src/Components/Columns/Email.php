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

use Nette\Utils\Html;

/**
 * Email column.
 */
final class Email extends Link
{
	protected function formatHref(string $value): string
	{
		return 'mailto:' . $value;
	}


	protected function getAnchor(mixed $value): Html
	{
		$anchor = parent::getAnchor($value);
		unset($anchor->attrs['target']);
		unset($anchor->attrs['rel']);

		return $anchor;
	}
}
