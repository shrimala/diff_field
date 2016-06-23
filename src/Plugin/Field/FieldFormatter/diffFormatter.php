<?php
/**
 * @file
 * Contains \Drupal\diff_field\Plugin\Field\FieldFormatter\diff1Formatter.
 */
 
namespace Drupal\diff_field\Plugin\Field\FieldFormatter;
 
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\diff\DiffEntityComparison;
use Drupal\node\NodeInterface;

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
   * The entity comparison service for diff.
   */
  protected $entityComparison;
  
  /**
   * Constructs a diffFormatter object.
   *
   * @param DiffEntityComparison $entityComparison
   *   The diff entity comparison service.
   */
  public function __construct($entityComparison) {
    $this->entityComparison = $entityComparison;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('diff.entity_comparison')
    );
  }
  
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
      
      $markup = compareNodeRevisions(ARIT_GET_THE_PARENT_NODE, $item->before_rid, $item->after_rid, 'raw')    
  
      $elements[$delta] = array(
        '#type' => 'markup',
        '#markup' => $markup,
      );
    }
 //=============================================================================
   
 
    return $elements;
  }
  
  
  private function compareNodeRevisions(NodeInterface $node, $left_vid, $right_vid, $filter) {
    $diff_rows = array();
    $build = array(
      '#title' => $this->t('Revisions for %title', array('%title' => $node->label())),
    );
    if (!in_array($filter, array('raw', 'raw-plain'))) {
      $filter = 'raw';
    }
    elseif ($filter == 'raw-plain') {
      $filter = 'raw_plain';
    }
    // Node storage service.
    $storage = $this->entityManager()->getStorage('node');
    $left_revision = $storage->loadRevision($left_vid);
    $right_revision = $storage->loadRevision($right_vid);
    $vids = $storage->revisionIds($node);
    $diff_rows[] = $this->buildRevisionsNavigation($node->id(), $vids, $left_vid, $right_vid);
    $diff_rows[] = $this->buildMarkdownNavigation($node->id(), $left_vid, $right_vid, $filter);
    $diff_header = $this->buildTableHeader($left_revision, $right_revision);

    // Perform comparison only if both node revisions loaded successfully.
    if ($left_revision != FALSE && $right_revision != FALSE) {
      $fields = $this->entityComparison->compareRevisions($left_entity, $right_entity); //MODIFIED FROM ORIGINAL TO USE SERVICE
      $node_base_fields = $this->entityManager()->getBaseFieldDefinitions('node');
      // Check to see if we need to display certain fields or not based on
      // selected view mode display settings.
      foreach ($fields as $field_name => $field) {
        // If we are dealing with nodes only compare those fields
        // set as visible from the selected view mode.
        $view_mode = $this->config->get('content_type_settings.' . $node->getType() . '.view_mode');
        // If no view mode is selected use the default view mode.
        if ($view_mode == NULL) {
          $view_mode = 'default';
        }
        $visible = entity_get_display('node', $node->getType(), $view_mode)->getComponent($field_name);
        if ($visible == NULL && !array_key_exists($field_name, $node_base_fields)) {
          unset($fields[$field_name]);
        }
      }
      // Build the diff rows for each field and append the field rows
      // to the table rows.
      foreach ($fields as $field) {
        $field_label_row = '';
        if (!empty($field['#name'])) {
          $field_label_row = array(
            'data' => $this->t('Changes to %name', array('%name' => $field['#name'])),
            'colspan' => 4,
            'class' => array('field-name'),
          );
        }
        $field_diff_rows = $this->getRows(
          $field['#states'][$filter]['#left'],
          $field['#states'][$filter]['#right']
        );

        // Add the field label to the table only if there are changes to that field.
        if (!empty($field_diff_rows) && !empty($field_label_row)) {
          $diff_rows[] = array($field_label_row);
        }

        // Add field diff rows to the table rows.
        $diff_rows = array_merge($diff_rows, $field_diff_rows);
      }

      // Add the CSS for the diff.
      $build['#attached']['library'][] = 'diff/diff.general';
      $theme = $this->config->get('general_settings.theme');
      if ($theme) {
        if ($theme == 'default') {
          $build['#attached']['library'][] = 'diff/diff.default';
        }
        elseif ($theme == 'github') {
          $build['#attached']['library'][] = 'diff/diff.github';
        }
      }
      // If the setting could not be loaded or is missing use the default theme.
      elseif ($theme == NULL) {
        $build['#attached']['library'][] = 'diff/diff.github';
      }

      $build['diff'] = array(
        '#type' => 'table',
        '#header' => $diff_header,
        '#rows' => $diff_rows,
        '#empty' => $this->t('No visible changes'),
        '#attributes' => array(
          'class' => array('diff'),
        ),
      );

      return $build;
    }
    else {
      // @todo When task 'Convert drupal_set_message() to a service' (2278383)
      //   will be merged use the corresponding service instead.
      drupal_set_message($this->t('Selected node revisions could not be loaded.'), 'error');
    }
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
