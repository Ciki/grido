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

namespace Grido\Components;

use Grido\Grid;

/**
 * Paginating grid.
 *
 * @property-read int $page
 * @property-read array $steps
 * @property-read int $countEnd
 * @property-read int $countBegin
 * @property-write Grid $grid
 */
final class Paginator extends \Nette\Utils\Paginator
{
	public const DEFAULT_STEP_COUNT = 4;
	public const DEFAULT_STEP_RANGE = 3;

	protected ?int $page = null;

	protected array $steps = [];

	protected ?int $countBegin = null;

	protected ?int $countEnd = null;

	protected Grid $grid;

	private int $stepCount = self::DEFAULT_STEP_COUNT;

	private int $stepRange = self::DEFAULT_STEP_RANGE;


	public function setGrid(Grid $grid): self
	{
		$this->grid = $grid;
		return $this;
	}


	public function setStepRange(int $stepRange): self
	{
		$this->stepRange = $stepRange;
		return $this;
	}


	public function setStepCount(int $stepCount): self
	{
		$this->stepCount = $stepCount;
		return $this;
	}


	/*	 * ******************************************************************************************* */

	public function getPage(): int
	{
		if ($this->page === null) {
			$this->page = parent::getPage();
		}

		return $this->page;
	}


	public function getSteps(): array
	{
		if (empty($this->steps)) {
			$arr = range(
				max($this->getFirstPage(), $this->getPage() - $this->stepRange),
				min($this->getLastPage(), $this->getPage() + $this->stepRange)
			);

			$quotient = ($this->getPageCount() - 1) / $this->stepCount;

			for ($i = 0; $i <= $this->stepCount; $i++) {
				$arr[] = (int) (round($quotient * $i) + $this->getFirstPage());
			}

			sort($arr);
			$this->steps = array_values(array_unique($arr));
		}

		return $this->steps;
	}


	public function getCountBegin(): int
	{
		if ($this->countBegin === null) {
			$this->countBegin = $this->grid->getCount() > 0 ? $this->getOffset() + 1 : 0;
		}

		return $this->countBegin;
	}


	public function getCountEnd(): int
	{
		if ($this->countEnd === null) {
			$this->countEnd = $this->grid->getCount() > 0 ? min($this->grid->getCount(), $this->getPage() * $this->grid->getPerPage()) : 0;
		}

		return $this->countEnd;
	}
}
