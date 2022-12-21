<?php

/**
 * This file is part of the Grido (http://grido.bugyik.cz)
 *
 * Copyright (c) 2011 Petr Bugyík (http://petr.bugyik.cz)
 *
 * For the full copyright and license information, please view
 * the file LICENSE.md that was distributed with this source code.
 */

namespace Grido;

use Grido\Exception;
use Grido\Components\Button;
use Grido\Components\Paginator;
use Grido\Components\Columns\Column;
use Grido\Components\Filters\Filter;
use Grido\Components\Actions\Action;
use Nette\Application\UI\Presenter;
use Symfony\Component\PropertyAccess\PropertyAccessor;

/**
 * Grido - DataGrid for Nette Framework.
 *
 * @package     Grido
 * @author      Petr Bugyík
 *
 * @property-read int $count
 * @property-read mixed $data
 * @property-read \Nette\Utils\Html $tablePrototype
 * @property-read PropertyAccessor $propertyAccessor
 * @property-read Customization $customization
 * @property-write string $templateFile
 * @property bool $rememberState
 * @property array $defaultPerPage
 * @property array $defaultFilter
 * @property array $defaultSort
 * @property array $perPageList
 * @property \Nette\Localization\ITranslator $translator
 * @property Paginator $paginator
 * @property string $primaryKey
 * @property string $filterRenderType
 * @property DataSources\IDataSource $model
 * @property callback $rowCallback
 * @property bool $strictMode
 * @method void onRegistered(Grid $grid)
 * @method void onRender(Grid $grid)
 * @method void onFetchData(Grid $grid)
 */
class Grid extends Components\Container
{
	/*	 * *** DEFAULTS *** */
	const BUTTONS = 'buttons';
	const CLIENT_SIDE_OPTIONS = 'grido-options';


	/** @var int @persistent */
	public $page = 1;

	/** @var int @persistent */
	public $perPage;

	/** @var array @persistent */
	public $sort = [];

	/** @var array @persistent */
	public $filter = [];

	/** @var array event on all grid's components registered */
	public $onRegistered;

	/** @var array event on render */
	public $onRender;

	/** @var array event for modifying data */
	public $onFetchData;

	/** @var callback returns tr html element; function($row, Html $tr) */
	protected $rowCallback;

	/** @var \Nette\Utils\Html */
	protected $tablePrototype;

	/** @var bool */
	protected $rememberState = false;

	/** @var string */
	protected $rememberStateSectionName;

	/** @var string */
	protected $primaryKey = 'id';

	/** @var string */
	protected $filterRenderType;

	/** @var array */
	protected $perPageList = [10, 20, 30, 50, 100];

	/** @var int */
	protected $defaultPerPage = 20;

	/** @var array */
	protected $defaultFilter = [];

	/** @var array */
	protected $defaultSort = [];

	/** @var DataSources\IDataSource */
	protected $model;

	/** @var int total count of items */
	protected $count;

	/** @var mixed */
	protected $data;

	/** @var Paginator */
	protected $paginator;

	/** @var \Nette\Localization\ITranslator */
	protected $translator;

	/** @var PropertyAccessor */
	protected $propertyAccessor;

	/** @var bool */
	protected $strictMode = true;

	/** @var array */
	protected $options = [
		self::CLIENT_SIDE_OPTIONS => []
	];

	/** @var Customization */
	protected $customization;


	/**
	 * Grid constructor.
	 */
	public function __construct()
	{
		list($parent, $name) = func_get_args() + [null, null];
		if ($parent !== null) {
			$parent->addComponent($this, $name);
		} elseif (is_string($name)) {
			$this->name = $name;
		}
	}


	/**
	 * Sets a model that implements the interface Grido\DataSources\IDataSource or data-source object.
	 * @param mixed $model
	 * @param bool $forceWrapper
	 * @throws Exception
	 * @return Grid
	 */
	public function setModel($model, bool $forceWrapper = false): Grid
	{
		$this->model = $model instanceof DataSources\IDataSource && $forceWrapper === false ? $model : new DataSources\Model($model);

		return $this;
	}


	public function setDefaultPerPage(int $perPage): Grid
	{
		$perPage = (int) $perPage;
		$this->defaultPerPage = $perPage;

		if (!in_array($perPage, $this->perPageList)) {
			$this->perPageList[] = $perPage;
			sort($this->perPageList);
		}

		return $this;
	}


	public function setDefaultFilter(array $filter): Grid
	{
		$this->defaultFilter = array_merge($this->defaultFilter, $filter);
		return $this;
	}


	/**
	 * Sets default sorting.
	 * @param array $sort
	 * @return Grid
	 * @throws Exception
	 */
	public function setDefaultSort(array $sort): Grid
	{
		static $replace = ['asc' => Column::ORDER_ASC, 'desc' => Column::ORDER_DESC];

		foreach ($sort as $column => $dir) {
			$dir = strtr(strtolower($dir), $replace);
			if (!in_array($dir, $replace)) {
				throw new Exception("Dir '$dir' for column '$column' is not allowed.");
			}

			$this->defaultSort[$column] = $dir;
		}

		return $this;
	}


	public function setPerPageList(array $perPageList): Grid
	{
		$this->perPageList = $perPageList;

		if ($this->hasFilters(false) || $this->hasOperation(false)) {
			$this['form']['count']->setItems($this->getItemsForCountSelect());
		}

		return $this;
	}


	public function setTranslator(\Nette\Localization\ITranslator $translator): Grid
	{
		$this->translator = $translator;
		return $this;
	}


	/**
	 * Sets type of filter rendering.
	 * Defaults inner (Filter::RENDER_INNER) if column does not exist then outer filter (Filter::RENDER_OUTER).
	 * @param string $type
	 * @throws Exception
	 * @return Grid
	 */
	public function setFilterRenderType(string $type): Grid
	{
		$type = strtolower($type);
		if (!in_array($type, [Filter::RENDER_INNER, Filter::RENDER_OUTER])) {
			throw new Exception('Type must be Filter::RENDER_INNER or Filter::RENDER_OUTER.');
		}

		$this->filterRenderType = $type;
		return $this;
	}


	public function setPaginator(Paginator $paginator): Grid
	{
		$this->paginator = $paginator;
		return $this;
	}


	/**
	 * Sets grid primary key.
	 * Defaults is "id".
	 * @param string $key
	 * @return Grid
	 */
	public function setPrimaryKey(string $key): Grid
	{
		$this->primaryKey = $key;
		return $this;
	}


	/**
	 * Sets file name of custom template.
	 * @param string $file
	 * @return Grid
	 */
	public function setTemplateFile(string $file): Grid
	{
		$this->onRender[] = function () use ($file) {
			$this->getTemplate()->add('gridoTemplate', $this->getTemplate()->getFile());
			$this->getTemplate()->setFile($file);
		};

		return $this;
	}


	/**
	 * Sets saving state to session.
	 */
	public function setRememberState(bool $state = true, ?string $sectionName = null): Grid
	{
		$this->rememberState = $state;
		$this->rememberStateSectionName = $sectionName;

		return $this;
	}


	/**
	 * Sets callback for customizing tr html object.
	 * Callback returns tr html element; function($row, Html $tr).
	 * @param $callback
	 * @return Grid
	 */
	public function setRowCallback(callable $callback): Grid
	{
		$this->rowCallback = $callback;
		return $this;
	}


	/**
	 * Sets client-side options.
	 * @param array $options
	 * @return Grid
	 */
	public function setClientSideOptions(array $options): Grid
	{
		$this->options[self::CLIENT_SIDE_OPTIONS] = $options;
		return $this;
	}


	/**
	 * Determines whether any user error will cause a notice.
	 * @param bool $mode
	 * @return Grid
	 */
	public function setStrictMode(bool $mode): Grid
	{
		$this->strictMode = (bool) $mode;
		return $this;
	}


	public function setCustomization(Customization $customization)
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
		if (!in_array($this->defaultPerPage, $this->perPageList)) {
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
		return $this->perPage === null ? $this->getDefaultPerPage() : $this->perPage;
	}


	/**
	 * Returns actual filter values.
	 * @param string $key
	 * @return mixed
	 */
	public function getActualFilter(string $key = null)
	{
		$filter = $this->filter ? $this->filter : $this->defaultFilter;
		return $key !== null && isset($filter[$key]) ? $filter[$key] : $filter;
	}


	/**
	 * Returns fetched data.
	 * @param bool $applyPaging
	 * @param bool $useCache
	 * @param bool $fetch
	 * @throws Exception
	 * @return array|DataSources\IDataSource|\Nette\Database\Table\Selection
	 */
	public function getData(bool $applyPaging = true, bool $useCache = true, bool $fetch = true)
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

			if ($applyPaging && !empty($data) && !in_array($this->page, range(1, $this->getPaginator()->pageCount))) {
				$this->__triggerUserNotice("Page is out of range.");
				$this->page = 1;
			}

			if (!empty($this->onFetchData)) {
				$this->onFetchData($this);
			}
		}

		return $data;
	}


	public function getTranslator(): \Nette\Localization\ITranslator
	{
		if ($this->translator === null) {
			$this->setTranslator(new Translations\FileTranslator);
		}

		return $this->translator;
	}


	/**
	 * Returns remember session for set expiration, etc.
	 * @param bool $forceStart - if true, session will be started if not
	 * @return \Nette\Http\SessionSection|null
	 */
	public function getRememberSession(bool $forceStart = false): ?\Nette\Http\SessionSection
	{
		$presenter = $this->getPresenter();
		$session = $presenter->getSession();

		if (!$session->isStarted() && $forceStart) {
			$session->start();
		}

		return $session->isStarted() ? ($session->getSection($this->rememberStateSectionName ?: ($presenter->name . ':' . $this->getUniqueId()))) : null;
	}


	/**
	 * Returns table html element of grid.
	 * @return \Nette\Utils\Html
	 */
	public function getTablePrototype(): \Nette\Utils\Html
	{
		if ($this->tablePrototype === null) {
			$this->tablePrototype = \Nette\Utils\Html::el('table');
			$this->tablePrototype->id($this->getName());
		}

		return $this->tablePrototype;
	}


	/**
	 * @return string
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


	public function getModel(): DataSources\IDataSource
	{
		return $this->model;
	}


	/**
	 * @internal
	 */
	public function getPaginator(): Paginator
	{
		if ($this->paginator === null) {
			$this->paginator = new Paginator;
			$this->paginator->setItemsPerPage($this->getPerPage())
				->setGrid($this);
		}

		return $this->paginator;
	}


	/**
	 * A simple wrapper around symfony/property-access with Nette Database dot notation support.
	 * @param array|object $object
	 * @param string $name
	 * @return mixed
	 * @internal
	 */
	public function getProperty($object, string $name)
	{
		if ($object instanceof \Nette\Database\Table\IRow && \Nette\Utils\Strings::contains($name, '.')) {
			$parts = explode('.', $name);
			foreach ($parts as $item) {
				if (is_object($object)) {
					$object = $object->$item;
				}
			}

			return $object;
		}

		if (is_array($object)) {
			$name = "[$name]";
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
	 * @return \Nette\Utils\Html
	 * @internal
	 */
	public function getRowPrototype($row): \Nette\Utils\Html
	{
		try {
			$primaryValue = $this->getProperty($row, $this->getPrimaryKey());
		} catch (\Exception $e) {
			$primaryValue = null;
		}

		$tr = \Nette\Utils\Html::el('tr');
		$primaryValue ? $tr->class[] = "grid-row-$primaryValue" : null;

		if ($this->rowCallback) {
			$tr = call_user_func_array($this->rowCallback, [$row, $tr]);
		}

		return $tr;
	}


	/**
	 * Returns client-side options.
	 * @return array
	 */
	public function getClientSideOptions(): array
	{
		return (array) $this->options[self::CLIENT_SIDE_OPTIONS];
	}


	/**
	 * @return bool
	 */
	public function isStrictMode(): bool
	{
		return $this->strictMode;
	}


	/**
	 * @return Customization
	 */
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
	 * @param array $params
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
	 * @param int $page
	 * @internal
	 */
	public function handlePage($page): void
	{
		$this->reload();
	}


	/**
	 * @param array $sort
	 * @internal
	 */
	public function handleSort(array $sort): void
	{
		$this->page = 1;
		$this->reload();
	}


	/**
	 * @param \Nette\Forms\Controls\SubmitButton $button
	 * @internal
	 */
	public function handleFilter(\Nette\Forms\Controls\SubmitButton $button): void
	{
		$values = $button->form->values[Filter::ID];
		// $session = $this->rememberState // session filter
		// 	?
		// 	($this->getRememberSession(true)->params['filter'] ?? [])
		// 	: [];

		foreach ($values as $name => $value) {
			if (/* is_numeric($value) ||  */!empty($value) || isset($this->defaultFilter[$name]) /* || isset($session[$name]) */) {
				$this->filter[$name] = $this->getFilter($name)->changeValue($value);
			} elseif (array_key_exists($name, $this->filter)) {
				unset($this->filter[$name]);
			}
		}

		$this->page = 1;
		$this->reload();
	}


	/**
	 * @param \Nette\Forms\Controls\SubmitButton $button
	 * @internal
	 */
	public function handleReset(\Nette\Forms\Controls\SubmitButton $button): void
	{
		$this->sort = [];
		$this->filter = [];
		$this->perPage = null;

		if ($session = $this->getRememberSession()) {
			$session->remove();
		}

		$button->form->setValues([Filter::ID => $this->defaultFilter], true);

		$this->page = 1;
		$this->reload();
	}


	/**
	 * @param \Nette\Forms\Controls\SubmitButton $button
	 * @internal
	 */
	public function handlePerPage(\Nette\Forms\Controls\SubmitButton $button): void
	{
		$perPage = (int) $button->form['count']->value;
		$this->perPage = $perPage == $this->defaultPerPage ? null : $perPage;

		$this->page = 1;
		$this->reload();
	}


	/**
	 * Refresh wrapper.
	 * @return void
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
	public function createTemplate(): \Nette\Application\UI\Template
	{
		$template = parent::createTemplate();
		$template->setFile($this->getCustomization()->getTemplateFiles()[Customization::TEMPLATE_DEFAULT]);
		$latte = $template->getLatte();
		// latte/latte ^3.0
		if (class_exists('\Latte\Essential\TranslatorExtension')) {
			$latte->addExtension(new \Latte\Essential\TranslatorExtension($this->getTranslator()));
			$latte->addExtension(new \Latte\Essential\RawPhpExtension);
		} else {
			$latte->addFilter('translate', [$this->getTranslator(), 'translate']);
		}

		return $template;
	}


	/**
	 * @internal
	 * @throws Exception
	 */
	public function render()
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
				$this->getTablePrototype()->setAttribute('data-' . self::CLIENT_SIDE_OPTIONS, json_encode($options));
			}
		}
		$this->getTemplate()->render();
	}


	protected function saveRememberState(): void
	{
		if ($this->rememberState) {
			$session = $this->getRememberSession(true);
			$params = array_keys($this->getReflection()->getPersistentParams());
			foreach ($params as $param) {
				$session->params[$param] = $this->$param;
			}
		}
	}


	protected function applyFiltering(): void
	{
		$conditions = $this->__getConditions($this->getActualFilter());
		$this->getModel()->filter($conditions);
	}


	/**
	 * @param array $filter
	 * @return array
	 * @internal
	 */
	public function __getConditions(array $filter): array
	{
		$conditions = [];
		if (!empty($filter)) {
			try {
				$this['form']->setDefaults([Filter::ID => $filter]);
			} catch (\Nette\InvalidArgumentException $e) {
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
					$this->__triggerUserNotice("Filter with name '$column' does not exist.");
				}
			}
		}

		return $conditions;
	}


	protected function applySorting(): void
	{
		$sort = [];
		$this->sort = $this->sort ? $this->sort : $this->defaultSort;

		foreach ($this->sort as $column => $dir) {
			$component = $this->getColumn($column, false);
			if (!$component) {
				if (!isset($this->defaultSort[$column])) {
					$this->__triggerUserNotice("Column with name '$column' does not exist.");
					break;
				}
			} elseif (!$component->isSortable()) {
				if (isset($this->defaultSort[$column])) {
					$component->setSortable();
				} else {
					$this->__triggerUserNotice("Column with name '$column' is not sortable.");
					break;
				}
			}

			if (!in_array($dir, [Column::ORDER_ASC, Column::ORDER_DESC])) {
				if ($dir == '' && isset($this->defaultSort[$column])) {
					unset($this->sort[$column]);
					break;
				}

				$this->__triggerUserNotice("Dir '$dir' is not allowed.");
				break;
			}

			$sort[$component ? $component->column : $column] = $dir == Column::ORDER_ASC ? 'ASC' : 'DESC';
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
		if ($perPage !== null && !in_array($perPage, $this->perPageList)) {
			$this->__triggerUserNotice("The number '$perPage' of items per page is out of range.");
		}

		$this->getModel()->limit($paginator->getOffset(), $paginator->getLength());
	}


	protected function createComponentForm($name)
	{
		$form = new \Nette\Application\UI\Form($this, $name);
		$form->setTranslator($this->getTranslator());
		$form->setMethod($form::GET);

		$buttons = $form->addContainer(self::BUTTONS);
		$buttons->addSubmit('search', 'Grido.Search')
			->onClick[] = [$this, 'handleFilter'];
		$buttons->addSubmit('reset', 'Grido.Reset')
			->onClick[] = [$this, 'handleReset'];
		$buttons->addSubmit('perPage', 'Grido.ItemsPerPage')
			->onClick[] = [$this, 'handlePerPage'];

		$form->addSelect('count', 'Count', $this->getItemsForCountSelect())
			->setTranslator(null)
			->controlPrototype->attrs['title'] = $this->getTranslator()->translate('Grido.ItemsPerPage');
	}


	/**
	 * @return array
	 */
	protected function getItemsForCountSelect(): array
	{
		return array_combine($this->perPageList, $this->perPageList);
	}


	/**
	 * @internal
	 * @param string $message
	 */
	public function __triggerUserNotice($message): void
	{
		if ($this->lookup(Presenter::class, false) && $session = $this->getRememberSession()) {
			$session->remove();
		}

		$this->strictMode && trigger_error($message, E_USER_NOTICE);
	}
}
