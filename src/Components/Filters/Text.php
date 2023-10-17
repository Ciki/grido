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

use Nette\Application\Responses\JsonResponse;
use Grido\Exception;
use Nette\Forms\Controls\TextInput;

/**
 * Text input filter.
 *
 * @package     Grido
 * @subpackage  Components\Filters
 * @author      Petr Bugyík
 *
 * @property int $suggestionLimit
 * @property-write callable $suggestionCallback
 * @property-read ?TextInput $control
 * @method ?TextInput getControl()
 */
class Text extends Filter
{
    protected mixed $condition = 'LIKE ?';

    protected ?string $formatValue = '%%value%';

    protected bool $suggestion = false;

    protected mixed $suggestionColumn;

    protected int $suggestionLimit = 10;

    /** @var callable */
    protected $suggestionCallback;


    public function setSuggestion(mixed $column = null): static
    {
        $this->suggestion = true;
        $this->suggestionColumn = $column;

        $prototype = $this->getControl()->getControlPrototype();
        $prototype->attrs['autocomplete'] = 'off';
        $prototype->class[] = 'suggest';

        $this->grid->onRender[] = function () use ($prototype): void {
            $replacement = '-query-';
            $prototype->setAttribute('data-grido-suggest-replacement', $replacement);
            $prototype->setAttribute('data-grido-suggest-limit', $this->suggestionLimit);
            $prototype->setAttribute('data-grido-suggest-handler', $this->link('suggest!', [
                'query' => $replacement
            ]));
        };

        return $this;
    }


    public function setSuggestionLimit(int $limit): static
    {
        $this->suggestionLimit = $limit;
        return $this;
    }


    public function setSuggestionCallback(callable $callback): static
    {
        $this->suggestionCallback = $callback;
        return $this;
    }


    /**********************************************************************************************/


    public function getSuggestionLimit(): int
    {
        return $this->suggestionLimit;
    }


    public function getSuggestionCallback(): callable
    {
        return $this->suggestionCallback;
    }


    public function getSuggestionColumn(): string
    {
        return $this->suggestionColumn;
    }

    /**
     * @param string $query - value from input
     * @internal
     * @throws Exception
     */
    public function handleSuggest(string $query): void
    {
        !empty($this->grid->onRegistered) && $this->grid->onRegistered($this->grid);
        $name = $this->getName();

        if (!$this->getPresenter()->isAjax() || !$this->suggestion || $query == '') {
            $this->getPresenter()->terminate();
        }

        $actualFilter = $this->grid->getActualFilter();
        if (isset($actualFilter[$name])) {
            unset($actualFilter[$name]);
        }

        $conditions = $this->grid->__getConditions($actualFilter);

        if ($this->suggestionCallback === null) {
            $conditions[] = $this->__getCondition($query);

            $column = $this->suggestionColumn ?: current($this->getColumn());
            $items = $this->grid->model->suggest($column, $conditions, $this->suggestionLimit);
        } else {
            $items = call_user_func_array($this->suggestionCallback, [$query, $actualFilter, $conditions, $this]);
            if (!is_array($items)) {
                throw new Exception('Items must be an array.');
            }
        }

        $this->getPresenter()->sendResponse(new JsonResponse($items));
    }


    protected function getFormControl(): TextInput
    {
        $control = new TextInput($this->label);
        $control->getControlPrototype()->class[] = 'text';

        return $control;
    }
}
