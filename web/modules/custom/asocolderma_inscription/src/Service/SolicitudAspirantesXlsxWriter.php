<?php

declare(strict_types=1);

namespace Drupal\asocolderma_inscription\Service;

/**
 * Rellena el archivo XLSX institucional sin depender de un exportador CSV.
 * Mantiene intactas sus dos hojas, estilos y agrupaciones originales.
 */
final class SolicitudAspirantesXlsxWriter
{

	private const XML_NS = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';

	/** @param list<array<int, string>> $rows */
	public function write(string $template, string $target, array $rows, array $totals, string $actor, string $date): void
	{
		if (!class_exists(\ZipArchive::class) || !class_exists(\DOMDocument::class)) {
			throw new \RuntimeException('El servidor requiere las extensiones PHP zip y dom para generar XLSX.');
		}
		if (!is_file($template) || !copy($template, $target)) {
			throw new \RuntimeException('No se pudo preparar la plantilla del reporte.');
		}
		$zip = new \ZipArchive();
		if ($zip->open($target) !== TRUE) {
			throw new \RuntimeException('No fue posible abrir el archivo XLSX.');
		}
		try {
			$main = $zip->getFromName('xl/worksheets/sheet1.xml');
			$legend = $zip->getFromName('xl/worksheets/sheet2.xml');
			if ($main === FALSE || $legend === FALSE) {
				throw new \RuntimeException('La plantilla XLSX no contiene las hojas esperadas.');
			}
			$main = $this->buildSheet($main, $rows, $totals, $actor, $date);
			$legend = $this->buildLegend($legend);
			if (
				!$zip->addFromString('xl/worksheets/sheet1.xml', $main)
				|| !$zip->addFromString('xl/worksheets/sheet2.xml', $legend)
			) {
				throw new \RuntimeException('No fue posible actualizar las hojas del XLSX.');
			}
		} finally {
			$zip->close();
		}
	}

	/** @param list<array<int, string>> $rows */
	private function buildSheet(string $xml, array $rows, array $totals, string $actor, string $date): string
	{
		$dom = $this->loadXml($xml);
		$xp = $this->xpath($dom);
		$sheetData = $xp->query('/m:worksheet/m:sheetData')->item(0);
		$sourceRow = $xp->query('/m:worksheet/m:sheetData/m:row[@r="6"]')->item(0);
		$alternateRow = $xp->query('/m:worksheet/m:sheetData/m:row[@r="7"]')->item(0);
		$summaryRow = $xp->query('/m:worksheet/m:sheetData/m:row[@r="16"]')->item(0);
		if (!$sheetData || !$sourceRow || !$alternateRow || !$summaryRow) {
			throw new \RuntimeException('Estructura de filas incompatible con el modelo XLSX.');
		}
		$sourceRow = $sourceRow->cloneNode(TRUE);
		$alternateRow = $alternateRow->cloneNode(TRUE);
		$summaryRow = $summaryRow->cloneNode(TRUE);

		// Eliminar los 10 registros de ejemplo y el resumen estático.
		foreach (iterator_to_array($xp->query('/m:worksheet/m:sheetData/m:row[number(@r) >= 6]')) as $oldRow) {
			$sheetData->removeChild($oldRow);
		}
		$rowNumber = 6;
		foreach ($rows as $index => $values) {
			$node = ($index % 2 === 0 ? $sourceRow : $alternateRow)->cloneNode(TRUE);
			$this->setRowNumber($node, $rowNumber);
			foreach ($this->xpath($dom)->query('m:c', $node) as $cell) {
				$column = preg_replace('/\d+/', '', (string) $cell->getAttribute('r'));
				$columnIndex = $this->columnNumber($column) - 1;
				$this->writeCell($dom, $cell, (string) ($values[$columnIndex] ?? ''));
			}
			$sheetData->appendChild($node);
			++$rowNumber;
		}

		$this->setRowNumber($summaryRow, $rowNumber);
		$summary = [
			'A' => 'RESUMEN ▼',
			'E' => 'Total solicitudes: ' . ($totals['total'] ?? 0),
			'G' => 'Miembros activos: ' . ($totals['activos'] ?? 0),
			'I' => 'Hitos de pago sin validación individual',
			'K' => 'Ver estado por solicitud',
			'M' => 'Docs. incompletos: ' . ($totals['incompletos'] ?? 0),
			'O' => 'Rechazadas: ' . ($totals['rechazadas'] ?? 0),
		];
		foreach ($this->xpath($dom)->query('m:c', $summaryRow) as $cell) {
			$column = preg_replace('/\d+/', '', (string) $cell->getAttribute('r'));
			if (isset($summary[$column])) {
				$this->writeCell($dom, $cell, $summary[$column]);
			} else {
				$this->clearCell($cell);
			}
		}
		$sheetData->appendChild($summaryRow);

		foreach ($xp->query('/m:worksheet/m:mergeCells/m:mergeCell') as $merge) {
			$ref = $merge->getAttribute('ref');
			if (preg_match('/^[A-Z]+16:[A-Z]+16$/', $ref)) {
				$merge->setAttribute('ref', str_replace('16', (string) $rowNumber, $ref));
			}
		}
		$dimension = $xp->query('/m:worksheet/m:dimension')->item(0);
		if ($dimension) {
			$dimension->setAttribute('ref', 'A1:Z' . $rowNumber);
		}
		$this->replace($dom, $xp, 'N5', "① Aprobada\nSecretaría G.");
		$this->replace($dom, $xp, 'A3', 'Fecha de descarga:  ' . $date);
		$this->replace($dom, $xp, 'I3', 'Generado por:  ' . $actor);
		return $dom->saveXML();
	}

	private function buildLegend(string $xml): string
	{
		$dom = $this->loadXml($xml);
		$xp = $this->xpath($dom);
		$replacements = [
			'A5' => '① Aprobada Secretaría G.',
			'B5' => 'Aprobación inicial registrada por Secretaría General.',
			'A21' => '✓ Cargados',
			'B21' => 'Los 14 adjuntos obligatorios están cargados; esto NO significa que fueron verificados.',
			'A22' => '⚠ Incompletos',
			'B22' => 'Falta uno o más de los 14 adjuntos obligatorios; los otros 2 son opcionales.',
			'A26' => 'Miembro activo (pago implícito)',
			'B26' => 'La activación indica finalización del trámite; no existe fecha independiente de confirmación de pago.',
			'A27' => '⚠ Pendiente',
			'B27' => 'Estado pendiente de pago de ingreso, sin constancia de pago confirmado.',
		];
		foreach ($replacements as $cell => $value) {
			$this->replace($dom, $xp, $cell, $value);
		}
		return $dom->saveXML();
	}

	private function loadXml(string $xml): \DOMDocument
	{
		$dom = new \DOMDocument('1.0', 'UTF-8');
		$dom->preserveWhiteSpace = TRUE;
		if (!$dom->loadXML($xml, LIBXML_NONET)) {
			throw new \RuntimeException('La plantilla XLSX contiene XML inválido.');
		}
		return $dom;
	}

	private function xpath(\DOMDocument $dom): \DOMXPath
	{
		$xp = new \DOMXPath($dom);
		$xp->registerNamespace('m', self::XML_NS);
		return $xp;
	}

	private function setRowNumber(\DOMNode $row, int $rowNumber): void
	{
		$row->setAttribute('r', (string) $rowNumber);
		foreach ($row->childNodes as $cell) {
			if ($cell instanceof \DOMElement && $cell->localName === 'c') {
				$column = preg_replace('/\d+/', '', $cell->getAttribute('r'));
				$cell->setAttribute('r', $column . $rowNumber);
			}
		}
	}

	private function columnNumber(string $letters): int
	{
		$number = 0;
		foreach (str_split($letters) as $letter) {
			$number = $number * 26 + ord($letter) - ord('A') + 1;
		}
		return $number;
	}

	private function replace(\DOMDocument $dom, \DOMXPath $xp, string $address, string $value): void
	{
		$cell = $xp->query('/m:worksheet/m:sheetData/m:row/m:c[@r="' . $address . '"]')->item(0);
		if ($cell instanceof \DOMElement) {
			$this->writeCell($dom, $cell, $value);
		}
	}

	private function clearCell(\DOMElement $cell): void
	{
		foreach (iterator_to_array($cell->childNodes) as $child) {
			$cell->removeChild($child);
		}
		$cell->removeAttribute('t');
	}

	private function writeCell(\DOMDocument $dom, \DOMElement $cell, string $value): void
	{
		$this->clearCell($cell);
		$cell->setAttribute('t', 'inlineStr');
		// Excel debe tratar cualquier valor como texto, incluso si empieza por =.
		$is = $dom->createElementNS(self::XML_NS, 'is');
		$t = $dom->createElementNS(self::XML_NS, 't');
		$t->setAttribute('xml:space', 'preserve');
		$value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u', '', $value) ?? '';
		$t->appendChild($dom->createTextNode($value));
		$is->appendChild($t);
		$cell->appendChild($is);
	}
}
