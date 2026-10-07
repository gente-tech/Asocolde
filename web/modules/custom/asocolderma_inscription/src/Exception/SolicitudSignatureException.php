<?php

declare(strict_types=1);

namespace Drupal\asocolderma_inscription\Exception;

/**
 * Excepción controlada del proceso de preparación de firma.
 *
 * El código técnico es exclusivamente para logs y diagnóstico interno.
 * Nunca debe mostrarse directamente al aspirante.
 */
final class SolicitudSignatureException extends \RuntimeException
{

	public function __construct(
		private readonly string $technicalCode,
		string $message,
		private readonly array $context = [],
		?\Throwable $previous = NULL,
	) {
		parent::__construct($message, 0, $previous);
	}

	/**
	 * Código interno utilizado para clasificación y filtrado de logs.
	 */
	public function getTechnicalCode(): string
	{
		return $this->technicalCode;
	}

	/**
	 * Contexto técnico adicional del error.
	 */
	public function getContext(): array
	{
		return $this->context;
	}
}
