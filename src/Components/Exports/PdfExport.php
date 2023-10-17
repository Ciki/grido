<?php

declare(strict_types=1);

namespace Grido\Components\Exports;

use Ciki\Grido\ColumnNumber;
use Contributte\PdfResponse\PdfResponse;
use Grido\Components\Columns\Column;
use Nette\Http\IResponse;
use Nette\Utils\ArrayHash;

final class PdfExport extends BaseExport
{

	protected function printData(): void
	{
		$columns = $this->grid[Column::ID]->getComponents();
		$header = [];
		$headerItems = $this->header ?: $columns;
		foreach ($headerItems as $column) {
			$header[] = $this->header ? $column : $column->getLabel();
		}

		$datasource = $this->grid->getData(false, false, false);
		$iterations = ceil($datasource->getCount() / $this->fetchLimit);
		$formattedData = [];
		$sums = [];
		for ($i = 0; $i < $iterations; $i++) {
			$datasource->limit($i * $this->fetchLimit, $this->fetchLimit);
			$data = $this->customData ? call_user_func_array($this->customData, [$datasource]) : $datasource->getData();

			foreach ($data as $items) {
				$row = [];

				$columns = $this->customData ? $items : $columns;

				foreach ($columns as $columnName => $column) {
					$row[$columnName] = $this->customData ? $column : $column->renderExport($items);

					if ($column instanceof ColumnNumber && $column->getCalculateSum()) {
						if (!isset($sums[$columnName])) {
							$sums[$columnName] = 0;
						}
						// dump($row[$columnName], $column->getValueForSumCalculation($items), $column->getValue($items));
						$sums[$columnName] += $column->getValueForSumCalculation($items);
					}
				}
				$formattedData[] = $row;
			}
			unset($row);
		}

		// Tip: In template to make a new page use <pagebreak>
		$template = $this->getPresenter()->getTemplate();
		$template->setFile(__DIR__ . '/pdf_export.latte');
		$template->header = $header;
		$template->data = ArrayHash::from($formattedData);
		$template->sums = ArrayHash::from($sums);
		$template->columns = $columns;
		// dumpe($header, $data, $columns, $template->data, $sums);
		// \Utils\Basic::downloadFile(null, 'test.html', false, $template->renderToString());

		$pdf = new PdfResponse($template);

		// optional
		// $pdf->documentTitle = date("Y-m-d H:i") . " PDF export"; // creates filename
		$pdf->pageFormat = $this->options['pageFormat'] ?? count($columns) > 5 ? 'A4-L' : 'A4';
		// $mpdf = $pdf->getMPDF();
		// Memory optim https://mpdf.github.io/troubleshooting/memory-problems.html
		// https://mpdf.github.io/reference/mpdf-variables/simpletables.html
		// $mpdf->simpleTables = true;
		// https://mpdf.github.io/reference/mpdf-variables/packtabledata.html
		// $mpdf->packTableData = true;
		// $mpdf->setFooter("|© www.PROJECT.sk|");
		// $pdf->outputDestination = $pdf::OUTPUT_DOWNLOAD;
		// $pdf->save = $pdf::OUTPUT_DOWNLOAD;
		echo $pdf->__toString();
	}


	protected function setHttpHeaders(IResponse $httpResponse, string $label): void
	{
		$encoding = 'utf-8';
		$httpResponse->setHeader('Content-Description', 'File Transfer');
		$httpResponse->setHeader('Content-Transfer-Encoding', 'binary');
		$httpResponse->setHeader('Content-Encoding', $encoding);
		$httpResponse->setHeader('Content-Type', "application/pdf; charset=$encoding");
		$httpResponse->setHeader('Content-Disposition', "attachment; filename=\"$label.pdf\"");
	}
}
