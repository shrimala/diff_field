<?php

/**
 * @file
 * Contains Drupal\diff_field\Plugin\Field\FieldType\diffItem.
 */

namespace Drupal\diff_field\Plugin\Field\FieldType;

use Drupal\Core\Field\FieldItemBase;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\TypedData\DataDefinition;

/**
 * Plugin implementation of the 'field_difffield' field type.
 *
 * @FieldType(
 *   id = "field_difffield",
 *   label = @Translation("Diff Field"),
 *   module = "diff_field",
 *   description = @Translation("Storing the revision ids of the node before and after edit"),
 *   default_formatter = "diff"
 * )
 */
class diffItem extends FieldItemBase {
 /**
   * {@inheritdoc}
   */
  public static function schema(FieldStorageDefinitionInterface $field_definition) {
    return array(
      'columns' => array(
        'before_rid' => array(
          'type' => 'int',
          'length' => '30',
        ),
        'after_rid' => array(
          'type' => 'int',
          'length' => '30',
        ),
      ),
    );
  }
 
  /**
   * {@inheritdoc}
  */
  public function isEmpty() {
    $value1 = $this->get('before_rid')->getValue();
    $value2 = $this->get('after_rid')->getValue();
    return empty($value1) &&empty($value2);
  }
 
  /**
   * {@inheritdoc}
   */
  public static function propertyDefinitions(FieldStorageDefinitionInterface $field_definition) {
    // Add our properties.
    $properties['before_rid'] = DataDefinition::create('integer')
      ->setLabel(t('Before_Rid'))
      ->setDescription(t('Revision id before edit'));
 
    $properties['after_rid'] = DataDefinition::create('integer')
      ->setLabel(t('After_Rid'))
      ->setDescription(t('Revision id aftre edit'));
      
    return $properties;
  }
}
