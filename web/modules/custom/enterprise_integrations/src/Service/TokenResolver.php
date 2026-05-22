<?php

namespace Drupal\enterprise_integrations\Service;

/**
 * Servicio genérico para reemplazar tokens dinámicos en textos.
 *
 * Ejemplo:
 * Texto: "Preinscripción programa - [programa]"
 * Variables: ['programa' => 'Cirugía Dermatológica']
 * Resultado: "Preinscripción programa - Cirugía Dermatológica"
 */
class TokenResolver
{

	/**
	 * Reemplaza tokens tipo [programa], [nombre_usuario], [email], etc.
	 *
	 * @param string $text
	 *   Texto base con tokens.
	 * @param array $variables
	 *   Variables disponibles para reemplazo.
	 *
	 * @return string
	 *   Texto final con los tokens reemplazados.
	 */
	public function replace(string $text, array $variables = []): string
	{
		$replacements = [];

		foreach ($variables as $key => $value) {
			$key = trim((string) $key);

			if ($key === '') {
				continue;
			}

			$replacements['[' . $key . ']'] = trim((string) $value);
		}

		return strtr($text, $replacements);
	}
}
