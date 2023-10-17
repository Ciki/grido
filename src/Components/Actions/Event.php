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

namespace Grido\Components\Actions;

use Grido\Grid;
use Grido\Exception;
use Nette\Utils\Html;

/**
 * Event action.
 *
 * @package     Grido
 * @subpackage  Components\Actions
 * @author      Josef Kříž <pepakriz@gmail.com>
 * @author      Petr Bugyík
 *
 * @property callable $onClick function($id, Event $event)
 */
final class Event extends Action
{
    /** @var callable function($id, Event $event) */
    private $onClick;


    /**
     * @throws Exception
     */
    public function __construct(Grid $grid, string $name, string $label, ?callable $onClick = null)
    {
        parent::__construct($grid, $name, $label);

        if ($onClick === null) {
            $grid->onRender[] = function (Grid $grid): void {
                if ($this->onClick === null) {
                    throw new Exception("Callback onClick in action '{$this->name}' must be set.");
                }
            };
        } else {
            $this->setOnClick($onClick);
        }
    }


    /**
     * @param callable $onClick function($id, Event $event)
     */
    public function setOnClick(callable $onClick): static
    {
        $this->onClick = $onClick;
        return $this;
    }


    public function getOnClick(): callable
    {
        return $this->onClick;
    }


    /**********************************************************************************************/


    /**
     * @internal
     */
    public function getElement(mixed $row): Html
    {
        $element = parent::getElement($row);

        $primaryValue = $this->grid->getProperty($row, $this->getPrimaryKey());
        $element->href($this->link('click!', $primaryValue));

        return $element;
    }


    /**********************************************************************************************/


    /**
     * @internal
     */
    public function handleClick(int $id): void
    {
        call_user_func_array($this->onClick, [$id, $this]);
    }
}
