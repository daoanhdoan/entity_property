<?php

namespace Drupal\entity_property\Controller;

use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldTypePluginManagerInterface;
use Drupal\Core\Url;
use Drupal\entity_property\EntityPropertyInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

class EntityPropertyController extends ControllerBase implements ContainerInjectionInterface
{
  /**
   * @var EntityPropertyInterface
   */
  protected $entityProperty;
  /**
   * @var FieldTypePluginManagerInterface
   */
  protected $fieldTypePluginManager;

  /**
   *
   * @param EntityPropertyInterface $entityProperty
   */
  public function __construct(EntityPropertyInterface $entityProperty, FieldTypePluginManagerInterface $fieldTypePluginManager)
  {
    $this->entityProperty = $entityProperty;
    $this->fieldTypePluginManager = $fieldTypePluginManager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container)
  {
    return new static(
      $container->get('entity_property'),
      $container->get('plugin.manager.field.field_type')
    );
  }
  public function types(Request $request)
  {
    $entity_types = [];
    foreach ($this->entityTypeManager()->getDefinitions() as $entity_type_id => $entity_type) {
      if ($entity_type->get('field_ui_base_route') && $entity_type->hasFormClasses()) {
        $entity_types[$entity_type_id] = [
          'title' => $entity_type->getLabel(),
          'url' => Url::fromRoute('entity_property.entity_property', ['entity_type' => $entity_type_id]),
          'localized_options' => [],
        ];
      }
    }
    return [
      '#theme' => 'admin_block_content',
      '#content' => $entity_types,
    ];
  }

  /**
   * Returns an administrative overview of Imce Profiles.
   */
  public function properties($entity_type = NULL, Request $request)
  {
    $output['properties'] = [
      '#theme' => 'table',
      '#header' => ['Label', 'Name', 'Type', 'Display configurable', 'Operation(s)'],
      '#rows' => [],
    ];
    /** @var ImmutableConfig $config */
    $config = \Drupal::config('entity_property.settings');
    $properties = \Drupal::config('entity_property.properties.' . $entity_type);
    $rows = [];
    if ($config->get('show_all_properties')) {
      $entity_type_definition = $this->entityTypeManager()->getDefinition($entity_type);
      $class = $entity_type_definition->getClass();
      /** @var FieldDefinitionInterface[] $fieldDefinitions */
      $fieldDefinitions = $class::baseFieldDefinitions($entity_type_definition);
      foreach ($fieldDefinitions as $id => $field) {
        $row = [
          ['data' => $field->getLabel()],
          ['data' => $id]
        ];
        $field_type = $this->fieldTypePluginManager->getDefinition($field->getType());
        $row[] = ['data' => $field_type['label']];
        $row[] = ['data' => ""];
        $row[] = [
          'data' => []
        ];
        $rows[] = $row;
      }
    }
    foreach ($properties->getRawData() as $id => $property) {
      $row = [
        ['data' => $property['label']],
        ['data' => $property['name']]
      ];
      /** @var FieldDefinitionInterface $field_type */
      $field_type = $this->fieldTypePluginManager->getDefinition($property['type']);
      $row[] = ['data' => $field_type['label']];

      $configurable = array_map(function ($var) {
        return ucfirst($var);
      }, array_filter(array_values($property['configurable'])));
      $row[] = ['data' => implode(", ", $configurable)];

      $links = [];
      if (!$this->entityProperty->hasData($entity_type, $id)) {
        $link_args = ['entity_type' => $entity_type, 'property_name' => $property['name']];
        $links['edit'] = [
          'title' => $this->t('Edit'),
          'url' => Url::fromRoute('entity_property.entity_property_edit', $link_args),
        ];

        $links['delete'] = [
          'title' => $this->t('Delete'),
          'url' => Url::fromRoute('entity_property.entity_property_delete', $link_args),
        ];
      }

      $row[] = [
        'data' => [
          '#type' => 'operations',
          '#links' => $links,
        ],
      ];

      $rows[] = $row;
    }
    $output['properties']['#rows'] = $rows;
    return $output;
  }

  public function title($entity_type)
  {
    $entity = $this->entityTypeManager()->getDefinition($entity_type);
    return $this->t('Properties of %title', ['%title' => $entity->getLabel()]);
  }
}
