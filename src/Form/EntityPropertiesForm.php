<?php

namespace Drupal\entity_property\Form;

use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\NestedArray;
use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityType;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\EntityTypeRepository;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Field\FieldTypePluginManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\entity\EckEntityTypeInterface;
use Drupal\entity_property\EntityPropertyInterface;
use Drupal\field_ui\FieldUI;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class EckPropertyForm.
 */
class EntityPropertiesForm extends FormBase {

  /**
   * The entity storage class.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $entityTypeManager;

  /**
   * The entity field manager service.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $entityFieldManager;
  /**
   * @var FieldTypePluginManagerInterface
   */
  protected $fieldTypePluginManager;

  protected $entity_type;
  /**
   * @var EntityPropertyInterface
   */
  protected $entityProperty;

  /**
   * Construct the EckEntityTypeFormBase.
   *
   * @param EntityTypeManagerInterface $entity_type_manager
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entity_field_manager
   *   The entity field manager service.
   * @param FieldTypePluginManagerInterface $field_type_plugin_manager
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, EntityFieldManagerInterface $entity_field_manager, FieldTypePluginManagerInterface $field_type_plugin_manager, EntityPropertyInterface $entity_property) {
    $this->entityTypeManager = $entity_type_manager;
    $this->entityFieldManager = $entity_field_manager;
    $this->fieldTypePluginManager = $field_type_plugin_manager;
    $this->entityProperty = $entity_property;
  }

  /**
   * Factory method for EckEntityTypeFormBase.
   *
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('entity_field.manager'),
      $container->get('plugin.manager.field.field_type'),
      $container->get('entity_property')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $entity_type = NULL) {
    if($entity_type) {
      $entity_type = $this->entityTypeManager->getDefinition($entity_type);
    }
    if ($entity_type) {
      $this->entity_type = $entity_type;
    }

    $values = !empty($form_state->getValues()) ? $form_state->getValues() : $form_state->getUserInput();

    $field_type_options = $this->entityProperty->getFieldTypeOptions();

    if (!$form_state->get('items_count')) {
      $form_state->set('items_count', 1);
    }

    $form['properties'] = [
      '#type' => 'table',
      '#header' => ['type' => 'Type', 'label' => 'Label', "",'required' => 'Required', 'configurable' => 'Configurable', 'settings' => 'Settings'],
      '#attributes' => [
        'id' => 'entity-properties',
        'class' => ['clearfix']
      ],
      '#tree' => TRUE
    ];

    for($i = 0; $i < $form_state->get('items_count'); $i++) {
      $element = [];
      $wrapper = "entity-properties-form-settings-{$i}-wrapper";
      $state = [
        '!visible' => [
          ':input[name="properties[' . $i . '][type]"]' => ['value' => '']
        ]
      ];
      $value = NestedArray::getValue($values, ['properties', $i]);
      $element['type'] = [
        '#type' => 'select',
        '#title' => $this->t('Type'),
        '#title_display' => 'invisible',
        '#options' => $field_type_options,
        '#default_value' => !empty($value['type']) ? $value['type'] : "",
        '#empty_option' => $this->t('- Select a field type -'),
        '#ajax' => [
          'callback' => '\Drupal\entity_property\Form\EntityPropertiesForm::ajaxUpdate',
          'wrapper' => $wrapper,
          'event' => 'change'
        ]
      ];

      // Build the form.
      $element['label'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Label'),
        '#title_display' => 'invisible',
        '#maxlength' => 255,
        '#default_value' => !empty($value['label']) ? $value['label'] : "",
        '#states' => $state
      ];

      $element['name'] = [
        '#type' => 'machine_name',
        '#title' => $this->t('Machine name'),
        '#title_display' => 'invisible',
        '#maxlength' => 32,
        '#default_value' => !empty($value['name']) ? $value['name'] : "",
        '#machine_name' => [
          'source' => ['properties', $i, 'label'],
          'exists' => [$this, 'exists'],
          'replace_pattern' => '([^a-z0-9_]+)|(^custom$)',
        ],
        '#states' => $state,
        '#required' => FALSE,
      ];

      $element['required'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Required field'),
        '#title_display' => 'invisible',
        '#default_value' => !empty($value['required']) ? TRUE : FALSE,
        '#states' => $state
      ];
      $element['configurable'] = [
        '#type' => 'checkboxes',
        '#title' => $this->t('Display configurable'),
        '#title_display' => 'invisible',
        '#options' => ['form' => t('Form'), 'view' => t('View')],
        '#default_value' => !empty($value['configurable']) ? $value['configurable'] : [],
        '#states' => $state
      ];

      $element['settings'] = [
        '#type' => 'container',
        '#title' => $this->t('Settings'),
        '#title_display' => 'invisible',
        '#tree' => TRUE,
        '#states' => $state,
        '#prefix' => '<div id="' . $wrapper . '">',
        '#suffix' => '</div>',
      ];

      if (!empty($value['type'])) {
        $element['settings']['#type'] = 'fieldset';
        $type = $value['type'];
        $field_name = $value['name'];
        $label = $value['label'];

        $field_definition = BaseFieldDefinition::create($type)
          ->setName($field_name)
          ->setLabel($label)
          ->setTargetEntityTypeId($entity_type->id());
        if (!empty($value['settings'])) {
          $field_definition->setSettings($value['settings']);
        }
        if ($type == 'entity_reference') {
          $target_type = !empty($value['settings']['target_type']) ? $value['settings']['target_type'] : "node";
          $field_definition->setSetting('target_type', $target_type);
          $handler = !empty($value['settings']['handler']) ? $value['settings']['handler'] : "default:{$target_type}";
          $field_definition->setSetting('handler', $handler);
        }

        $configuration = [
          'field_definition' => $field_definition,
          'name' => $field_name,
          'parent' => NULL,
        ];
        /** @var \Drupal\Core\Field\FieldItemInterface $instance */
        $instance = $this->fieldTypePluginManager->createInstance($type, $configuration);

        if ($type == 'entity_reference') {
          $element['settings']['target_type'] = [
            '#type' => 'select',
            '#title' => t('Type of item to reference'),
            '#options' => \Drupal::service('entity_type.repository')->getEntityTypeLabels(TRUE),
            '#default_value' => $field_definition->getSetting('target_type'),
            '#required' => TRUE,
            '#size' => 1,
            '#ajax' => [
              'callback' => '\Drupal\entity_property\Form\EntityPropertiesForm::ajaxUpdate',
              'wrapper' => $wrapper,
              'event' => 'change'
            ]
          ];

          $element['settings']['handler'] = [
            '#type' => 'select',
            '#title' => t('Reference method'),
            '#options' => $this->entityProperty->getAllSelection($field_definition->getSetting('target_type')),
            '#default_value' => $field_definition->getSetting('handler'),
            '#required' => TRUE,
            '#limit_validation_errors' => [],
            '#ajax' => [
              'callback' => '\Drupal\entity_property\Form\EntityPropertiesForm::ajaxUpdate',
              'wrapper' => $wrapper,
              'event' => 'change'
            ]
          ];

          $element['settings']['handler_settings'] = [
            '#type' => 'container',
            '#tree' => TRUE,
            '#attributes' => ['class' => ['entity_reference-settings']],
          ];

          $handler = \Drupal::service('plugin.manager.entity_reference_selection')->getSelectionHandler($field_definition);
          $element['settings']['handler_settings'] += $handler->buildConfigurationForm([], $form_state);
        } else {
          $element['settings'] += $instance->storageSettingsForm($element, $form_state, FALSE);
          if (is_callable([$instance, "fieldSettingsForm"])) {
            $form_settings = call_user_func_array([$instance, "fieldSettingsForm"], [$element, $form_state]);
            if ($form_settings) {
              $element['settings'] += $form_settings;
            }
          }
        }
      }
      $form['properties'][$i] = $element;
    }

    $form['actions'] = [
      '#type' => 'actions',
    ];
    $form['actions']['add'] = array(
      '#type' => 'submit',
      '#ajax' => array(
        'wrapper' => 'entity-properties',
        'callback' => [$this, 'ajaxCallback'],
      ),
      '#submit' => [[$this, 'addItemSubmit']],
      '#value' => t('Add'),
    );
    $form['actions']['delete'] = array(
      '#type' => 'submit',
      '#ajax' => array(
        'wrapper' => 'entity-properties',
        'callback' => [$this, 'ajaxCallback'],
      ),
      '#submit' => [[$this, 'deleteItemSubmit']],
      '#value' => t('Delete'),
    );
    $form['actions']['submit'] = array(
      '#type' => 'submit',
      '#value' => t('Save'),
      '#submit' => [[$this, 'submitForm']],
    );

    return $form;
  }

  /**
   *
   */
  public function ajaxCallback($form, FormStateInterface &$form_state) {
    return $form['properties'];
  }
  /**
   *
   */
  public function addItemSubmit($form, FormStateInterface &$form_state) {
    $form_state->set('items_count', $form_state->get('items_count')+1);
    $form_state->setRebuild();
  }

  /**
   *
   */
  public function deleteItemSubmit($form, FormStateInterface &$form_state) {
    if ($form_state->get('items_count') > 1) {
      $form_state->set('items_count', $form_state->get('items_count')-1);
      $form_state->setRebuild();
    }
  }

  /**
   * Update the dependent field options.
   *
   * @param array $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return mixed
   *   The updated field.
   */
  public static function ajaxUpdate(array $form, FormStateInterface &$form_state)
  {
    $trigger = $form_state->getTriggeringElement();
    $parents = array_slice($trigger['#array_parents'], 0, 2);
    $parents = array_merge($parents, ['settings']);
    $element = NestedArray::getValue($form, $parents);
    if ($element) {
      return $element;
    }
    //return $form['properties'];
  }

  /**
   * Chentitys for an existing ECK entity type.
   *
   * @param string|int $entity_id
   *   The entity ID.
   * @param array $element
   *   The form element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return bool
   *   TRUE if this format already exists, FALSE otherwise.
   */
  public function exists($name, array $element, FormStateInterface $form_state) {
    $entity = $this->entity_type;
    $config = \Drupal::config('entity_property.properties.' . $entity->id());
    $result = $config->get($name);
    return (bool) $result;
  }

  /**
   * @inheritDoc
   */
  public function getFormId()
  {
    return "entity_properties_form";
  }

  /**
   * @inheritDoc
   */
  public function submitForm(array &$form, FormStateInterface $form_state)
  {
    $form_state->cleanValues();
    $config = \Drupal::configFactory()->getEditable('entity_property.properties.' . $this->entity_type->id());
    $properties = $form_state->getValue('properties');
    foreach ($properties as $i => $item) {
      if (!empty($item['type']) && !empty($item['label']) && !empty($item['name'])) {
        $config->set($item['name'], $item);
        $config->save();
      }
      $messageArgs = ['@label' => $item['label']];
      $message[] = $this->t('Property @label has been added.', $messageArgs);
    }

    $this->entityProperty->rebuildEntityType($this->entity_type->id());
    \Drupal::messenger()->addMessage(implode(" \n", $message));
    $destination = \Drupal::requestStack()->getCurrentRequest()->get('destination');
    if ($destination) {
      $options = UrlHelper::parse($destination);
      $next_destination = Url::fromUserInput($options['path'], $options);
      $form_state->setRedirectUrl($next_destination);
    }
    else {
      $form_state->setRedirect('entity_property.entity_property', ["entity_type" => $this->entity_type->id()]);
    }
  }
}
