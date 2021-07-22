<?php

namespace Drupal\entity_property\Plugin\Field\FieldWidget;

use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Datetime\Element\Datetime;
use Drupal\Core\Datetime\Entity\DateFormat;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItem;

/**
 * Plugin implementation of the 'timestamp' widget.
 *
 * @FieldWidget(
 *   id = "timestamp",
 *   label = @Translation("Timestamp"),
 *   field_types = {
 *     "timestamp",
 *     "created",
 *   }
 * )
 */
class TimestampWidget extends WidgetBase {
  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return [
        'datetime_show' => 'datetime',
      ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $element['datetime_show'] = [
      '#type' => 'select',
      '#title' => t('Datetime show'),
      '#description' => t('Choose the widget of datetime to show.'),
      '#default_value' => $this->getSetting('datetime_show'),
      '#options' => [
        DateTimeItem::DATETIME_TYPE_DATETIME => t('Date and time'),
        DateTimeItem::DATETIME_TYPE_DATE => t('Date only'),
      ]
    ];
    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $summary = [];

    $show = $this->getSetting('datetime_show');
    $summary[] = t('Show: @show', ['@show' => $show === DateTimeItem::DATETIME_TYPE_DATETIME ? t('Date and time') : t('Date only')]);

    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $default_value = isset($items[$delta]->value) ? DrupalDateTime::createFromTimestamp($items[$delta]->value) : '';
    switch ($this->getSetting('datetime_show')) {
      case DateTimeItem::DATETIME_TYPE_DATE:
        $date_type = 'date';
        $time_type = 'none';
        $date_format = DateFormat::load('html_date')->getPattern();
        $time_format = '';
        break;

      default:
        $date_type = 'date';
        $time_type = 'time';
        $date_format = DateFormat::load('html_date')->getPattern();
        $time_format = DateFormat::load('html_time')->getPattern();
        break;
    }

    $element['value'] = $element + [
        '#type' => 'datetime',
        '#default_value' => $default_value,
        '#date_year_range' => '1902:2037',
        '#date_date_format' => $date_format,
        '#date_date_element' => $date_type,
        '#date_date_callbacks' => [],
        '#date_time_format' => $time_format,
        '#date_time_element' => $time_type,
        '#date_time_callbacks' => [],
      ];

    $element['value']['#description'] = $this->t('Format: %format. Leave blank if you don\'t want to set any value.', ['%format' => Datetime::formatExample($date_format . ' ' . $time_format)]);

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function massageFormValues(array $values, array $form, FormStateInterface $form_state) {
    $date = NULL;
    foreach ($values as &$item) {
      // @todo The structure is different whether access is denied or not, to
      //   be fixed in https://www.drupal.org/node/2326533.
      if (isset($item['value']) && $item['value'] instanceof DrupalDateTime) {
        $date = $item['value'];
      }
      elseif (isset($item['value']['object']) && $item['value']['object'] instanceof DrupalDateTime) {
        $date = $item['value']['object'];
      }
      $item['value'] = $date instanceof DrupalDateTime ? $date->getTimestamp() : $date;
    }
    return $values;
  }

}
