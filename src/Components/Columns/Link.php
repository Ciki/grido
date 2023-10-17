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
 * Link column.
 */
class Link extends Text
{
	protected function formatValue(mixed $value): Html
	{
		return $this->getAnchor($value);
	}


	protected function formatHref(string $value): string
	{
		if (!preg_match('~^\w+://~i', $value)) {
			$value = 'http://' . $value;
		}

		return $value;
	}


	protected function formatText(string $value): string
	{
		return preg_replace('~^https?://~i', '', $value);
	}


	protected function getAnchor(mixed $value): Html
	{
		$truncate = $this->truncate;
		$this->truncate = null;

		$value = (string) parent::formatValue($value);
		$href = $this->formatHref($value);
		$text = $this->formatText($value);

		$anchor = Html::el('a')
			->setHref($href)
			->setText($text)
			->setTarget('_blank')
			->setRel('noreferrer');

		if ($truncate) {
			$anchor->setText($truncate($text))
				->setTitle($value);
		}

		return $anchor;
	}
}
