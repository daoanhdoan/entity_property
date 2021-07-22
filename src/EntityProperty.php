<?php
/**
 * @file
 */
namespace Drupal\entity_property;

use Drupal\Component\Utility\Html;
use Drupal\Core\Entity\EntityDefinitionUpdateManagerInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldTypePluginManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class EntityProperty implements EntityPropertyInterface {
  /**
   * @var EntityFieldManagerInterface
   */
  protected $entityFieldManager;
  /**
   * @var EntityTypeManagerInterface
   */
  protected $entityTypeManager;
  /**
   * @var FieldTypePluginManagerInterface
   */
  protected $fieldTypePluginManager;
  /**
   * @var EntityDefinitionUpdateManagerInterface
   */
  protected $entityDefinitionUpdateManager;

  /**
   *
   * @param EntityTypeManagerInterface $entity_type_manager
   * @param EntityFieldManagerInterface $entity_field_manager
   * @param FieldTypePluginManagerInterface $field_type_plugin_manager
   * @param EntityDefinitionUpdateManagerInterface $entity_definition_update_manager
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, EntityFieldManagerInterface $entity_field_manager, FieldTypePluginManagerInterface $field_type_plugin_manager, EntityDefinitionUpdateManagerInterface $entity_definition_update_manager)
  {
    $this->entityFieldManager = $entity_field_manager;
    $this->entityTypeManager = $entity_type_manager;
    $this->fieldTypePluginManager = $field_type_plugin_manager;
    $this->entityDefinitionUpdateManager = $entity_definition_update_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container)
  {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('entity_field.manager'),
      $container->get('plugin.manager.field.field_type'),
      $container->get('entity.definition_update_manager')
    );
  }
  public function buildFieldDefinition($name, $entity_type_id, $property) {
    /** @var FieldDefinitionInterface[] $fields */
    $field = BaseFieldDefinition::create($property['type'])
      ->setName($name)
      ->setLabel($property['label'])
      ->setTargetEntityTypeId($entity_type_id)
      ->setRevisionable(TRUE);
    if (!empty($property['settings'])) {
      $field->setSettings($property['settings']);
    }
    if (!empty($property['required'])) {
      $field->setRequired($property['required']);
    }
    foreach($property['configurable'] as $key => $value) {
      $field->setDisplayConfigurable($key, !empty($value));
    }
    $field->setDisplayOptions('form', ['region' => 'content']);
    $field->setDisplayOptions('view', ['region' => 'content']);
    return $field;
  }

  /**
   *
   */
  public function updateFieldDefinitions($entity_type_id) {
    $entity_type = $this->entityDefinitionUpdateManager->getEntityType($entity_type_id);
    $config = \Drupal::configFactory()->getEditable('entity_property.properties.' . $entity_type_id);
    if ($properties = $config->getRawData()) {
      foreach ($properties as $id => $property) {
        if (!$this->entityDefinitionUpdateManager->getFieldStorageDefinition($id, $entity_type_id)) {
          /** @var FieldDefinitionInterface $field */
          $field = $this->buildFieldDefinition($id, $entity_type_id, $property);
          $this->entityDefinitionUpdateManager->installFieldStorageDefinition($id, $entity_type_id, 'entity_property', $field);
        }
      }
    }
    $this->entityDefinitionUpdateManager->updateEntityType($entity_type);
  }
  /*
   *
   */
  public function rebuildEntityType($entity_type_id) {
    $this->entityFieldManager->clearCachedFieldDefinitions();
    if ($this->entityTypeManager->hasHandler($entity_type_id, 'view_builder')) {
      $this->entityTypeManager->getViewBuilder($entity_type_id)->resetCache();
    }
    $this->entityTypeManager->clearCachedDefinitions();
    $this->updateFieldDefinitions($entity_type_id);
  }

  /**
   *
   */
  public function hasData($entity_type_id, $field) {
    /** @var \Drupal\Core\Entity\Sql\SqlContentEntityStorage $fieldStorage */
    $fieldStorage = $this->entityTypeManager->getStorage($entity_type_id);

    /** @var \Drupal\Core\Entity\EntityFieldManagerInterface $efm */
    $definitions = $this->entityFieldManager->getBaseFieldDefinitions($entity_type_id);
    if (isset($definitions[$field]) && $fieldStorage->countFieldData($definitions[$field], TRUE)){
      return TRUE;
    }
    return FALSE;
  }

  /**
   *
   */
  public function getFieldTypeOptions() {
    $default_types = [];
    $default_types += \Drupal::config('entity_property.settings')->get('field_types');
    $field_type_options = [];
    foreach ($this->fieldTypePluginManager->getGroupedDefinitions($this->fieldTypePluginManager->getUiDefinitions()) as $category => $field_types) {
      foreach ($field_types as $name => $field_type) {
        $name = $field_type['id'];
        if (in_array($name, $default_types)) {
          $field_type_options[$category][$name] = $field_type['label'];
        }
      }
    }
    $field_type_options['Reference']['entity_reference'] = t('Entity Reference');
    return $field_type_options;
  }

  /**
   *
   */
  public function getAllSelection($target_type) {
    // Get all selection plugins for this entity type.
    $selection_plugins = \Drupal::service('plugin.manager.entity_reference_selection')->getSelectionGroups($target_type);
    $handlers_options = [];
    foreach (array_keys($selection_plugins) as $selection_group_id) {
      // We only display base plugins (e.g. 'default', 'views', ...) and not
      // entity type specific plugins (e.g. 'default:node', 'default:user',
      // ...).
      if (array_key_exists($selection_group_id, $selection_plugins[$selection_group_id])) {
        $handlers_options[$selection_group_id] = Html::escape($selection_plugins[$selection_group_id][$selection_group_id]['label']);
      }
      elseif (array_key_exists($selection_group_id . ':' . $target_type, $selection_plugins[$selection_group_id])) {
        $selection_group_plugin = $selection_group_id . ':' . $target_type;
        $handlers_options[$selection_group_plugin] = Html::escape($selection_plugins[$selection_group_id][$selection_group_plugin]['base_plugin_label']);
      }
    }
    return $handlers_options;
  }
}
