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

use Grido\Components\Component;
use Latte\Runtime\Filters;
use Grido\Components\Filters\Check;
use Grido\Components\Filters\Custom;
use Grido\Components\Filters\Date;
use Grido\Components\Filters\DateRange;
use Grido\Components\Filters\Number;
use Grido\Components\Filters\Select;
use Grido\Components\Filters\Text;
use Grido\Helpers;
use Grido\Exception;
use Grido\Grid;
use Nette\Forms\Control;
use Nette\Utils\Html;

/**
 * Column grid.
 *
 * @package     Grido
 * @subpackage  Components\Columns
 * @author      Petr Bugyík
 *
 * @property-read string $sort
 * @property-read Html $cellPrototype
 * @property-read Html $headerPrototype
 * @property-write ?callable $cellCallback
 * @property-write string $defaultSorting
 * @property mixed $customRender
 * @property-write array $customRenderVariables
 * @property-write ?callable $customRenderExport
 * @property-write array $replacements
 * @property-write bool $sortable
 * @property string $column
 */
abstract class Column extends Component
{
    final public const ID = 'columns';

    final public const VALUE_IDENTIFIER = '%value';

    final public const ORDER_ASC = 'asc';
    final public const ORDER_DESC = 'desc';

    protected ?string $sort = null;

    protected ?string $column = null;

    // <td> html tag
    protected ?Html $cellPrototype = null;

    /** @var ?callable returns td html element; function($row, Html $td) */
    protected $cellCallback;

    // <th> html tag
    protected ?Html $headerPrototype = null;

    protected mixed $customRender = null;

    protected array $customRenderVariables = [];

    protected /*?callable*/ $customRenderExport;

    protected bool $sortable = false;

    // of arrays('pattern' => 'replacement')
    protected array $replacements = [];

    protected bool $translateReplacements = true;


    public function __construct(Grid $grid, string $name, string $label)
    {
        $this->addComponentToGrid($grid, Helpers::formatColumnName($name));

        $this->type = static::class;
        $this->label = $label;
    }


    public function setSortable(bool $sortable = true): static
    {
        $this->sortable = (bool) $sortable;
        return $this;
    }


    /**
     * @param array $replacement array('pattern' => 'replacement')
     */
    public function setReplacement(array $replacement, bool $translate = true): static
    {
        $this->replacements = $this->replacements + $replacement;
        $this->translateReplacements = $translate;
        return $this;
    }


    public function setColumn(mixed $column): static
    {
        $this->column = $column;
        return $this;
    }


    public function setDefaultSort(string $dir): static
    {
        $this->grid->setDefaultSort([$this->getName() => $dir]);
        return $this;
    }


    /**
     * @param callable|string $callback callback or string for name of template filename
     */
    public function setCustomRender(callable|string $callback, array $variables = []): static
    {
        $this->customRender = $callback;
        $this->customRenderVariables = $variables;

        return $this;
    }


    public function setCustomRenderExport(callable $callback): static
    {
        $this->customRenderExport = $callback;
        return $this;
    }


    public function setCellCallback(callable $callback): static
    {
        $this->cellCallback = $callback;
        return $this;
    }


    /**********************************************************************************************/


    public function getCellPrototype(mixed $row = null): Html
    {
        $td = $this->cellPrototype;

        if ($td === null) { //cache
            $td = $this->cellPrototype = Html::el('td')
                ->setClass(['grid-cell-' . $this->getName()]);
        }

        if ($this->cellCallback && $row !== null) {
            $td = clone $td;
            $td = call_user_func_array($this->cellCallback, [$row, $td]);
        }

        return $td;
    }


    public function getHeaderPrototype(): Html
    {
        if ($this->headerPrototype === null) {
            $this->headerPrototype = Html::el('th')
                ->setClass(['column', 'grid-header-' . $this->getName()]);
        }

        if ($this->isSortable() && $this->getSort()) {
            $this->headerPrototype->class[] = $this->getSort() == self::ORDER_DESC
                ? 'desc'
                : 'asc';
        }

        return $this->headerPrototype;
    }


    /**
     * @internal
     */
    public function getColumn(): ?string
    {
        return $this->column ?: $this->getName();
    }


    /**
     * @internal
     */
    public function getSort(): ?string
    {
        if ($this->sort === null) {
            $name = $this->getName();

            $sort = $this->grid->sort[$name] ?? null;

            $this->sort = $sort ?? null;
        }

        return $this->sort;
    }


    /**
     * @internal
     */
    public function getCustomRender(): mixed
    {
        return $this->customRender;
    }


    /**
     * @internal
     */
    public function getCustomRenderVariables(): array
    {
        return $this->customRenderVariables;
    }


    /**
     * @internal
     */
    public function getLabel(): string
    {
        return is_string($this->label)
            ? $this->translate($this->label)
            : $this->label;
    }


    /**********************************************************************************************/


    /**
     * @internal
     */
    public function isSortable(): bool
    {
        return $this->sortable;
    }


    /**
     * @internal
     */
    public function hasFilter(): bool
    {
        return (bool) $this->grid->getFilter($this->getName(), false);
    }


    /**********************************************************************************************/


    /**
     * @internal
     */
    public function render(mixed $row): mixed
    {
        if (is_callable($this->customRender)) {
            return call_user_func_array($this->customRender, [$row, $this->customRenderVariables]);
        }

        $value = $this->getValue($row);
        return $this->formatValue($value);
    }


    /**
     * @internal
     */
    public function renderExport(mixed $row): mixed
    {
        if (is_callable($this->customRenderExport)) {
            return call_user_func_array($this->customRenderExport, [$row]);
        }

        $value = $this->getValue($row);
        return strip_tags((string) $this->applyReplacement($value));
    }


    /**
     * @throws Exception
     */
    protected function getValue(mixed $row): mixed
    {
        $column = $this->getColumn();
        if (is_string($column)) {
            return $this->grid->getProperty($row, Helpers::unformatColumnName($column));
        } elseif (is_callable($column)) {
            return call_user_func_array($column, [$row]);
        } else {
            throw new Exception('Column must be string or callback.');
        }
    }


    protected function applyReplacement(mixed $value): mixed
    {
        if ((is_scalar($value) || $value === null) && isset($this->replacements[(string) $value])) {
            $replaced = $this->replacements[(string) $value];
            if (is_scalar($replaced) && $this->translateReplacements) {
                $replaced = $this->translate($replaced);
            }

            $value = is_string($value)
                ? str_replace(static::VALUE_IDENTIFIER, $value, $replaced)
                : $replaced;
        }

        return $value;
    }


    protected function formatValue(mixed $value): mixed
    {
        $value = is_string($value)
            ? Filters::escapeHtml($value)
            : $value;

        return $this->applyReplacement($value);
    }


    /******************************* Aliases for filters ******************************************/


    public function setFilterText(): Text
    {
        return $this->grid->addFilterText($this->getName(), $this->label);
    }


    public function setFilterDate(): Date
    {
        return $this->grid->addFilterDate($this->getName(), $this->label);
    }


    public function setFilterDateRange(): DateRange
    {
        return $this->grid->addFilterDateRange($this->getName(), $this->label);
    }


    public function setFilterCheck(): Check
    {
        return $this->grid->addFilterCheck($this->getName(), $this->label);
    }


    public function setFilterSelect(array $items = null, bool $multiple = false): Select
    {
        return $this->grid->addFilterSelect($this->getName(), $this->label, $items, $multiple);
    }


    public function setFilterNumber(): Number
    {
        return $this->grid->addFilterNumber($this->getName(), $this->label);
    }


    public function setFilterCustom(Control $formControl): Custom
    {
        return $this->grid->addFilterCustom($this->getName(), $formControl);
    }
}
