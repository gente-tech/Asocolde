<?php

namespace Drupal\asocolderma_inscription\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\node\NodeInterface;
use Drupal\enterprise_integrations\Service\ZohoSignService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpFoundation\Response;
use Drupal\Core\Routing\TrustedRedirectResponse;
use Drupal\asocolderma_inscription\Exception\SolicitudSignatureException;
use Drupal\asocolderma_inscription\Service\SolicitudSignatureManager;

final class SolicitudSignatureController extends ControllerBase
{

	public function __construct(
		private readonly ZohoSignService $zohoSignService,
		private readonly SolicitudSignatureManager $signatureManager,
	) {}

	public static function create(ContainerInterface $container): self
	{
		return new self(
			$container->get('enterprise_integrations.zoho_sign'),
			$container->get('asocolderma_inscription.solicitud_signature_manager'),
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

		try {
			$sign_url = $this->signatureManager->prepareSignUrl($node);

			return new TrustedRedirectResponse($sign_url);
		} catch (SolicitudSignatureException $e) {
			/*
			* Una firma ya completada es una condición de negocio, no un fallo
			* técnico que debamos exponer al aspirante.
			*/
			if ($e->getTechnicalCode() === 'FIRMA_SOLICITUD_YA_COMPLETADA') {
				$this->messenger()->addStatus(
					'Este documento ya fue firmado correctamente.'
				);

				return $this->redirect(
					'asocolderma_inscription.user_zone_requests'
				);
			}

			/*
			* Los detalles técnicos ya fueron registrados por
			* SolicitudSignatureManager.
			*
			* Nunca exponemos nombres de campos, IDs de Zoho, respuestas HTTP,
			* payloads ni detalles de configuración al aspirante.
			*/
			$this->messenger()->addError(
				'En este momento no está habilitada la firma del documento. Por favor, contacte con el administrador del sistema para continuar con el proceso.'
			);

			return $this->redirect(
				'asocolderma_inscription.user_zone_requests'
			);
		} catch (\Throwable $e) {
			$solicitud_code = (
				$node->hasField('field_solicitud_id')
				&& !$node->get('field_solicitud_id')->isEmpty()
			)
				? (string) $node->get('field_solicitud_id')->value
				: 'NID-' . $node->id();

			$this->getLogger('asocolderma_inscription')->error(
				'[FIRMA_ERROR_NO_CONTROLADO_CONTROLLER] Error inesperado en el controlador de firma | Solicitud NID: @nid | Código: @code | Error: @error',
				[
					'@nid' => $node->id(),
					'@code' => $solicitud_code,
					'@error' => $e->getMessage(),
				]
			);

			$this->messenger()->addError(
				'En este momento no está habilitada la firma del documento. Por favor, contacte con el administrador del sistema para continuar con el proceso.'
			);

			return $this->redirect(
				'asocolderma_inscription.user_zone_requests'
			);
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

			$is_completed = $this->zohoSignService
				->isSignatureCompletedForSolicitud((int) $node->id(), TRUE);

			if ($is_completed) {
				$this->messenger()->addStatus(
					'La firma del documento fue confirmada correctamente.'
				);
			} else {
				$this->messenger()->addStatus(
					'Aún no se evidencia la firma completa del documento.'
				);
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

	public function viewSignedDocument(NodeInterface $node): Response
	{
		if ($node->bundle() !== 'solicitud_ingreso') {
			throw new AccessDeniedHttpException();
		}

		$current_user = $this->currentUser();

		$is_owner = ((int) $node->getOwnerId() === (int) $current_user->id());
		$is_coord_admin = in_array('coordinacion_administrativa', $current_user->getRoles(), TRUE);

		if (!$is_owner && !$is_coord_admin) {
			throw new AccessDeniedHttpException();
		}

		try {
			$mapping = $this->zohoSignService->getLatestRequestMappingBySolicitud((int) $node->id());

			if (empty($mapping['zoho_request_id'])) {
				throw new \RuntimeException('No existe documento firmado.');
			}

			$request_id = (string) $mapping['zoho_request_id'];

			$pdf = $this->zohoSignService->downloadSignedDocument($request_id);

			return new Response($pdf, 200, [
				'Content-Type' => 'application/pdf',
				'Content-Disposition' => 'inline; filename="documento_firmado_' . $node->id() . '.pdf"',
			]);
		} catch (\Throwable $e) {
			$this->getLogger('asocolderma_inscription')->error(
				'Error mostrando documento firmado @nid: @message',
				[
					'@nid' => $node->id(),
					'@message' => $e->getMessage(),
				]
			);

			$this->messenger()->addError('No fue posible cargar el documento firmado.');
			return $this->redirect('asocolderma_inscription.user_zone_requests');
		}
	}
}
