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

use Nette\SmartObject;
use Grido\Exception;
use Nette;

/**
 * Builds filter condition.
 *
 * @package     Grido
 * @subpackage  Components\Filters
 * @author      Petr Bugyík
 *
 * @property array $column
 * @property array $condition
 * @property mixed $value
 * @property-read callable $callback
 */
final class Condition
{

    use SmartObject;

    public const OPERATOR_OR = 'OR';
    public const OPERATOR_AND = 'AND';

    protected array $column;

    protected array $condition;

    protected mixed $value;

    /** @var ?callable */
    protected $callback = null;


    public function __construct(mixed $column, mixed $condition, mixed $value = null)
    {
        $this->setColumn($column);
        $this->setCondition($condition);
        $this->setValue($value);
    }


    /**
     * @throws Exception
     */
    public function setColumn(mixed $column): static
    {
        if (is_array($column)) {
            $count = count($column);

            // check validity
            if ($count % 2 === 0) {
                throw new Exception('Count of column must be odd.');
            }

            for ($i = 0; $i < $count; $i++) {
                $item = $column[$i];
                if ($i & 1 && !self::isOperator($item)) {
                    $msg = "The even values of column must be 'AND' or 'OR', '$item' given.";
                    throw new Exception($msg);
                }
            }
        } else {
            $column = (array) $column;
        }

        $this->column = $column;
        return $this;
    }


    public function setCondition(mixed $condition): static
    {
        $this->condition = (array) $condition;
        return $this;
    }


    public function setValue(mixed $value): static
    {
        $this->value = (array) $value;
        return $this;
    }


    /**********************************************************************************************/


    public function getColumn(): array
    {
        return $this->column;
    }


    public function getCondition(): array
    {
        return $this->condition;
    }


    public function getValue(): mixed // always array type except when directly set from setup 
    {
        return $this->value;
    }


    public function getValueForColumn(): array
    {
        if (count($this->condition) > 1) {
            return $this->value;
        }

        $values = [];
        foreach ($this->getColumn() as $column) {
            if (!self::isOperator($column)) {
                foreach ($this->getValue() as $val) {
                    $values[] = $val;
                }
            }
        }

        return $values;
    }


    public function getColumnWithoutOperator(): array
    {
        $columns = [];
        foreach ($this->column as $column) {
            if (!self::isOperator($column)) {
                $columns[] = $column;
            }
        }

        return $columns;
    }


    public function getCallback(): ?callable
    {
        return $this->callback;
    }


    /**********************************************************************************************/


    /**
     * Returns true if $item is Condition:OPERATOR_AND or Condition:OPERATOR_OR else false.
     */
    public static function isOperator(string $item): bool
    {
        return in_array(strtoupper($item), [self::OPERATOR_AND, self::OPERATOR_OR]);
    }


    public static function setup(mixed $column, mixed $condition, mixed $value): static
    {
        return new self($column, $condition, $value);
    }


    public static function setupEmpty(): static
    {
        return new self(null, '0 = 1');
    }


    /**
     * @throws Exception
     */
    public static function setupFromArray(array $condition): static
    {
        if (count($condition) !== 3) {
            throw new Exception("Condition array must contain 3 items.");
        }

        return new self($condition[0], $condition[1], $condition[2]);
    }


    public static function setupFromCallback(callable $callback, mixed $value): static
    {
        /* $self = new self(null, null, $value);
        // $self->value = $value; */
        $self = new self(null, null);
        $self->value = $value; // this breaks the `array` type rule
        $self->callback = $callback;

        return $self;
    }


    /**********************************************************************************************/


    /**
     * @param string $prefix - column prefix
     * @param string $suffix - column suffix
     * @param bool $brackets - add brackets when multiple where
     * @throws Exception
     */
    public function __toArray(
        ?string $prefix = null,
        ?string $suffix = null,
        bool $brackets = true
    ): array {
        $condition = [];
        $addBrackets = $brackets && count($this->column) > 1;

        if ($addBrackets) {
            $condition[] = '(';
        }

        $i = 0;
        foreach ($this->column as $column) {
            if (self::isOperator($column)) {
                $operator = strtoupper($column);
                $condition[] = " $operator ";
            } else {
                $i = count($this->condition) > 1 ? $i : 0;
                $condition[] = "{$prefix}$column{$suffix} {$this->condition[$i]}";

                $i++;
            }
        }

        if ($addBrackets) {
            $condition[] = ')';
        }

        return $condition
            ? array_values(array_merge([implode('', $condition)], $this->getValueForColumn()))
            : $this->condition;
    }
}
