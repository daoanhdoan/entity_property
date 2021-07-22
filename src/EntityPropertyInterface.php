<?php
namespace Drupal\entity_property;
interface EntityPropertyInterface {
  public function buildFieldDefinition($name, $entity_type_id, $property);
  public function updateFieldDefinitions($entity_type_id);
  public function rebuildEntityType($entity_type_id);
  public function hasData($entity_type_id, $field);
  public function getFieldTypeOptions();
  public function getAllSelection($target_type);
}
