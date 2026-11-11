<?php

declare(strict_types=1);

namespace Drupal\asocolderma_inscription\Service;

/**
 * Rellena el archivo XLSX institucional sin depender de un exportador CSV.
 * Mantiene intactas sus dos hojas, estilos y agrupaciones originales.
 */
final class SolicitudAspirantesXlsxWriter {

  private const XML_NS = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';

  /** @param list<array<int, string>> $rows */
  public function write(string $template, string $target, array $rows, array $totals, string $actor, string $date): void {
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
      if (!$zip->addFromString('xl/worksheets/sheet1.xml', $main)
        || !$zip->addFromString('xl/worksheets/sheet2.xml', $legend)) {
        throw new \RuntimeException('No fue posible actualizar las hojas del XLSX.');
      }
    }
    finally {
      $zip->close();
    }
  }

  /** @param list<array<int, string>> $rows */
  private function buildSheet(string $xml, array $rows, array $totals, string $actor, string $date): string {
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
      }
      else {
        $this->clearCell($cell);
      }
    }
    $sheetData->appendChild($summaryRow);