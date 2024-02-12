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

namespace Grido\Translations;

use Grido\Exception;
use Nette\Localization\Translator;
use Nette\SmartObject;

/**
 * Simple file translator.
 */
final class FileTranslator implements Translator
{
	use SmartObject;

	protected array $translations = [];


	public function __construct(string $lang = 'en', array $translations = [])
	{
		$translations = $translations + $this->getTranslationsFromFile($lang);
		$this->translations = $translations;
	}


	public function setLang(string $lang): void
	{
		$this->translations = $this->getTranslationsFromFile($lang);
	}


	/**
	 * @throws Exception
	 */
	protected function getTranslationsFromFile(string $lang): array
	{
		$filename = __DIR__ . "/{$lang}.php";
		if (!file_exists($filename)) {
			throw new Exception("Translations for language '{$lang}' not found.");
		}

		return include($filename);
	}


	/*	 * *********************** interface \Nette\Localization\ITranslator ************************* */

	// public function translate(string|\Stringable $message, mixed ...$parameters): string|\Stringable
	public function translate($message, ...$parameters): string
	{
		return $this->translations[$message] ?? (string) $message;
	}
}
