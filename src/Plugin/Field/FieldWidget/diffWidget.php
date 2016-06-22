<?php
/**
 * @file
 * Contains \Drupal\diff_field\Plugin\Field\FieldWidget\diffWidget.
 */
 
namespace Drupal\diff_field\Plugin\Field\FieldWidget;
 
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;
 
/**
 * Plugin implementation of the 'dice' widget.
 *
 * @FieldWidget (
 *   id = "widget_difffield",
 *   label = @Translation("Diff widget"),
 *   field_types = {
 *     "field_difffield"
 *   }
 * )
 */
class diffWidget extends WidgetBase {
  /**
   * {@inheritdoc}
   */
  //public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, $form_state) {
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {	  
    $element['before_rid'] = array(
      '#type' => 'number',
      '#title' => t('Before RID'),
      '#default_value' => '123',
      '#size' => 10,
    );
    $element['after_rid'] = array(
      '#type' => 'number',
      '#title' => t('After RID'),
      '#default_value' => '321',
      '#size' => 10,
    );
 
    // If cardinality is 1, ensure a label is output for the field by wrapping
    // it in a details element.
    if ($this->fieldDefinition->getFieldStorageDefinition()->getCardinality() == 1) {
      $element += array(
        '#type' => 'fieldset',
        '#attributes' => array('class' => array('container-inline')),
      );
    }
 
    return $element;
  }
}
