<?php

declare(strict_types=1);

namespace Drupal\asocolderma_inscription\Controller;

use Drupal\asocolderma_inscription\Service\SolicitudAspirantesReportService;
use Drupal\asocolderma_inscription\Service\SolicitudAspirantesXlsxWriter;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Extension\ModuleExtensionList;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;

/** Descarga de XLSX institucional con trazabilidad. */
final class SolicitudAspirantesReportController extends ControllerBase
{

	public function __construct(
		private readonly SolicitudAspirantesReportService $report,
		private readonly SolicitudAspirantesXlsxWriter $writer,
		private readonly ModuleExtensionList $moduleExtensionList,
	) {}

	public static function create(ContainerInterface $container): self
	{
		return new self(
			$container->get('asocolderma_inscription.aspirantes_report'),
			$container->get('asocolderma_inscription.aspirantes_xlsx_writer'),
			$container->get('extension.list.module'),
		);
	}

	public function download(): BinaryFileResponse
	{
		$filters = $this->report->normalizeFilters(\Drupal::request()->query->all());
		if ($filters['desde'] !== '' && $filters['hasta'] !== '' && $filters['desde'] > $filters['hasta']) {
			throw new \Symfony\Component\HttpKernel\Exception\BadRequestHttpException('Rango de fechas inválido.');
		}
		$ids = $this->report->getIds($filters);
		$data = $this->report->buildRows($ids);
		$template = $this->moduleExtensionList->getPath('asocolderma_inscription')
			. '/assets/reporte_aspirantes_plantilla.xlsx';
		// Ruta de código módulo puede ser relativa al DRUPAL_ROOT.
		$template = DRUPAL_ROOT . '/' . ltrim($template, '/');
		$temporary = tempnam(sys_get_temp_dir(), 'asocol_aspirantes_');
		if ($temporary === FALSE) {
			throw new \RuntimeException('No fue posible reservar el archivo temporal del reporte.');
		}
		try {
			$actor = (string) $this->currentUser()->getDisplayName();
			$date = \Drupal::service('date.formatter')->format(\Drupal::time()->getRequestTime(), 'custom', 'd/m/Y H:i');
			$this->writer->write($template, $temporary, $data['rows'], $data['totals'], $actor, $date);
		} catch (\Throwable $e) {
			@unlink($temporary);
			\Drupal::logger('asocolderma_inscription')->error('Error generando XLSX institucional: @message', ['@message' => $e->getMessage()]);
			throw $e;
		}
		$filename = 'reporte_aspirantes_asocolderma_' . date('Y-m-d_H-i-s') . '.xlsx';
		\Drupal::logger('asocolderma_inscription')->notice(
			'Descarga de reporte de aspirantes por UID @uid: @count solicitudes, filtros @filters',
			[
				'@uid' => $this->currentUser()->id(),
				'@count' => count($ids),
				'@filters' => json_encode($filters, JSON_UNESCAPED_UNICODE),
			],
		);
		$response = new BinaryFileResponse($temporary);
		$response->headers->set('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
		$response->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, $filename);
		$response->deleteFileAfterSend(TRUE);
		$response->headers->set('Cache-Control', 'private, no-store, no-cache, must-revalidate');
		return $response;
	}
}
