<?php

namespace Drupal\entity_property\Plugin\views\filter;

use Drupal\Core\Form\FormStateInterface;
use Drupal\views\FieldAPIHandlerTrait;
use Drupal\views\Plugin\views\display\DisplayPluginBase;
use Drupal\views\Plugin\views\filter\ManyToOne;
use Drupal\views\ViewExecutable;

/**
 * Filter handler which uses list-fields as options.
 *
 * @ingroup views_filter_handlers
 *
 * @ViewsFilter("entity_property_list_field")
 */
class EntityPropertyListField extends ManyToOne {

  use FieldAPIHandlerTrait;

  /**
   * {@inheritdoc}
   */
  public function init(ViewExecutable $view, DisplayPluginBase $display, array &$options = NULL) {
    $allowed_values = &drupal_static(__FUNCTION__, []);
    parent::init($view, $display, $options);
    $field_storage_definitions = $this->getEntityFieldManager()->getFieldStorageDefinitions($this->definition['entity_type']);
    $definition = $field_storage_definitions[$this->realField];
    $settings = $definition->getSettings();

    $cache_keys = [$definition->getTargetEntityTypeId(), $definition->getName()];
    $cache_id = implode(':', $cache_keys);

    if (!isset($allowed_values[$cache_id])) {
      $options = \Drupal::service('plugin.manager.entity_reference_selection')->getSelectionHandler($definition, NULL)->getReferenceableEntities();
      $target_type = $settings['target_type'];
      if (!empty($options[$target_type])) {
        $allowed_values[$cache_id] = $options[$target_type];
      }
    }

    $this->valueOptions = $allowed_values[$cache_id];
  }
}
