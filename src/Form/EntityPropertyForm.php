<?php

namespace Drupal\entity_property\Form;

use Drupal;
use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Field\FieldItemInterface;
use Drupal\Core\Field\FieldTypePluginManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\entity_property\EntityPropertyInterface;
use Drupal\field_ui\FieldUI;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class EntityPropertyForm.
 */
class EntityPropertyForm extends FormBase {

  /**
   * The entity storage class.
   *
   * @var EntityStorageInterface
   */
  protected $entityTypeManager;

  /**
   * The entity field manager service.
   *
   * @var EntityFieldManagerInterface
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
   * @param EntityTypeManagerInterface $entity_type_manager
   * @param EntityFieldManagerInterface $entity_field_manager
   * @param FieldTypePluginManagerInterface $field_type_plugin_manager
   * @param EntityPropertyInterface $entity_property
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
  public function buildForm(array $form, FormStateInterface $form_state, $entity_type = NULL, $property_name = NULL) {
    if($entity_type = \Drupal::entityTypeManager()->getDefinition($entity_type, FALSE)) {
      $this->entity_type = $entity_type;
      /** @var ImmutableConfig $config */
      $config = \Drupal::config('entity_property.properties.' . $this->entity_type->id());
    }
    else {
      \Drupal::messenger()->addError(t('The "@type" entity type does not exist.', ['@type' => $entity_type]));
      return $form;
    }

    $property = NULL;
    if ($property_name && !$form_state->getTriggeringElement()) {
      $property = $config->get($property_name);
      $form_state->setUserInput($property);
    }
    $values = !empty($form_state->getValues()) ? $form_state->getValues() : $form_state->getUserInput();

    $field_type_options = $this->entityProperty->getFieldTypeOptions();
    $wrapper_id = 'entity-property-form-settings-wrapper';

    $form['type'] = [
      '#type' => 'select',
      '#title' => $this->t('Type'),
      '#options' => $field_type_options,
      '#default_value' => !empty($values['type']) ? $values['type'] : "",
      '#empty_option' => $this->t('- Select a field type -'),
      '#required' => TRUE,
      '#ajax' => [
        'callback' => '\Drupal\entity_property\Form\EntityPropertyForm::ajaxUpdate',
        'wrapper' => $wrapper_id,
        'event' => 'change'
      ]
    ];

    // Build the form.
    $form['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Label'),
      '#maxlength' => 255,
      '#default_value' => !empty($values['label']) ? $values['label'] : "",
      '#required' => TRUE,
      '#states' => [
        '!visible' => [
          ':input[name="type"]' => ['value' => '']
        ]
      ]
    ];

    $form['name'] = [
      '#type' => 'machine_name',
      '#title' => $this->t('Machine name'),
      '#maxlength' => 32,
      '#default_value' => !empty($values['name']) ? $values['name'] : "",
      '#machine_name' => [
        'scource' => ['type'],
        'exists' => [$this, 'exists'],
        'replace_pattern' => '([^a-z0-9_]+)|(^custom$)',
        'error' => 'The machine-readable name must be unique, and can only contain lowercase letters, numbers, and underscores. Additionally, it can not be the reserved word "custom".',
      ],
      '#states' => [
        '!visible' => [
          ':input[name="type"]' => ['value' => '']
        ]
      ]
    ];

    $form['required'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Required field'),
      '#default_value' => !empty($values['required']) ? TRUE : FALSE,
      '#states' => [
        '!visible' => [
          ':input[name="type"]' => ['value' => '']
        ]
      ]
    ];
    $form['configurable'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Display configurable'),
      '#options' => ['form' => t('Form'), 'view' => t('View')],
      '#default_value' => !empty($values['configurable']) ? $values['configurable'] : [],
      '#states' => [
        '!visible' => [
          ':input[name="type"]' => ['value' => '']
        ]
      ]
    ];

    $form['settings'] = [
      '#type' => 'container',
      '#title' => $this->t('Settings'),
      '#tree' => TRUE,
      '#states' => [
        '!visible' => [
          ':input[name="type"]' => ['value' => '']
        ]
      ],
      '#prefix' => '<div id="' . $wrapper_id . '">',
      '#suffix' => '</div>',
    ];

    if ($form_state->getTriggeringElement() || !empty($property)) {
      $form['settings']['#type'] = 'fieldset';
      $type = $values['type'];
      $field_name = $values['name'];
      $label = $values['label'];

      $field_definition = BaseFieldDefinition::create($type)
        ->setName($field_name)
        ->setLabel($label)
        ->setTargetEntityTypeId($entity_type->id());
      if (!empty($property['settings'])) {
        $field_definition->setSettings($property['settings']);
      }
      if ($type == 'entity_reference') {
        $target_type = !empty($values['settings']['target_type']) ? $values['settings']['target_type'] : "node";
        $field_definition->setSetting('target_type', $target_type);
        $handler = !empty($values['settings']['handler']) ? $values['settings']['handler'] : "default:{$target_type}";
        $field_definition->setSetting('handler', $handler);
      }

      $configuration = [
        'field_definition' => $field_definition,
        'name' => $field_name,
        'parent' => NULL,
      ];
      /** @var FieldItemInterface $instance */
      $instance = $this->fieldTypePluginManager->createInstance($type, $configuration);

      if ($type == 'entity_reference') {
        $form['settings']['target_type'] = [
          '#type' => 'select',
          '#title' => t('Type of item to reference'),
          '#options' => Drupal::service('entity_type.repository')->getEntityTypeLabels(TRUE),
          '#default_value' => $field_definition->getSetting('target_type'),
          '#required' => TRUE,
          '#size' => 1,
          '#ajax' => [
            'callback' => '\Drupal\entity_property\Form\EntityPropertyForm::ajaxUpdate',
            'wrapper' => $wrapper_id,
            'event' => 'change'
          ]
        ];

        $form['settings']['handler'] = [
          '#type' => 'select',
          '#title' => t('Reference method'),
          '#options' => $this->entityProperty->getAllSelection($field_definition->getSetting('target_type')),
          '#default_value' => $field_definition->getSetting('handler'),
          '#required' => TRUE,
          '#limit_validation_errors' => [],
          '#ajax' => [
            'callback' => '\Drupal\entity_property\Form\EntityPropertyForm::ajaxUpdate',
            'wrapper' => $wrapper_id,
            'event' => 'change'
          ]
        ];

        $form['settings']['handler_settings'] = [
          '#type' => 'container',
          '#tree' => TRUE,
          '#attributes' => ['class' => ['entity_reference-settings']],
        ];

        $handler = Drupal::service('plugin.manager.entity_reference_selection')->getSelectionHandler($field_definition);
        $form['settings']['handler_settings'] += $handler->buildConfigurationForm([], $form_state);
      }
      else {
        $form['settings'] += $instance->storageSettingsForm($form, $form_state, FALSE);
        if (is_callable([$instance, "fieldSettingsForm"])) {
          $form_settings = call_user_func_array([$instance, "fieldSettingsForm"], [$form, $form_state]);
          if ($form_settings) {
            $form['settings'] += $form_settings;
          }
          elseif($form_settings === NULL) {
            $form['settings']['error'] = [
              '#markup' => 'Field type don\'t support use same property.'
            ];
          }
        }
      }
    }

    $form['actions'] = [
      '#type' => 'actions',
    ];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => t('Save')
    ];

    return $form;
  }

  /**
   * Update the dependent field options.
   *
   * @param array $form
   *   The form.
   * @param FormStateInterface $form_state
   *   The form state.
   *
   * @return mixed
   *   The updated field.
   */
  public static function ajaxUpdate(array $form, FormStateInterface &$form_state)
  {
    $form_state->setRebuild();
    return $form['settings'];
  }

  /**
   * Checks for an existing ECK entity type.
   *
   * @param string|int $entity_id
   *   The entity ID.
   * @param array $element
   *   The form element.
   * @param FormStateInterface $form_state
   *   The form state.
   *
   * @return bool
   *   TRUE if this format already exists, FALSE otherwise.
   */
  public function exists($name, array $element, FormStateInterface $form_state) {
    $entity = $this->entity_type;
    $config = Drupal::config('entity_property.properties.' . $entity->id());
    $result = $config->get($name);
    return (bool) $result;
  }

  /**
   * @inheritDoc
   */
  public function getFormId()
  {
    return "entity_property_form";
  }

  /**
   * @inheritDoc
   */
  public function submitForm(array &$form, FormStateInterface $form_state)
  {
    $form_state->cleanValues();
    $config = Drupal::configFactory()->getEditable('entity_property.properties.' . $this->entity_type->id());
    $name = $form_state->getValue('name');
    if ($name) {
      $values = $form_state->getValues();
      $config->set($name, $values);
      $config->save();
      $this->entityProperty->rebuildEntityType($this->entity_type->id());

      $messageArgs = ['%label' => $form_state->getValue('label')];
      $message = $this->t('Property %label has been added.', $messageArgs);
      Drupal::messenger()->addMessage($message);
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
}
