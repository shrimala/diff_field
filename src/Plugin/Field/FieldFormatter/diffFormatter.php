<?php
/**
 * @file
 * Contains \Drupal\diff_field\Plugin\Field\FieldFormatter\diff1Formatter.
 */
 
namespace Drupal\diff_field\Plugin\Field\FieldFormatter;
 
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;

use Drupal\Core\Url;
use Drupal\diff\EntityComparison;
use Drupal\Component\Utility\Xss;
use Drupal\Component\Utility\SafeMarkup;


use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\entity\Revision\EntityRevisionLogInterface;
use Symfony\Component\HttpFoundation\Request;

//use Drupal\diff_field\EntityComparisonBase;



use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Utility\LinkGeneratorInterface;



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
  public function __construct($plugin_id, $plugin_definition, FieldDefinitionInterface $field_definition, array $settings, $label, $view_mode, array $third_party_settings,$entityComparison) {
	  parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $label, $view_mode, $third_party_settings);
    $this->entitycomparison = $entityComparison;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container,array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $plugin_id,
      $plugin_definition,
      $configuration['field_definition'],
      $configuration['settings'],
      $configuration['label'],
      $configuration['view_mode'],
      $configuration['third_party_settings'],
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
      $ARIT_GET_THE_PARENT_NODE = \Drupal::routeMatch()->getParameter('node');
      //$markup = $this->compareNodeRevisions($ARIT_GET_THE_PARENT_NODE, $item->before_rid, $item->after_rid, 'raw'); 
      $markup = $this->entitycomparison->compareRevisions($item->before_rid, $item->after_rid);
      //$markup = $this->entityComparison->test('arit');
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
    $storage = \Drupal::entityManager()->getStorage('node');
    $left_revision = $storage->loadRevision($left_vid);
    $right_revision = $storage->loadRevision($right_vid);
    $vids = $storage->revisionIds($node);
    $diff_rows[] = $this->buildRevisionsNavigation($node->id(), $vids, $left_vid, $right_vid);  //checking
    $diff_rows[] = $this->buildMarkdownNavigation($node->id(), $left_vid, $right_vid, $filter);  //checking
    $diff_header = $this->buildTableHeader($left_revision, $right_revision);  //checking

    // Perform comparison only if both node revisions loaded successfully.
    if ($left_revision != FALSE && $right_revision != FALSE) {
     //$fields = $this->entityComparison->compareRevisions($left_entity, $right_entity); //MODIFIED FROM ORIGINAL TO USE SERVICE    //checking
     $fields = $this->compareRevisions($left_entity, $right_entity); //MODIFIED FROM ORIGINAL TO USE SERVICE    //checking
      $node_base_fields = \Drupal::entityManager()->getBaseFieldDefinitions('node');
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
      $theme = \Drupal::config()->get('general_settings.theme');
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
   /**
   * Returns the navigation row for diff table.
   */
  protected function buildRevisionsNavigation($nid, $vids, $left_vid, $right_vid) {
    $revisions_count = count($vids);
    $i = 0;

    $row = array();
    // Find the previous revision.
    while ($left_vid > $vids[$i]) {
      $i += 1;
    }
    if ($i != 0) {
      // Second column.
      $row[] = array(
        'data' => \Drupal::l(
          $this->t('< Previous difference'),
          Url::fromRoute('diff.revisions_diff',
          array(
            'node' => $nid,
            'left_vid' => $vids[$i - 1],
            'right_vid' => $left_vid,
          ))
        ),
        'colspan' => 2,
        'class' => 'rev-navigation',
      );
    }
    else {
      // Second column.
      $row[] = $this->nonBreakingSpace;
    }
    // Third column.
    $row[] = $this->nonBreakingSpace;
    // Find the next revision.
    $i = 0;
    while ($i < $revisions_count && $right_vid >= $vids[$i]) {
      $i += 1;
    }
    if ($revisions_count != $i && $vids[$i - 1] != $vids[$revisions_count - 1]) {
      // Forth column.
      $row[] = array(
        'data' => \Drupal::l(
          $this->t('Next difference >'),
          Url::fromRoute('diff.revisions_diff',
          array(
            'node' => $nid,
            'left_vid' => $right_vid,
            'right_vid' => $vids[$i],
          ))
        ),
        'colspan' => 2,
        'class' => 'rev-navigation',
      );
    }
    else {
      // Forth column.
      $row[] = $this->nonBreakingSpace;
    }

    // If there are only 2 revision return an empty row.
    if ($revisions_count == 2) {
      return array();
    }
    else {
      return $row;
    }
  }

  /**
   * Builds a table row with navigation between raw and raw-plain formats.
   */
  protected function buildMarkdownNavigation($nid, $left_vid, $right_vid, $active_filter) {

    $links['raw'] = array(
      'title' => $this->t('Standard'),
      'url' => Url::fromRoute('diff.revisions_diff', array(
        'node' => $nid,
        'left_vid' => $left_vid,
        'right_vid' => $right_vid,
      )),
    );
    $links['raw_plain'] = array(
      'title' => $this->t('Markdown'),
      'url' => Url::fromRoute('diff.revisions_diff', array(
        'node' => $nid,
        'left_vid' => $left_vid,
        'right_vid' => $right_vid,
        'filter' => 'raw-plain',
      )),
    );

    // Set as the first element the current filter.
    $filter = $links[$active_filter];
    unset($links[$active_filter]);
    array_unshift($links, $filter);

    $row[] = array(
      'data' => array(
        '#type' => 'operations',
        '#links' => $links,
      ),
      'colspan' => 4,
    );

    return $row;
  }

  /**
   * Build the header for the diff table.
   *
   * @param \Drupal\Core\Entity\EntityInterface $left_revision
   *   Revision from the left hand side.
   * @param \Drupal\Core\Entity\EntityInterface $right_revision
   *   Revision from the right hand side.
   *
   * @return array
   *   Header for Diff table.
   */
  protected function buildTableHeader(EntityInterface $left_revision, EntityInterface $right_revision) {
    $entity_type_id = $left_revision->getEntityTypeId();
    $revisions = array($left_revision, $right_revision);
    $header = array();

    foreach ($revisions as $revision) {
      if ($revision instanceof EntityRevisionLogInterface || $revision instanceof NodeInterface) {
        $revision_log = $this->nonBreakingSpace;

        if ($revision->revision_log->value != '') {
          $revision_log = Xss::filter($revision->revision_log->value);
        }
        $username = array(
          '#theme' => 'username',
          '#account' => $revision->uid->entity,
        );
        $revision_date = format_date($revision->getRevisionCreationTime(), 'short');
        $revision_link = $this->t($revision_log . '@date', array(
            '@date' => \Drupal::l($revision_date, Url::fromRoute("entity.$entity_type_id.revision", array(
              $entity_type_id => $revision->id(),
              $entity_type_id . '_revision' => $revision->getRevisionId(),
          ))),
        ));
      }
      else {
        $revision_link = $this->l($revision->label(), $revision->toUrl('revision'));
      }

      // @todo When theming think about where in the table to integrate this
      //   link to the revision user. There is some issue about multi-line headers
      //   for theme table.
      // $header[] = array(
      //   'data' => $this->t('by' . '!username', array('!username' => drupal_render($username))),
      //   'colspan' => 1,
      // );
      $header[] = array(
        'data' => array('#markup' => $this->nonBreakingSpace),
        'colspan' => 1,
      );
      $header[] = array(
        'data' => array('#markup' => $revision_link),
        'colspan' => 1,
      );
    }

    return $header;
  }
/**
   * This method should return an array of items ready to be compared.
   *
   * @param ContentEntityInterface $left_entity
   *   The left entity
   * @param ContentEntityInterface $right_entity
   *   The right entity
   *
   * @return array
   *   Items ready to be compared by the Diff component.
   */
  public function compareRevisions(ContentEntityInterface $left_entity, ContentEntityInterface $right_entity) {
    $result = array();

    $left_values = $this->entityParser->parseEntity($left_entity);
    $right_values = $this->entityParser->parseEntity($right_entity);

    foreach ($left_values as $field_name => $values) {
      $field_definition = $left_entity->getFieldDefinition($field_name);
      // Get the compare settings for this field type.
      $compare_settings = $this->pluginsConfig->get('field_types.' . $field_definition->getType());
      $result[$field_name] = array(
        '#name' => ($compare_settings['settings']['show_header'] == 1) ? $field_definition->getLabel() : '',
        '#settings' => $compare_settings,
      );

      // Fields which exist on the right entity also.
      if (isset($right_values[$field_name])) {
        $result[$field_name] += $this->combineFields($left_values[$field_name], $right_values[$field_name]);
        // Unset the field from the right entity so that we know if the right
        // entity has any fields that left entity doesn't have.
        unset($right_values[$field_name]);
      }
      // This field exists only on the left entity.
      else {
        $result[$field_name] += $this->combineFields($left_values[$field_name], array());
      }
    }

    // Fields which exist only on the right entity.
    foreach ($right_values as $field_name => $values) {
      $field_definition = $right_entity->getFieldDefinition($field_name);
      $compare_settings = $this->pluginsConfig->get('field_types.' . $field_definition->getType());
      $result[$field_name] = array(
        '#name' => ($compare_settings['settings']['show_header'] == 1) ? $field_definition->getLabel() : '',
        '#settings' => $compare_settings,
      );
      $result[$field_name] += $this->combineFields(array(), $right_values[$field_name]);
    }

    // Field rows. Recurse through all child elements.
    foreach (Element::children($result) as $key) {
      $result[$key]['#states'] = array();
      // Ensure that the element follows the #states format.
      if (isset($result[$key]['#left'])) {
        // We need to trim spaces and new lines from the end of the string
        // otherwise in some cases we have a blank not needed line.
        $result[$key]['#states']['raw']['#left'] = trim($result[$key]['#left']);
        unset($result[$key]['#left']);
      }
      if (isset($result[$key]['#right'])) {
        $result[$key]['#states']['raw']['#right'] = trim($result[$key]['#right']);
        unset($result[$key]['#right']);
      }
      $field_settings = $result[$key]['#settings'];

      if (!empty($field_settings['settings']['markdown'])) {
        $result[$key]['#states']['raw_plain']['#left'] = $this->applyMarkdown($field_settings['settings']['markdown'], $result[$key]['#states']['raw']['#left']);
        $result[$key]['#states']['raw_plain']['#right'] = $this->applyMarkdown($field_settings['settings']['markdown'], $result[$key]['#states']['raw']['#right']);
      }
      // In case the settings are not loaded correctly use drupal_html_to_text
      // to avoid any possible notices when a user clicks on markdown.
      else {
        $result[$key]['#states']['raw_plain']['#left'] = $this->applyMarkdown('drupal_html_to_text', $result[$key]['#states']['raw']['#left']);
        $result[$key]['#states']['raw_plain']['#right'] = $this->applyMarkdown('drupal_html_to_text', $result[$key]['#states']['raw']['#right']);
      }
    }

    // Process the array (split the strings into single line strings)
    // and get line counts per field.
    array_walk($result, array($this, 'processStateLine'));

    return $result;
  }

}
