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

use Exception;
use Nette\Forms\Controls\TextInput;

/**
 * Number input filter.
 *
 * @package     Grido
 * @subpackage  Components\Filters
 * @author      Petr Bugyík
 */
final class Number extends Text
{
    protected mixed $condition = null;


    protected function getFormControl(): TextInput
    {
        $control = parent::getFormControl();
        $hint = 'Grido.HintNumber';
        $control->getControlPrototype()->title = sprintf($this->translate($hint), random_int(1, 9));
        $control->getControlPrototype()->class[] = 'number';

        return $control;
    }


    /**
     * @throws Exception
     * @internal
     */
    public function __getCondition(mixed $value): ?Condition
    {
        $condition = parent::__getCondition($value);

        if ($condition === null) {
            $condition = Condition::setupEmpty();

            if (preg_match('/(<>|[<|>]=?)?([-0-9,|.]+)/', $value, $matches)) {
                $value = str_replace(',', '.', $matches[2]);
                $operator = $matches[1] ?: '=';

                $condition = Condition::setup($this->getColumn(), $operator . ' ?', $value);
            }
        }

        return $condition;
    }
}
