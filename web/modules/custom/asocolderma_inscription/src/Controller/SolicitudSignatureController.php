<?php

namespace Drupal\asocolderma_inscription\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\node\NodeInterface;
use Drupal\enterprise_integrations\Service\ZohoSignService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

final class SolicitudSignatureController extends ControllerBase
{

	public function __construct(
		private readonly ZohoSignService $zohoSignService,
	) {}

	public static function create(ContainerInterface $container): self
	{
		return new self(
			$container->get('enterprise_integrations.zoho_sign'),
		);
	}

	public function redirectToSign(NodeInterface $node): RedirectResponse
	{
		if ($node->bundle() !== 'solicitud_ingreso') {
			throw new AccessDeniedHttpException();
		}

		if ((int) $node->getOwnerId() !== (int) $this->currentUser()->id()) {
			throw new AccessDeniedHttpException();
		}

		$state_name = $this->getStateName($node);
		if ($state_name !== 'Pendiente firma de documentos') {
			$this->messenger()->addError('La solicitud no está habilitada para firma.');
			return $this->redirect('asocolderma_inscription.user_zone_requests');
		}

		try {
			$mapping = $this->zohoSignService->getLatestRequestMappingBySolicitud((int) $node->id());

			if (empty($mapping['zoho_request_id']) || empty($mapping['zoho_action_id'])) {
				$recipient_name = $this->resolveRecipientName($node);
				$recipient_email = $this->resolveRecipientEmail($node);

				if ($recipient_name === '' || $recipient_email === '') {
					throw new \RuntimeException('No fue posible resolver los datos del firmante.');
				}

				$created = $this->zohoSignService->createSignatureRequest([
					'solicitud_nid' => (int) $node->id(),
					'recipient_name' => $recipient_name,
					'recipient_email' => $recipient_email,
					'field_text_data' => $this->buildFieldTextData($node),
					'notes' => 'Solicitud de ingreso Asocolderma #' . $this->getSolicitudCode($node),
				]);

				$request_id = (string) ($created['request_id'] ?? '');
				$action_id = (string) ($created['action_id'] ?? '');
			} else {
				$request_id = (string) $mapping['zoho_request_id'];
				$action_id = (string) $mapping['zoho_action_id'];
			}

			if ($request_id === '' || $action_id === '') {
				throw new \RuntimeException('No fue posible determinar request_id/action_id.');
			}

			$fresh_sign = $this->zohoSignService->generateFreshSignUrl($request_id, $action_id);
			$sign_url = (string) ($fresh_sign['sign_url'] ?? '');

			if ($sign_url === '') {
				throw new \RuntimeException('Zoho no devolvió una URL de firma.');
			}

			return new RedirectResponse($sign_url);
		} catch (\Throwable $e) {
			$this->getLogger('asocolderma_inscription')->error(
				'Error preparando firma para solicitud @nid: @message',
				[
					'@nid' => $node->id(),
					'@message' => $e->getMessage(),
				]
			);

			$this->messenger()->addError('No fue posible abrir la firma en este momento.');
			return $this->redirect('asocolderma_inscription.user_zone_requests');
		}
	}

	public function returnFromSign(NodeInterface $node): RedirectResponse
	{
		if ($node->bundle() !== 'solicitud_ingreso') {
			throw new AccessDeniedHttpException();
		}

		if ((int) $node->getOwnerId() !== (int) $this->currentUser()->id()) {
			throw new AccessDeniedHttpException();
		}

		try {
			$mapping = $this->zohoSignService->getLatestRequestMappingBySolicitud((int) $node->id());

			if (empty($mapping['zoho_request_id'])) {
				$this->messenger()->addWarning('No se encontró un request de firma asociado a la solicitud.');
				return $this->redirect('asocolderma_inscription.user_zone_requests');
			}

			$request_id = (string) $mapping['zoho_request_id'];
			$details = $this->zohoSignService->getRequestDetails($request_id);

			$request_status = strtolower((string) ($details['requests']['request_status'] ?? ''));
			$action_status = strtolower((string) ($details['requests']['actions'][0]['action_status'] ?? ''));

			if (in_array($request_status, ['completed', 'signed'], TRUE) || in_array($action_status, ['signed', 'completed'], TRUE)) {
				$documentos_firmados_tid = $this->getStateTidByName('Documentos firmados');

				if ($documentos_firmados_tid) {
					$node->set('field_state', ['target_id' => $documentos_firmados_tid]);
					$node->save();

					$this->messenger()->addStatus('La firma fue completada y la solicitud fue actualizada a Documentos firmados.');
				} else {
					$this->messenger()->addWarning('El documento fue firmado, pero no se encontró el estado Documentos firmados.');
				}
			} else {
				$this->messenger()->addStatus('Aún no se evidencia la firma completa del documento.');
			}
		} catch (\Throwable $e) {
			$this->getLogger('asocolderma_inscription')->error(
				'Error sincronizando retorno de firma para solicitud @nid: @message',
				[
					'@nid' => $node->id(),
					'@message' => $e->getMessage(),
				]
			);

			$this->messenger()->addError('No fue posible validar el estado de la firma en este momento.');
		}

		return $this->redirect('asocolderma_inscription.user_zone_requests');
	}

	private function getStateName(NodeInterface $node): string
	{
		if (!$node->hasField('field_state') || $node->get('field_state')->isEmpty()) {
			return '';
		}

		$term = $node->get('field_state')->entity;
		return $term ? trim((string) $term->label()) : '';
	}

	private function resolveRecipientName(NodeInterface $node): string
	{
		$parts = [];

		foreach (['field_nombre1', 'field_nombre2', 'field_apellido1', 'field_apellido2'] as $field_name) {
			if ($node->hasField($field_name) && !$node->get($field_name)->isEmpty()) {
				$parts[] = trim((string) $node->get($field_name)->value);
			}
		}

		$full_name = trim(implode(' ', array_filter($parts)));

		if ($full_name !== '') {
			return $full_name;
		}

		$owner = $node->getOwner();
		return $owner ? trim((string) $owner->getDisplayName()) : '';
	}

	private function resolveRecipientEmail(NodeInterface $node): string
	{
		if ($node->hasField('field_email') && !$node->get('field_email')->isEmpty()) {
			return trim((string) $node->get('field_email')->value);
		}

		$owner = $node->getOwner();
		return ($owner && $owner->getEmail()) ? trim((string) $owner->getEmail()) : '';
	}

	private function buildFieldTextData(NodeInterface $node): array
	{
		return [
			'solicitud_id' => $this->getSolicitudCode($node),
			'nombre_completo' => $this->resolveRecipientName($node),
			'correo' => $this->resolveRecipientEmail($node),
			'documento' => $node->hasField('field_numero_documento') && !$node->get('field_numero_documento')->isEmpty()
				? (string) $node->get('field_numero_documento')->value
				: '',
			'registro_medico' => $node->hasField('field_registro_medico') && !$node->get('field_registro_medico')->isEmpty()
				? (string) $node->get('field_registro_medico')->value
				: '',
			'ciudad' => $node->hasField('field_ciudad_ejercicio') && !$node->get('field_ciudad_ejercicio')->isEmpty()
				? (string) $node->get('field_ciudad_ejercicio')->value
				: '',
		];
	}

	private function getSolicitudCode(NodeInterface $node): string
	{
		if ($node->hasField('field_solicitud_id') && !$node->get('field_solicitud_id')->isEmpty()) {
			return (string) $node->get('field_solicitud_id')->value;
		}

		return 'NID-' . $node->id();
	}

	private function getStateTidByName(string $state_name): ?int
	{
		$storage = $this->entityTypeManager()->getStorage('taxonomy_term');
		$terms = $storage->loadByProperties([
			'vid' => 'estado_solicitud_ingreso',
			'name' => $state_name,
		]);

		if (!$terms) {
			return NULL;
		}

		$term = reset($terms);
		return $term ? (int) $term->id() : NULL;
	}
}
