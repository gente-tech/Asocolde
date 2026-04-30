<?php

namespace Drupal\asocolderma_data_core\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

class DataImportForm extends FormBase
{

	/**
	 * {@inheritdoc}
	 */
	public function getFormId(): string
	{
		return 'asocolderma_data_core_import_form';
	}

	/**
	 * {@inheritdoc}
	 */
	public function buildForm(array $form, FormStateInterface $form_state): array
	{
		$form['#attributes']['enctype'] = 'multipart/form-data';

		$form['import_type'] = [
			'#type' => 'select',
			'#title' => $this->t('Tabla a importar'),
			'#description' => $this->t('Seleccione el tipo de información que desea importar desde el Excel.'),
			'#required' => TRUE,
			'#empty_option' => $this->t('Seleccione una tabla'),
			'#options' => [
				'asocolderma_import_patrocinadores' => $this->t('Patrocinadores'),
				'asocolderma_import_asociados' => $this->t('Asociados'),
				'asocolderma_import_residentes' => $this->t('Residentes'),
				'asocolderma_import_proveedores' => $this->t('Proveedores'),
				'asocolderma_import_empleados' => $this->t('Empleados'),
			],
		];

		$form['excel_file'] = [
			'#type' => 'file',
			'#title' => $this->t('Archivo Excel'),
			'#description' => $this->t('Cargue un archivo .xlsx con la información a importar.'),
			'#required' => TRUE,
		];

		$form['actions'] = [
			'#type' => 'actions',
		];

		$form['actions']['submit'] = [
			'#type' => 'submit',
			'#value' => $this->t('Importar'),
			'#button_type' => 'primary',
		];

		return $form;
	}

	/**
	 * {@inheritdoc}
	 */
	public function validateForm(array &$form, FormStateInterface $form_state): void
	{
		$validators = [
			'file_validate_extensions' => ['xlsx'],
		];

		$directory = 'public://imports/data_core';
		\Drupal::service('file_system')->prepareDirectory(
			$directory,
			\Drupal\Core\File\FileSystemInterface::CREATE_DIRECTORY | \Drupal\Core\File\FileSystemInterface::MODIFY_PERMISSIONS
		);

		$file = file_save_upload('excel_file', $validators, $directory, 0);

		if (!$file) {
			$form_state->setErrorByName('excel_file', $this->t('Debe cargar un archivo Excel válido con extensión .xlsx.'));
			return;
		}

		$file->setPermanent();
		$file->save();

		$form_state->setValue('uploaded_file', $file);
	}

	/**
	 * {@inheritdoc}
	 */
	public function submitForm(array &$form, FormStateInterface $form_state): void
	{
		$this->messenger()->addStatus($this->t('Formulario listo. En el siguiente paso conectaremos la lectura del Excel e importación a la tabla seleccionada.'));
	}
}
