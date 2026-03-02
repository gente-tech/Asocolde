<?php

namespace Drupal\asocolderma_inscription\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\user\UserInterface;

/**
 * @ContentEntityType(
 *   id = "solicitud_ingreso",
 *   label = @Translation("Solicitud de ingreso"),
 *   label_collection = @Translation("Solicitudes de ingreso"),
 *   handlers = {
 *     "access" = "Drupal\Core\Entity\EntityAccessControlHandler",
 *     "list_builder" = "Drupal\Core\Entity\EntityListBuilder",
 *     "views_data" = "Drupal\views\EntityViewsData",
 *     "route_provider" = {
 *       "html" = "Drupal\Core\Entity\Routing\AdminHtmlRouteProvider"
 *     }
 *   },
 *   base_table = "solicitud_ingreso",
 *   admin_permission = "administer solicitud ingreso",
 *   entity_keys = {
 *     "id" = "id",
 *     "uuid" = "uuid",
 *     "uid" = "uid",
 *     "label" = "solicitud_id"
 *   },
 *   links = {
 *     "collection" = "/admin/asocolderma/solicitudes",
 *     "canonical" = "/admin/asocolderma/solicitudes/{solicitud_ingreso}"
 *   }
 * )
 */
final class SolicitudIngreso extends ContentEntityBase
{

  /**
   * Returns the user that owns this solicitud.
   */
  public function getOwner(): ?UserInterface
  {
    /** @var \Drupal\user\UserInterface|null $owner */
    $owner = $this->get('uid')->entity;
    return $owner;
  }

  /**
   * Returns the owner user ID.
   */
  public function getOwnerId(): ?int
  {
    $uid = $this->get('uid')->target_id;
    return $uid !== NULL ? (int) $uid : NULL;
  }

  /**
   * Sets the owner user ID.
   */
  public function setOwnerId($uid): static
  {
    $this->set('uid', $uid);
    return $this;
  }

  /**
   * Sets the owner user.
   */
  public function setOwner(UserInterface $account): static
  {
    $this->set('uid', (int) $account->id());
    return $this;
  }

  public static function baseFieldDefinitions(EntityTypeInterface $entity_type): array
  {
    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['uid'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Aspirante'))
      ->setSetting('target_type', 'user')
      ->setRequired(TRUE);

    $fields['solicitud_id'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Código de solicitud'))
      ->setRequired(TRUE)
      ->setSetting('max_length', 32);

    $fields['state'] = BaseFieldDefinition::create('list_string')
      ->setLabel(t('Estado'))
      ->setRequired(TRUE)
      ->setSetting('allowed_values', [
        'in_progress' => 'En trámite',
        'needs_clarification' => 'Pendiente aclaración',
        'sg_approved' => 'Aprobada por Secretaría',
        'rejected' => 'Rechazada',
        'payment_pending' => 'Pago pendiente',
        'signature_pending' => 'Pendiente firma',
        'active_member' => 'Miembro activo',
      ])
      ->setDefaultValue('in_progress');

    $fields['submitted'] = BaseFieldDefinition::create('boolean')
      ->setLabel(t('Enviada'))
      ->setDefaultValue(FALSE);

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Creada'));

    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel(t('Actualizada'));

    // =======================
    // Paso 1: Información general
    // =======================
    $fields['tipo_asociado'] = BaseFieldDefinition::create('list_string')
      ->setLabel(t('Tipo de asociado'))
      ->setRequired(TRUE)
      ->setSetting('allowed_values', [
        'numero' => 'Número',
        'adherente' => 'Adherente',
        'correspondiente' => 'Correspondiente',
        'internacional' => 'Internacional',
      ]);

    $fields['nombre1'] = BaseFieldDefinition::create('string')->setLabel(t('Primer nombre'))->setRequired(TRUE)->setSetting('max_length', 100);
    $fields['nombre2'] = BaseFieldDefinition::create('string')->setLabel(t('Segundo nombre'))->setRequired(FALSE)->setSetting('max_length', 100);
    $fields['apellido1'] = BaseFieldDefinition::create('string')->setLabel(t('Primer apellido'))->setRequired(TRUE)->setSetting('max_length', 100);
    $fields['apellido2'] = BaseFieldDefinition::create('string')->setLabel(t('Segundo apellido'))->setRequired(FALSE)->setSetting('max_length', 100);

    $fields['fecha_nacimiento'] = BaseFieldDefinition::create('datetime')
      ->setLabel(t('Fecha de nacimiento'))
      ->setRequired(TRUE)
      ->setSetting('datetime_type', 'date');

    $fields['estado_civil'] = BaseFieldDefinition::create('string')->setLabel(t('Estado civil'))->setRequired(TRUE)->setSetting('max_length', 50);

    $fields['sexo'] = BaseFieldDefinition::create('list_string')
      ->setLabel(t('Sexo'))
      ->setRequired(TRUE)
      ->setSetting('allowed_values', [
        'masculino' => 'Masculino',
        'femenino' => 'Femenino',
        'no_indica' => 'Prefiero no decirlo',
      ]);

    $fields['tipo_documento'] = BaseFieldDefinition::create('string')->setLabel(t('Tipo de documento'))->setRequired(TRUE)->setSetting('max_length', 50);
    $fields['numero_documento'] = BaseFieldDefinition::create('string')->setLabel(t('Número de documento'))->setRequired(TRUE)->setSetting('max_length', 50);

    $fields['registro_medico'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Registro médico / tarjeta profesional'))
      ->setRequired(TRUE)
      ->setSetting('max_length', 100);

    $fields['pais'] = BaseFieldDefinition::create('string')->setLabel(t('País'))->setRequired(TRUE)->setSetting('max_length', 100);
    $fields['ciudad_ejercicio'] = BaseFieldDefinition::create('string')->setLabel(t('Ciudad de ejercicio principal'))->setRequired(TRUE)->setSetting('max_length', 100);
    $fields['departamento'] = BaseFieldDefinition::create('string')->setLabel(t('Departamento'))->setRequired(TRUE)->setSetting('max_length', 100);

    // =======================
    // Paso 2: Uso institucional
    // =======================
    $fields['direccion_institucional'] = BaseFieldDefinition::create('string_long')
      ->setLabel(t('Dirección principal/institucional'))
      ->setRequired(TRUE);

    $fields['email_principal'] = BaseFieldDefinition::create('email')
      ->setLabel(t('Correo electrónico principal'))
      ->setRequired(TRUE);

    $fields['celular'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Teléfono celular'))
      ->setRequired(TRUE)
      ->setSetting('max_length', 30);

    $fields['correspondencia_fisica'] = BaseFieldDefinition::create('list_string')
      ->setLabel(t('Correspondencia física'))
      ->setRequired(TRUE)
      ->setSetting('allowed_values', [
        'casa' => 'Casa',
        'consultorio' => 'Consultorio',
      ]);

    // =======================
    // Paso 3: Información académica
    // =======================
    $fields['facultad_pregrado'] = BaseFieldDefinition::create('string_long')
      ->setLabel(t('Facultad de medicina – Pregrado'))
      ->setRequired(TRUE);

    $fields['pais_pregrado'] = BaseFieldDefinition::create('string')
      ->setLabel(t('País pregrado'))
      ->setRequired(TRUE)
      ->setSetting('max_length', 100);

    $fields['titulo_universitario'] = BaseFieldDefinition::create('string_long')
      ->setLabel(t('Título universitario'))
      ->setRequired(TRUE);

    $fields['universidad_residencia'] = BaseFieldDefinition::create('string_long')
      ->setLabel(t('Universidad de residencia'))
      ->setRequired(TRUE);

    $fields['pais_residencia'] = BaseFieldDefinition::create('string')
      ->setLabel(t('País residencia'))
      ->setRequired(TRUE)
      ->setSetting('max_length', 100);

    $fields['recertificacion_camec'] = BaseFieldDefinition::create('boolean')
      ->setLabel(t('Desea participar en CAMEC'))
      ->setRequired(TRUE)
      ->setDefaultValue(FALSE);

    $fields['tiene_subespecialidad'] = BaseFieldDefinition::create('boolean')
      ->setLabel(t('Tiene subespecialidad'))
      ->setRequired(TRUE)
      ->setDefaultValue(FALSE);

    $fields['subespecialidad_cual'] = BaseFieldDefinition::create('string_long')
      ->setLabel(t('Subespecialidad - cuál'))
      ->setRequired(FALSE);

    // =======================
    // Paso 4: Adjuntos (managed_file)
    // =======================
    $file_settings = [
      'uri_scheme' => 'private',
      'file_extensions' => 'pdf jpg jpeg png',
      'max_filesize' => '10 MB',
    ];

    $fields['adj_carta_1'] = BaseFieldDefinition::create('file')->setLabel(t('Carta 1 de presentación'))->setRequired(TRUE)->setSettings($file_settings);
    $fields['adj_carta_2'] = BaseFieldDefinition::create('file')->setLabel(t('Carta 2 de presentación'))->setRequired(TRUE)->setSettings($file_settings);
    $fields['adj_rut'] = BaseFieldDefinition::create('file')->setLabel(t('RUT'))->setRequired(TRUE)->setSettings($file_settings);
    $fields['adj_id'] = BaseFieldDefinition::create('file')->setLabel(t('Cédula / ID'))->setRequired(TRUE)->setSettings($file_settings);
    $fields['adj_carta_ingreso'] = BaseFieldDefinition::create('file')->setLabel(t('Carta solicitud de ingreso'))->setRequired(TRUE)->setSettings($file_settings);
    $fields['adj_hv'] = BaseFieldDefinition::create('file')->setLabel(t('Hoja de vida'))->setRequired(TRUE)->setSettings($file_settings);
    $fields['adj_diploma_medico'] = BaseFieldDefinition::create('file')->setLabel(t('Diploma médico general'))->setRequired(TRUE)->setSettings($file_settings);
    $fields['adj_diploma_dermatologo'] = BaseFieldDefinition::create('file')->setLabel(t('Diploma dermatólogo'))->setRequired(TRUE)->setSettings($file_settings);
    $fields['adj_rethus'] = BaseFieldDefinition::create('file')->setLabel(t('RETHUS'))->setRequired(TRUE)->setSettings($file_settings);
    $fields['adj_aut_verificacion'] = BaseFieldDefinition::create('file')->setLabel(t('Autorización verificación títulos'))->setRequired(TRUE)->setSettings($file_settings);
    $fields['adj_cert_publicacion'] = BaseFieldDefinition::create('file')->setLabel(t('Certificación publicación/conferencia'))->setRequired(TRUE)->setSettings($file_settings);

    // =======================
    // Términos + motivo SG
    // =======================
    $fields['terms_accepted'] = BaseFieldDefinition::create('boolean')
      ->setLabel(t('Acepta términos'))
      ->setRequired(TRUE)
      ->setDefaultValue(FALSE);

    $fields['sg_reason'] = BaseFieldDefinition::create('string_long')
      ->setLabel(t('Motivo SG'))
      ->setRequired(FALSE);

    return $fields;
  }
}
