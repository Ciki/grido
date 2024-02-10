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

namespace Grido;

use Grido\Components\Actions\Action;
use Grido\Components\Button;
use Grido\Components\Columns\Column;
use Grido\Components\Container;
use Grido\Components\Filters\Filter;
use Grido\Components\Paginator;
use Grido\DataSources\IDataSource;
use Grido\DataSources\Model;
use Grido\Translations\FileTranslator;
use Latte\Essential\RawPhpExtension;
use Latte\Essential\TranslatorExtension;
use Nette\Application\Attributes\Persistent;
use Nette\Application\UI\Form;
use Nette\Application\UI\Presenter;
use Nette\Application\UI\Template;
use Nette\Database\Table\IRow;
use Nette\Database\Table\Selection;
use Nette\Forms\Controls\SubmitButton;
use Nette\Http\SessionSection;
use Nette\InvalidArgumentException;
use Nette\Localization\Translator;
use Nette\Utils\Html;
use Symfony\Component\PropertyAccess\PropertyAccessor;

/**
 * Grido - DataGrid for Nette Framework.
 *
 * @property-read ?int $count
 * @property-read mixed $data
 * @property-read Html $tablePrototype
 * @property-read PropertyAccessor $propertyAccessor
 * @property-read Customization $customization
 * @property-write string $templateFile
 * @property bool $rememberState
 * @property int $defaultPerPage
 * @property array $defaultFilter
 * @property array $defaultSort
 * @property array $perPageList
 * @property ?Translator $translator
 * @property Paginator $paginator
 * @property string $primaryKey
 * @property string $filterRenderType
 * @property IDataSource|Model|null $model
 * @property callable $rowCallback
 * @property bool $strictMode
 * @method void onRegistered(Grid $grid)
 * @method void onRender(Grid $grid)
 * @method void onFetchData(Grid $grid)
 */
/*final*/ class Grid extends Container
{
	/*	 * *** DEFAULTS *** */
	public const BUTTONS = 'buttons';
	public const CLIENT_SIDE_OPTIONS = 'grido-options';

	#[Persistent]
	public int $page = 1;

	#[Persistent]
	public ?int $perPage = null;

	#[Persistent]
	public array $sort = [];

	#[Persistent]
	public array $filter = [];

	// event on all grid's components registered
	public array $onRegistered;

	// event on render
	public array $onRender;

	// event for modifying data
	public array $onFetchData;

	/** @var callable($row, Html $tr): Html */
	protected /*callable*/ $rowCallback;

	protected ?Html $tablePrototype = null;

	protected bool $rememberState = false;

	protected ?string $rememberStateSectionName = null;

	protected string $primaryKey = 'id';

	protected ?string $filterRenderType = null;

	protected array $perPageList = [10, 20, 30, 50, 100];

	protected int $defaultPerPage = 20;

	protected array $defaultFilter = [];

	protected array $defaultSort = [];

	protected IDataSource|Model|null $model = null;

	// total count of items
	protected ?int $count = null;

	protected mixed $data = null;

	protected ?Paginator $paginator = null;

	protected ?Translator $translator = null;

	protected ?PropertyAccessor $propertyAccessor = null;

	protected bool $strictMode = true;

	protected array $options = [
		self::CLIENT_SIDE_OPTIONS => [],
	];

	protected ?Customization $customization = null;


	public function __construct()
	{
		[$parent, $name] = func_get_args() + [null, null];
		if ($parent !== null) {
			$parent->addComponent($this, $name);
		} elseif (is_string($name)) {
			$this->name = $name;
		}
	}


	/**
	 * Sets a model that implements the interface Grido\DataSources\IDataSource or data-source object.
	 * @throws Exception
	 */
	public function setModel(mixed $model, bool $forceWrapper = false): static
	{
		$this->model = ($model instanceof IDataSource && $forceWrapper === false) ? $model : new Model($model);

		return $this;
	}


	public function setDefaultPerPage(int $perPage): static
	{
		$this->defaultPerPage = $perPage;

		if (!in_array($perPage, $this->perPageList, true)) {
			$this->perPageList[] = $perPage;
			sort($this->perPageList);
		}

		return $this;
	}


	public function setDefaultFilter(array $filter): static
	{
		$this->defaultFilter = array_merge($this->defaultFilter, $filter);
		return $this;
	}


	/**
	 * @throws Exception
	 */
	public function setDefaultSort(array $sort): static
	{
		static $replace = [
			'asc' => Column::ORDER_ASC,
			'desc' => Column::ORDER_DESC,
		];

		foreach ($sort as $column => $dir) {
			$dir = strtr(strtolower($dir), $replace);
			if (!in_array($dir, $replace, true)) {
				throw new Exception("Dir '{$dir}' for column '{$column}' is not allowed.");
			}

			$this->defaultSort[$column] = $dir;
		}

		return $this;
	}


	public function setPerPageList(array $perPageList): static
	{
		$this->perPageList = $perPageList;

		if ($this->hasFilters(false) || $this->hasOperation(false)) {
			$this['form']['count']->setItems($this->getItemsForCountSelect());
		}

		return $this;
	}


	public function setTranslator(Translator $translator): static
	{
		$this->translator = $translator;
		return $this;
	}


	/**
	 * Sets type of filter rendering.
	 * Defaults inner (Filter::RENDER_INNER) if column does not exist then outer filter (Filter::RENDER_OUTER).
	 * @throws Exception
	 */
	public function setFilterRenderType(string $type): static
	{
		$type = strtolower($type);
		if (!in_array($type, [Filter::RENDER_INNER, Filter::RENDER_OUTER], true)) {
			throw new Exception('Type must be Filter::RENDER_INNER or Filter::RENDER_OUTER.');
		}

		$this->filterRenderType = $type;
		return $this;
	}


	public function setPaginator(Paginator $paginator): static
	{
		$this->paginator = $paginator;
		return $this;
	}


	public function setPrimaryKey(string $key): static
	{
		$this->primaryKey = $key;
		return $this;
	}


	public function setTemplateFile(string $file): static
	{
		$this->onRender[] = function () use ($file): void {
			$this->getTemplate()->add('gridoTemplate', $this->getTemplate()->getFile());
			$this->getTemplate()->setFile($file);
		};

		return $this;
	}


	/**
	 * Sets saving state to session.
	 */
	public function setRememberState(bool $state = true, ?string $sectionName = null): static
	{
		$this->rememberState = $state;
		$this->rememberStateSectionName = $sectionName;

		return $this;
	}


	/**
	 * Sets callback for customizing tr html object.
	 * Callback returns tr html element; function($row, Html $tr).
	 */
	public function setRowCallback(callable $callback): static
	{
		$this->rowCallback = $callback;
		return $this;
	}


	/**
	 * Sets client-side options.
	 */
	public function setClientSideOptions(array $options): static
	{
		$this->options[self::CLIENT_SIDE_OPTIONS] = $options;
		return $this;
	}


	/**
	 * Determines whether any user error will cause a notice.
	 */
	public function setStrictMode(bool $mode): static
	{
		$this->strictMode = (bool) $mode;
		return $this;
	}


	public function setCustomization(Customization $customization): void
	{
		$this->customization = $customization;
	}


	/*	 * ******************************************************************************************* */

	public function getCount(): int
	{
		if ($this->count === null) {
			$this->count = $this->getModel()->getCount();
		}

		return $this->count;
	}


	public function getDefaultPerPage(): int
	{
		if (!in_array($this->defaultPerPage, $this->perPageList, true)) {
			$this->defaultPerPage = $this->perPageList[0];
		}

		return $this->defaultPerPage;
	}


	public function getDefaultFilter(): array
	{
		return $this->defaultFilter;
	}


	public function getDefaultSort(): array
	{
		return $this->defaultSort;
	}


	public function getPerPageList(): array
	{
		return $this->perPageList;
	}


	public function getPrimaryKey(): string
	{
		return $this->primaryKey;
	}


	public function getRememberState(): bool
	{
		return $this->rememberState;
	}


	public function getRowCallback(): callable
	{
		return $this->rowCallback;
	}


	public function getPerPage(): int
	{
		return $this->perPage ?? $this->getDefaultPerPage();
	}


	/**
	 * Returns actual filter values.
	 */
	public function getActualFilter(string $key = null): mixed
	{
		$filter = $this->filter ?: $this->defaultFilter;
		return $key !== null && isset($filter[$key]) ? $filter[$key] : $filter;
	}


	/**
	 * Returns fetched data.
	 * @throws Exception
	 */
	public function getData(bool $applyPaging = true, bool $useCache = true, bool $fetch = true): array|IDataSource|Selection
	{
		if ($this->getModel() === null) {
			throw new Exception('Model cannot be empty, please use method $grid->setModel().');
		}

		$data = $this->data;
		if ($data === null || $useCache === false) {
			$this->applyFiltering();
			$this->applySorting();

			if ($applyPaging) {
				$this->applyPaging();
			}

			if ($fetch === false) {
				return $this->getModel();
			}

			$data = $this->getModel()->getData();

			if ($useCache === true) {
				$this->data = $data;
			}

			if ($applyPaging && !empty($data) && !in_array($this->page, range(1, $this->getPaginator()->pageCount), true)) {
				$this->__triggerUserNotice('Page is out of range.');
				$this->page = 1;
			}

			if (!empty($this->onFetchData)) {
				$this->onFetchData($this);
			}
		}

		return $data;
	}


	public function getTranslator(): Translator
	{
		if ($this->translator === null) {
			$this->setTranslator(new FileTranslator());
		}

		return $this->translator;
	}


	/**
	 * Returns remember session for set expiration, etc.
	 */
	public function getRememberSession(bool $forceStartSession = false): ?SessionSection
	{
		$presenter = $this->getPresenter();
		$session = $presenter->getSession();

		if (!$session->isStarted() && $forceStartSession) {
			$session->start();
		}

		return $session->isStarted() ?
			$session->getSection(
				$this->rememberStateSectionName ?: ($presenter->name . ':' . $this->getUniqueId())
			)
			: null;
	}


	/**
	 * Returns table html element of grid.
	 */
	public function getTablePrototype(): Html
	{
		if ($this->tablePrototype === null) {
			$this->tablePrototype = Html::el('table');
			$this->tablePrototype->id($this->getName());
		}

		return $this->tablePrototype;
	}


	/**
	 * @internal
	 */
	public function getFilterRenderType(): string
	{
		if ($this->filterRenderType !== null) {
			return $this->filterRenderType;
		}

		$this->filterRenderType = Filter::RENDER_OUTER;
		if ($this->hasColumns() && $this->hasFilters() && $this->hasActions()) {
			$this->filterRenderType = Filter::RENDER_INNER;

			$filters = $this[Filter::ID]->getComponents();
			foreach ($filters as $filter) {
				if (!$this[Column::ID]->getComponent($filter->name, false)) {
					$this->filterRenderType = Filter::RENDER_OUTER;
					break;
				}
			}
		}

		return $this->filterRenderType;
	}


	public function getModel(): IDataSource|Model|null
	{
		return $this->model;
	}


	/**
	 * @internal
	 */
	public function getPaginator(): Paginator
	{
		if ($this->paginator === null) {
			$this->paginator = new Paginator();
			$this->paginator->setItemsPerPage($this->getPerPage())
				->setGrid($this);
		}

		return $this->paginator;
	}


	/**
	 * A simple wrapper around symfony/property-access with Nette Database dot notation support.
	 * @internal
	 */
	public function getProperty(array|object $object, string $name): mixed
	{
		if ($object instanceof IRow && \str_contains($name, '.')) {
			$parts = explode('.', $name);
			foreach ($parts as $item) {
				if (is_object($object)) {
					$object = $object->{$item};
				}
			}

			return $object;
		}

		if (is_array($object)) {
			$name = "[{$name}]";
		}

		return $this->getPropertyAccessor()->getValue($object, $name);
	}


	/**
	 * @internal
	 */
	public function getPropertyAccessor(): PropertyAccessor
	{
		if ($this->propertyAccessor === null) {
			$this->propertyAccessor = new PropertyAccessor(
				PropertyAccessor::MAGIC_CALL | PropertyAccessor::MAGIC_GET | PropertyAccessor::MAGIC_SET,
				PropertyAccessor::THROW_ON_INVALID_INDEX | PropertyAccessor::THROW_ON_INVALID_PROPERTY_PATH
			);
		}

		return $this->propertyAccessor;
	}


	/**
	 * @param mixed $row item from db
	 * @internal
	 */
	public function getRowPrototype(mixed $row): Html
	{
		try {
			$primaryValue = $this->getProperty($row, $this->getPrimaryKey());
		} catch (\Exception) {
			$primaryValue = null;
		}

		$tr = Html::el('tr');
		$primaryValue ? $tr->class[] = "grid-row-{$primaryValue}" : null;

		if ($this->rowCallback) {
			$tr = call_user_func_array($this->rowCallback, [$row, $tr]);
		}

		return $tr;
	}


	public function getClientSideOptions(): array
	{
		return (array) $this->options[self::CLIENT_SIDE_OPTIONS];
	}


	public function isStrictMode(): bool
	{
		return $this->strictMode;
	}


	public function getCustomization(): Customization
	{
		if ($this->customization === null) {
			$this->customization = new Customization($this);
		}

		return $this->customization;
	}


	/*	 * ******************************************************************************************* */


	/**
	 * Loads state informations.
	 * @internal
	 */
	public function loadState(array $params): void
	{
		//loads state from session
		$session = $this->getRememberSession();
		if ($session && $this->getPresenter()->isSignalReceiver($this)) {
			$session->remove();
		} elseif ($session && empty($params) && $session->params) {
			$params = (array) $session->params;
		}

		parent::loadState($params);
	}


	/**
	 * Saves state informations for next request.
	 * @internal
	 */
	public function saveState(array &$params): void
	{
		!empty($this->onRegistered) && $this->onRegistered($this);
		parent::saveState($params);
	}


	/**
	 * Ajax method.
	 * @internal
	 */
	public function handleRefresh(): void
	{
		$this->reload();
	}


	/**
	 * @internal
	 */
	public function handlePage(int $page): void
	{
		$this->reload();
	}


	/**
	 * @internal
	 */
	public function handleSort(array $sort): void
	{
		$this->page = 1;
		$this->reload();
	}


	/**
	 * @internal
	 */
	public function handleFilter(SubmitButton $button): void
	{
		$values = $button->form->values[Filter::ID];
		// $session = $this->rememberState // session filter
		// ?
		// ($this->getRememberSession(true)->params['filter'] ?? [])
		// : [];

		foreach ($values as $name => $value) {
			if (
				is_numeric($value) // valid empty value like `0`
				|| !empty($value)
				|| isset($this->defaultFilter[$name])
				// || isset($session[$name])
			) {
				$this->filter[$name] = $this->getFilter($name)->changeValue($value);
			} elseif (array_key_exists($name, $this->filter)) {
				unset($this->filter[$name]);
			}
		}

		$this->page = 1;
		$this->reload();
	}


	/**
	 * @internal
	 */
	public function handleReset(SubmitButton $button): void
	{
		$this->sort = [];
		$this->filter = [];
		$this->perPage = null;

		if ($session = $this->getRememberSession()) {
			$session->remove();
		}

		$button->form->setValues([
			Filter::ID => $this->defaultFilter,
		], true);

		$this->page = 1;
		$this->reload();
	}


	/**
	 * @internal
	 */
	public function handlePerPage(SubmitButton $button): void
	{
		$perPage = (int) $button->form['count']->value;
		$this->perPage = $perPage === $this->defaultPerPage ? null : $perPage;

		$this->page = 1;
		$this->reload();
	}


	/**
	 * Refresh wrapper.
	 * @internal
	 */
	public function reload(): void
	{
		if ($this->presenter->isAjax()) {
			$this->presenter->payload->grido = true;
			$this->redrawControl();
		} else {
			$this->redirect('this');
		}
	}


	/*	 * ******************************************************************************************* */


	/**
	 * @internal
	 */
	public function createTemplate(?string $class = null): Template
	{
		$template = parent::createTemplate($class);
		$template->setFile($this->getCustomization()->getTemplateFiles()[Customization::TEMPLATE_DEFAULT]);
		$latte = $template->getLatte();
		// latte/latte ^3.0
		if (class_exists('\\' . TranslatorExtension::class)) {
			$latte->addExtension(new TranslatorExtension($this->getTranslator()));
			$latte->addExtension(new RawPhpExtension());
		} else {
			$latte->addFilter('translate', [$this->getTranslator(), 'translate']);
		}

		return $template;
	}


	/**
	 * @internal
	 * @throws Exception
	 */
	public function render(): void
	{
		if (!$this->hasColumns()) {
			throw new Exception('Grid must have defined a column, please use method $grid->addColumn*().');
		}

		$this->saveRememberState();
		$data = $this->getData();

		if (!empty($this->onRender)) {
			$this->onRender($this);
		}

		$form = $this['form'];

		// avoid multiple template params setting if grid used on multiple places or called multiple times in snippets
		if (!isset($this->getTemplate()->data)) {
			$this->getTemplate()->add('data', $data);
			$this->getTemplate()->add('form', $form);
			$this->getTemplate()->add('paginator', $this->getPaginator());
			$this->getTemplate()->add('customization', $this->getCustomization());
			$this->getTemplate()->add('columns', $this->getComponent(Column::ID)->getComponents());
			$this->getTemplate()->add(
				'actions',
				$this->hasActions() ? $this->getComponent(Action::ID)->getComponents() : []
			);

			$this->getTemplate()->add(
				'buttons',
				$this->hasButtons() ? $this->getComponent(Button::ID)->getComponents() : []
			);

			$this->getTemplate()->add(
				'formFilters',
				$this->hasFilters() ? $form->getComponent(Filter::ID)->getComponents() : []
			);

			$form['count']->setValue($this->getPerPage());

			if ($options = $this->options[self::CLIENT_SIDE_OPTIONS]) {
				$this->getTablePrototype()->setAttribute('data-' . self::CLIENT_SIDE_OPTIONS, json_encode($options, JSON_THROW_ON_ERROR));
			}
		}
		$this->getTemplate()->render();
	}


	protected function saveRememberState(): void
	{
		if ($this->rememberState) {
			$session = $this->getRememberSession(true);
			$params = array_keys(static::getReflection()->getPersistentParams());
			foreach ($params as $param) {
				$session->params[$param] = $this->{$param};
			}
		}
	}


	protected function applyFiltering(): void
	{
		$conditions = $this->__getConditions($this->getActualFilter());
		$this->getModel()->filter($conditions);
	}


	/**
	 * @internal
	 */
	public function __getConditions(array $filter): array
	{
		$conditions = [];
		if (!empty($filter)) {
			try {
				$this['form']->setDefaults([
					Filter::ID => $filter,
				]);
			} catch (InvalidArgumentException $e) {
				$this->__triggerUserNotice($e->getMessage());
				$filter = [];
				if ($session = $this->getRememberSession()) {
					$session->remove();
				}
			}

			foreach ($filter as $column => $value) {
				if ($component = $this->getFilter($column, false)) {
					if ($condition = $component->__getCondition($value)) {
						$conditions[] = $condition;
					}
				} else {
					$this->__triggerUserNotice("Filter with name '{$column}' does not exist.");
				}
			}
		}

		return $conditions;
	}


	protected function applySorting(): void
	{
		$sort = [];
		$this->sort = $this->sort ?: $this->defaultSort;

		foreach ($this->sort as $column => $dir) {
			$component = $this->getColumn($column, false);
			if (!$component) {
				if (!isset($this->defaultSort[$column])) {
					$this->__triggerUserNotice("Column with name '{$column}' does not exist.");
					break;
				}
			} elseif (!$component->isSortable()) {
				if (isset($this->defaultSort[$column])) {
					$component->setSortable();
				} else {
					$this->__triggerUserNotice("Column with name '{$column}' is not sortable.");
					break;
				}
			}

			if (!in_array($dir, [Column::ORDER_ASC, Column::ORDER_DESC], true)) {
				if ($dir === '' && isset($this->defaultSort[$column])) {
					unset($this->sort[$column]);
					break;
				}

				$this->__triggerUserNotice("Dir '{$dir}' is not allowed.");
				break;
			}

			$sort[$component ? $component->column : $column] = $dir === Column::ORDER_ASC ? 'ASC' : 'DESC';
		}

		if (!empty($sort)) {
			$this->getModel()->sort($sort);
		}
	}


	protected function applyPaging(): void
	{
		$paginator = $this->getPaginator()
			->setItemCount($this->getCount())
			->setPage($this->page);

		$perPage = $this->getPerPage();
		if ($perPage !== null && !in_array($perPage, $this->perPageList, true)) {
			$this->__triggerUserNotice("The number '{$perPage}' of items per page is out of range.");
		}

		$this->getModel()->limit($paginator->getOffset(), $paginator->getLength());
	}


	protected function createComponentForm(string $name): Form
	{
		$form = new Form($this, $name);
		$form->setTranslator($this->getTranslator());
		$form->setMethod($form::GET);

		$buttons = $form->addContainer(self::BUTTONS);
		$buttons->addSubmit('search', 'Grido.Search')
			->onClick[] = $this->handleFilter(...);
		$buttons->addSubmit('reset', 'Grido.Reset')
			->onClick[] = $this->handleReset(...);
		$buttons->addSubmit('perPage', 'Grido.ItemsPerPage')
			->onClick[] = $this->handlePerPage(...);

		$form->addSelect('count', 'Count', $this->getItemsForCountSelect())
			->setTranslator(null)
			->controlPrototype->attrs['title'] = $this->getTranslator()->translate('Grido.ItemsPerPage');

		return $form;
	}


	protected function getItemsForCountSelect(): array
	{
		return array_combine($this->perPageList, $this->perPageList);
	}


	/**
	 * @internal
	 */
	public function __triggerUserNotice(string $message): void
	{
		if ($this->lookup(Presenter::class, false) && $session = $this->getRememberSession()) {
			$session->remove();
		}

		$this->strictMode && trigger_error($message, E_USER_NOTICE);
	}
}
