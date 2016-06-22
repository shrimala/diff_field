<?php
/**
 * @file
 * Contains \Drupal\diff_field\Plugin\Field\FieldFormatter\diff1Formatter.
 */
 
namespace Drupal\diff_field\Plugin\Field\FieldFormatter;
 
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Form\FormStateInterface;
/**
 * Plugin implementation of the 'diff' formatter.
 *
 * @FieldFormatter (
 *   id = "diff",
 *   label = @Translation("Diff"),
 *   field_types = {
 *     "field_difffield"
 *   }
 * )
 */
class diffFormatter extends FormatterBase {
  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode = NULL) {
    $elements = array();
//=======================This part have to change============================================ 
    foreach ($items as $delta => $item) {
      if ($item->before_rid == 1) {
        // If we are using a 1-sided die (occasionally sees use), just write "1"
        // instead of "1d1" which looks silly.
        $markup = $item->before_rid . $item->after_rid;
      }
      else {
        $markup = $item->before_rid . 'd' . $item->after_rid;
      }
 
      $elements[$delta] = array(
        '#type' => 'markup',
        '#markup' => $markup,
      );
    }
 //=============================================================================
    return $elements;
  }
   
   /**
   * {@inheritdoc}
   */
   /**
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $image_styles = 'CommentField';
    $element['image_style'] = array(
      '#title' => t('Comment Field'),
      '#type' => 'select',
      '#default_value' => $this->getSetting('image_style'),
      '#empty_option' => t('None (original image)'),
      '#options' => $image_styles,
    );
    return $element;
  }
  * /
  /**
   * {@inheritdoc}
   */
   /**
  public function settingsSummary() {
    // Only show a summary if we're using a non-standard pager id.
    if ($this->getSetting('after_rid')) {
      return array($this->t('After ID: @id', array(
        '@id' => $this->getSetting('after_rid'),
      )));
    }
    return array();
  }
  */
}
