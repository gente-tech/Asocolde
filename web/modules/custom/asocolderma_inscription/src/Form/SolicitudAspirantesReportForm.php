<?php

declare(strict_types=1);

namespace Drupal\asocolderma_inscription\Form;

use Drupal\asocolderma_inscription\Service\SolicitudAspirantesReportService;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/** Filtros y descarga de reporte institucional de aspirantes. */
final class SolicitudAspirantesReportForm extends FormBase
{

	public function __construct(
		private readonly SolicitudAspirantesReportService $report,
		private readonly EntityTypeManagerInterface $entityTypeManager,
	) {}

	public static function create(ContainerInterface $container): self
	{
		return new self(
			$container->get('asocolderma_inscription.aspirantes_report'),
			$container->get('entity_type.manager'),
		);
	}

	public function getFormId(): string
	{
		return 'asocolderma_aspirantes_report_form';
	}

	public function buildForm(array $form, FormStateInterface $form_state): array
	{
		$query = \Drupal::request()->query->all();
		$filters = $this->report->normalizeFilters($query);

		$form['intro'] = [
			'#type' => 'item',
			'#markup' => $this->t('Descarga el reporte institucional de aspirantes en formato Excel (.xlsx), con 26 columnas y una segunda hoja de leyenda. Los campos sin evidencia registrada se exportan vacíos.'),
		];
		$form['filters'] = [
			'#type' => 'details',
			'#title' => $this->t('Filtros del reporte'),
			'#open' => TRUE,
		];
		$form['filters']['desde'] = [
			'#type' => 'date',
			'#title' => $this->t('Fecha de solicitud desde'),
			'#default_value' => $filters['desde'],
		];
		$form['filters']['hasta'] = [
			'#type' => 'date',
			'#title' => $this->t('Fecha de solicitud hasta'),
			'#default_value' => $filters['hasta'],
		];
		$form['filters']['estado'] = [
			'#type' => 'select',
			'#title' => $this->t('Estado de la solicitud'),
			'#options' => $this->termOptions('estado_solicitud_ingreso'),
			'#default_value' => $filters['estado'],
		];
		$form['filters']['tipo'] = [
			'#type' => 'select',
			'#title' => $this->t('Tipo de asociado'),
			'#options' => $this->termOptions('tipo_de_asociado'),
			'#default_value' => $filters['tipo'],
		];
		$form['actions'] = ['#type' => 'actions'];
		$form['actions']['apply'] = [
			'#type' => 'submit',
			'#value' => $this->t('Aplicar filtros'),
			'#button_type' => 'primary',
		];
		$form['actions']['reset'] = [
			'#type' => 'link',
			'#title' => $this->t('Limpiar filtros'),
			'#url' => Url::fromRoute('asocolderma_inscription.aspirantes_report'),
			'#attributes' => ['class' => ['button']],
		];
		$count = count($this->report->getIds($filters));
		$form['result'] = [
			'#type' => 'item',
			'#title' => $this->t('Solicitudes encontradas'),
			'#markup' => (string) $count,
		];
		$form['download'] = [
			'#type' => 'link',
			'#title' => $this->t('Descargar Excel (.xlsx)'),
			'#url' => Url::fromRoute('asocolderma_inscription.aspirantes_report_download', [], [
				'query' => array_filter($filters, static fn($v) => $v !== '' && $v !== 0),
			]),
			'#attributes' => ['class' => ['button', 'button--primary']],
		];
		$form['note'] = [
			'#type' => 'item',
			'#markup' => $this->t('El reporte incluye datos personales y debe utilizarse únicamente para gestión institucional autorizada. La descarga queda registrada en el sistema.'),
		];
		$form['#cache']['max-age'] = 0;
		return $form;
	}

	public function validateForm(array &$form, FormStateInterface $form_state): void
	{
		$desde = (string) $form_state->getValue('desde');
		$hasta = (string) $form_state->getValue('hasta');
		if ($desde !== '' && $hasta !== '' && $desde > $hasta) {
			$form_state->setErrorByName('hasta', $this->t('La fecha final no puede ser anterior a la inicial.'));
		}
	}

	public function submitForm(array &$form, FormStateInterface $form_state): void
	{
		$filters = $this->report->normalizeFilters([
			'desde' => $form_state->getValue('desde'),
			'hasta' => $form_state->getValue('hasta'),
			'estado' => $form_state->getValue('estado'),
			'tipo' => $form_state->getValue('tipo'),
		]);
		$form_state->setRedirect('asocolderma_inscription.aspirantes_report', [], [
			'query' => array_filter($filters, static fn($v) => $v !== '' && $v !== 0),
		]);
	}

	private function termOptions(string $vid): array
	{
		$options = [0 => (string) $this->t('- Todos -')];
		foreach ($this->entityTypeManager->getStorage('taxonomy_term')->loadTree($vid) as $term) {
			$options[(int) $term->tid] = (string) $term->name;
		}
		return $options;
	}
}
