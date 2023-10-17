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

use DirectoryIterator;
use Nette\SmartObject;

/**
 * Customization.
 *
 * @property string|array $buttonClass
 * @property string|array $iconClass
 */
final class Customization
{
	use SmartObject;
	public const TEMPLATE_DEFAULT = 'default';
	public const TEMPLATE_BOOTSTRAP = 'bootstrap';

	protected string|array $buttonClass;

	protected string|array $iconClass;

	protected array $templateFiles = [];


	public function __construct(
		protected Grid $grid
	) {
	}


	public function setButtonClass(string|array $class): static
	{
		$this->buttonClass = $class;
		return $this;
	}


	public function setIconClass(string|array $class): static
	{
		$this->iconClass = $class;
		return $this;
	}


	public function getButtonClass(): string
	{
		return is_array($this->buttonClass) ? implode(' ', $this->buttonClass) : $this->buttonClass;
	}


	public function getIconClass(string $icon = null): string
	{
		if ($icon === null) {
			$class = $this->iconClass;
		} else {
			$this->iconClass = (array) $this->iconClass;
			$classes = [];
			foreach ($this->iconClass as $fontClass) {
				$classes[] = "{$fontClass} {$fontClass}-{$icon}";
			}
			$class = implode(' ', $classes);
		}

		return $class;
	}


	public function getTemplateFiles(): array
	{
		if (empty($this->templateFiles)) {
			foreach (new DirectoryIterator(__DIR__ . '/templates') as $file) {
				if ($file->isFile()) {
					$this->templateFiles[$file->getBasename('.latte')] = realpath($file->getPathname());
				}
			}
		}

		return $this->templateFiles;
	}


	public function useTemplateDefault(): static
	{
		$this->grid->setTemplateFile($this->getTemplateFiles()[self::TEMPLATE_DEFAULT]);
		return $this;
	}


	public function useTemplateBootstrap(): static
	{
		$this->grid->setTemplateFile($this->getTemplateFiles()[self::TEMPLATE_BOOTSTRAP]);
		return $this;
	}
}
