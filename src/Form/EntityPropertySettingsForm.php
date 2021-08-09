<?php
namespace Drupal\entity_property\Form;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * @file
 * JQuery Autosize settings form include file.
 */
class EntityPropertySettingsForm extends ConfigFormBase
{
  /**
   * Admin settings menu callback.
   *
   * @see jquery_entity_property_menu()
   */
  public function buildForm(array $form, FormStateInterface $form_state)
  {
    $config = $this->config('entity_property.settings');
    $field_type_options = [];
    $fieldTypePluginManager = \Drupal::service('plugin.manager.field.field_type');
    foreach ($fieldTypePluginManager->getDefinitions() as $name => $field_type) {
      $name = $field_type['id'];
      if (!isset($field_type_options[$name])) {
        $field_type_options[$name] = $field_type['label'];
      }
    }

    $form['show_all_properties'] = array (
      '#type' => 'checkbox',
      '#title' => t('Show programing properties'),
      '#default_value' => $config->get('show_all_properties'),
    );

    $form['field_types'] = [
      '#type' => 'details',
      '#title' => t('Field types'),
      '#open' => TRUE,
    ];

    $form['field_types']['field_types'] = array (
      '#type' => 'checkboxes',
      '#title' => t('Field types'),
      '#options' => $field_type_options,
      '#default_value' => $config->get('field_types'),
      '#required' => TRUE,
    );

    return parent::buildForm($form, $form_state);
  }
  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config('entity_property.settings');
    $config->set('show_all_properties', $form_state->getValue('show_all_properties'));
    $config->set('entity_types', $form_state->getValue('entity_types'));
    $config->set('field_types', $form_state->getValue('field_types'));
    $config->save();
    $this->messenger()->addStatus($this->t('The Autosize settings have been saved.'));
  }

  /**
   * @inheritDoc
   */
  protected function getEditableConfigNames()
  {
    return ['entity_property.settings'];
  }

  /**
   * @inheritDoc
   */
  public function getFormId()
  {
    return  "entity_property_settings";
  }
}
