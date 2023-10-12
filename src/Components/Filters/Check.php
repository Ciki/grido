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

use Nette\Forms\Controls\Checkbox;

/**
 * Check box filter.
 *
 * @package     Grido
 * @subpackage  Components\Filters
 * @author      Petr Bugyík
 *
 * @property-read Checkbox|null $control
 * @method Checkbox|null getControl()
 */
class Check extends Filter
{
    /* representation true in URI */
    const true = '✓';

    protected mixed $condition = 'IS NOT null';


    protected function getFormControl(): Checkbox
    {
        $control = new Checkbox($this->label);
        $control->getControlPrototype()->class[] = 'checkbox';
        return $control;
    }


    /**
     * @internal
     */
    public function __getCondition(mixed $value): ?Condition
    {
        $value = $value == self::true
            ? true
            : false;

        return parent::__getCondition($value);
    }


    /**
     * @internal
     */
    public function formatValue(mixed $value): mixed
    {
        return null;
    }


    /**
     * @internal
     */
    public function changeValue(mixed $value): mixed
    {
        return (bool) $value === true
            ? self::true
            : $value;
    }
}
