<?php

/**
 * This file is part of the Grido (http://grido.bugyik.cz)
 *
 * Copyright (c) 2011 Petr Bugyík (http://petr.bugyik.cz)
 *
 * For the full copyright and license information, please view
 * the file LICENSE.md that was distributed with this source code.
 */

namespace Grido\Tests;

use Nette\Http\Helpers;
use Tester\Assert;

/**
 * Test helper.
 *
 * @author     Petr Bugyík
 * @package    Grido\Tests
 */
class Helper
{
    const GRID_NAME = 'grid';

    /** @var \Grido\Grid */
    public static $grid;

    /** @var TestPresenter */
    public static $presenter;

    /**
     * A grid attached to a presenter - since Nette Application 3.2 creating a subcomponent (the filter form) checks
     * the access policy, which needs the presenter.
     */
    public static function attachedGrid(): \Grido\Grid
    {
        static $count = 0;
        self::$presenter ??= (new self)->createPresenter();
        return new \Grido\Grid(self::$presenter, 'attached' . ++$count);
    }

    /**
     * @param \Closure $definition of grid; function(Grid $grid, TestPresenter $presenter) { };
     */
    public static function grid(\Closure $definition)
    {
        $self = new self;

        if (self::$presenter === NULL) {
            self::$presenter = $self->createPresenter();
        }

        self::$presenter->onStartUp = [];
        self::$presenter->onStartUp[] = function(TestPresenter $presenter) use ($definition) {
            if (isset($presenter[Helper::GRID_NAME])) {
                unset($presenter[Helper::GRID_NAME]);
            }

            $definition(new \Grido\Grid($presenter, Helper::GRID_NAME), $presenter);
        };

        return $self;
    }

    /**
     * @param array $params
     * @param string $method
     * @return \Nette\Application\IResponse
     */
    public static function request(array $params = [], $method = \Nette\Http\IRequest::Get)
    {
        $request = new \Nette\Application\Request('Test', $method, $params);
        $response = self::$presenter->run($request);

        self::$grid = self::$presenter[self::GRID_NAME];

        return $response;
    }

    /**
     * @param array $params
     * @param string $method
     * @return \Nette\Application\IResponse
     */
    public function run(array $params = [], $method = \Nette\Http\IRequest::Get)
    {
        return self::request($params, $method);
    }

    public static function assertTypeError($function)
    {
        if (PHP_VERSION_ID < 70000) {
            Assert::error($function, E_RECOVERABLE_ERROR);
        } else {
            Assert::exception($function, '\TypeError');
        }
    }

    /**
     * @return \TestPresenter
     */
    private function createPresenter()
    {
        $url = new \Nette\Http\UrlScript('http://localhost/', '/');

        $configurator = new \Nette\Bootstrap\Configurator;
        $configurator->addConfig(__DIR__ . '/config.neon');

        $container = $configurator
            ->setTempDirectory(TEMP_DIR)
            ->createContainer();
        $container->removeService('httpRequest');
        // Helpers::StrictCookieName must be set in cookie for Component::checkRequirements() properly handle `do` signals
        $httpRequest = new \Nette\Http\Request($url, [], [], [Helpers::StrictCookieName => true]);
        $container->addService('httpRequest', $httpRequest);

        // the route is in config.neon (routing: routes)
        $presenter = new TestPresenter($container);
        $container->callInjects($presenter);
        $presenter->invalidLinkMode = $presenter::INVALID_LINK_WARNING;
        $presenter->autoCanonicalize = FALSE;

        return $presenter;
    }
}

class TestPresenter extends \Nette\Application\UI\Presenter
{
    /** @var array */
    public $onStartUp;

    public function __construct(
        // the DI container - the presenter has no getter for it since Nette 3
        public \Nette\DI\Container $context,
    ) {
        parent::__construct();
    }

    /** @var bool */
    public $forceAjaxMode = FALSE;

    public function startup()
    {
        parent::startup();

        $this->onStartUp($this);
    }

    public function sendTemplate(?\Nette\Application\UI\Template $template = null): void
    {
        //parent::sendTemplate(); intentionally
    }

    public function sendResponse(\Nette\Application\Response $response): void
    {
        if($response instanceof \Nette\Application\Responses\JsonResponse){
            $response->send($this->getHttpRequest(), $this->getHttpResponse());
        } else {
            parent::sendResponse($response);
        }
    }

    public function isAjax(): bool
    {
        return $this->forceAjaxMode === TRUE
            ? TRUE
            : parent::isAjax();
    }

    public function terminate(): void
    {
        if ($this->forceAjaxMode === FALSE) {
            parent::terminate();
        }
    }

	public function getContext(): \Nette\DI\Container
	{
        return @parent::getContext();
	}
}
