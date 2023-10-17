<?php

declare(strict_types=1);

namespace Grido\Components\Exports;

use Grido\Components\Columns\Column;
use Nette\Http\IResponse;

final class CsvExport extends BaseExport
{
	/** @deprecated */
	public const CSV_ID = 'csv';

	/** @type string */
	public const NEW_LINE = "\n";
	public const DELIMITER = "\t"; // tabulator (in UTF-16LE only) enables MS Excel to automatically format data into columns (comma nor ; does not) https://gitlab.com/Ciki/uiad/-/issues/575


	private readonly string $delimiter;


	public function __construct(string $label = null, ?string $filename = null, array $options = [])
	{
		$options['encoding'] ??= self::ENCODING_UTF16LE;
		$label ??= $filename;
		parent::__construct($label, $filename, $options);
		$this->delimiter = $options['delimiter'] ?? self::DELIMITER;
	}


	protected function printData(): void
	{
		$escape = function ($value): string {
			$value = (string) $value;
			return preg_match("~[\"\n,;\t]~", $value) || $value === "" ? '"' . str_replace('"', '""', $value) . '"' : $value;
		};

		$print = function (array $row): void {
			$source = implode($this->delimiter, $row) . self::NEW_LINE;
			if ($this->encoding !== mb_internal_encoding()) {
				$source = mb_convert_encoding($source, $this->encoding, mb_internal_encoding());
			}
			print $source;
		};

		$columns = $this->grid[Column::ID]->getComponents();

		$header = [];
		$headerItems = $this->header ?: $columns;
		foreach ($headerItems as $column) {
			$header[] = $this->header ? $escape($column) : $escape($column->getLabel());
		}
		$print($header);

		$datasource = $this->grid->getData(false, false, false);
		$iterations = ceil($datasource->getCount() / $this->fetchLimit);
		for ($i = 0; $i < $iterations; $i++) {
			$datasource->limit($i * $this->fetchLimit, $this->fetchLimit);
			$data = $this->customData ? call_user_func_array($this->customData, [$datasource]) : $datasource->getData();

			foreach ($data as $items) {
				$row = [];

				$columns = $this->customData ? $items : $columns;

				foreach ($columns as $column) {
					$row[] = $this->customData ? $escape($column) : $escape($column->renderExport($items));
				}

				$print($row);
			}
			unset($row);
		}
	}


	protected function setHttpHeaders(IResponse $httpResponse, string $label): void
	{
		$httpResponse->setHeader('Content-Encoding', $this->encoding);
		$httpResponse->setHeader('Content-Type', "text/csv; charset=$this->encoding");
		$httpResponse->setHeader('Content-Disposition', "attachment; filename=\"$label.csv\"");
	}
}
