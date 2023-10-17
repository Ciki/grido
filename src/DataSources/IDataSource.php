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

namespace Grido\DataSources;

/**
 * The interface defines methods that must be implemented by each data source.
 */
interface IDataSource
{
	public function getCount(): int;


	public function getData(): array;


	public function filter(array $condition): void;


	public function limit(int $offset, int $limit): void;


	public function sort(array $sorting): void;


	public function suggest(mixed $column, array $conditions, int $limit): array;
}
