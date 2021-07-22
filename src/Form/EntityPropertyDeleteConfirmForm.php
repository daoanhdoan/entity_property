<?php

namespace Drupal\entity_property\Form;

use Drupal\Core\Entity\EntityDeleteForm;
use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\ConfirmFormHelper;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\eck\EckEntityTypeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Provides a form for ECK entity bundle deletion.
 *
 * @ingroup eck
 */
class EntityPropertyDeleteConfirmForm extends ConfirmFormBase {
  /** @var EckEntityTypeInterface $entity */
  protected $entity;
  /**
   * @var array|null
   */
  protected $property;

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return $this->t('Are you sure you want to delete the property %label(%name)?', ['%label' => $this->property['label'], '%name' => $this->property['name']]);
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    return new Url('entity_property.entity_property', ['entity_type' => $this->entity->id()]);
  }

  /**
   * {@inheritdoc}
   */
  public function getConfirmText() {
    return $this->t('Delete');
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $entity_type = NULL, $property_name = NULL) {
    $this->entity = \Drupal::entityTypeManager()->getDefinition($entity_type);
    $config = \Drupal::config('entity_property.properties.' . $this->entity->id());
    $this->property = NULL;
    if ($property_name) {
      $this->property = $config->get($property_name);
    }
    if ($this->property) {
      $form['#title'] = $this->getQuestion();

      $form['#attributes']['class'][] = 'confirmation';
      $form['description'] = ['#markup' => $this->getDescription()];
      $form[$this->getFormName()] = ['#type' => 'hidden', '#value' => 1];

      $form['actions'] = ['#type' => 'actions'];
      $form['actions']['submit'] = [
        '#type' => 'submit',
        '#value' => $this->getConfirmText(),
        '#button_type' => 'primary',
      ];

      // By default, render the form using theme_confirm_form().
      if (!isset($form['#theme'])) {
        $form['#theme'] = 'confirm_form';
      }
    }
    else {
      $form['#title'] = $this->t('The property %name not found', ['%name' => $property_name]);
    }
    $form['actions']['cancel'] = ConfirmFormHelper::buildCancelLink($this, $this->getRequest());
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = \Drupal::configFactory()->getEditable('entity_property.properties.' . $this->entity->id());
    $t_args = ['%label' => $this->property['label'], '%name' => $this->property['name']];
    \Drupal::messenger()->addMessage($this->t('The property %label(%name) has been deleted', $t_args));
    $config->clear($this->property['name']);
    $config->save();
    $fieldDefinition = \Drupal::service('entity_property')->buildFieldDefinition($this->property['name'], $this->entity->id(), $this->property);
    \Drupal::entityDefinitionUpdateManager()->uninstallFieldStorageDefinition($fieldDefinition);
    \Drupal::service('entity_property')->rebuildEntityType($this->entity->id());
    $form_state->setRedirectUrl($this->getCancelUrl());
  }

  /**
   * @inheritDoc
   */
  public function getFormId()
  {
    return 'entity_property_delete_confirm';
  }
}
