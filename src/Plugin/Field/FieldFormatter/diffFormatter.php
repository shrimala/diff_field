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
use Drupal\Core\Url;
use Drupal\Component\Utility\Xss;
use Drupal\Component\Utility\SafeMarkup;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\diff\DiffEntityComparison;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Utility\LinkGeneratorInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\diff\Controller\NodeRevisionController;
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
class diffFormatter extends FormatterBase  implements ContainerFactoryPluginInterface {
 /**
  * The entity comparison service for diff.
  */
 protected $entityComparison;
 /**
  * The current user.
  *
  * @var \Drupal\Core\Session\AccountInterface
  */
 protected $currentUser;
 /**
  * Constructs a diffFormatter object.
  *
  * @param string $plugin_id
  * The plugin_id for the formatter.
  * @param mixed $plugin_definition
  * The plugin implementation definition.
  * @param \Drupal\Core\Field\FieldDefinitionInterface $field_definition
  * The definition of the field to which the formatter is associated.
  * @param array $settings
  * The formatter settings.
  * @param string $label
  * The formatter label display setting.
  * @param string $view_mode
  * The view mode.
  * @param array $third_party_settings
  * Any third party settings settings.
  * @param \Drupal\Core\Session\AccountInterface $current_user.
  * The current user.
  * @param \Drupal\diff\DiffEntityComparison $entity_comparison
  * The diff entity comparison service.
  */
  public function __construct($plugin_id, $plugin_definition, FieldDefinitionInterface $field_definition, array $settings, $label, $view_mode, array $third_party_settings,AccountInterface $current_user,$entity_comparison) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $label, $view_mode, $third_party_settings);
	$this->currentUser = $current_user;
	$this->entityComparison = $entity_comparison;
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
      $container->get('current_user'),
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
      $node = entity_revision_load('node',$item->before_rid);
      $markups = $this->compareNodeRevisions($node, $item->before_rid, $item->after_rid, 'raw');    
      //$markup = $this->entityComparison->compareRevisions($item->before_rid, $item->after_rid);
      //$markup = $this->entityComparison->test(50);
      //$markup = $this->currentUser->id();
      $elements[$delta] = array(
        '#type' => 'markup',
        '#markup' => $markups,
      );
    }
    //=============================================================================
    return $elements;
  }
  public function compareNodeRevisions(NodeInterface $node, $left_vid, $right_vid, $filter) {
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
    $diff_rows[] = $this->buildRevisionsNavigation($node->id(), $vids, $left_vid, $right_vid);
    $diff_rows[] = $this->buildMarkdownNavigation($node->id(), $left_vid, $right_vid, $filter);
    $diff_header = $this->buildTableHeader($left_revision, $right_revision);
    // Perform comparison only if both node revisions loaded successfully.
    if ($left_revision != FALSE && $right_revision != FALSE) {
      $fields = $this->entityComparison->compareRevisions($left_revision, $right_revision);
      $node_base_fields = \Drupal::entityManager()->getBaseFieldDefinitions('node');
      // Check to see if we need to display certain fields or not based on
      // selected view mode display settings.
      foreach ($fields as $field_name => $field) {
        // If we are dealing with nodes only compare those fields
        // set as visible from the selected view mode.
        $view_mode = $this->entityComparison->config->get('content_type_settings.' . $node->getType() . '.view_mode');
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
      $i=0;
      $t='<table>';
      foreach ($fields as $field) {
        $field_label_row = '';
        if (!empty($field['#name'])) {
			 $field_label_row = array(
            'data' => $this->t('Changes to %name', array('%name' => $field['#name'])),
            'colspan' => 4,
            'class' => array('field-name'),
          );
          
        }
        $field_diff_rows = $this->entityComparison->getRows(
          $field['#states'][$filter]['#left'],
          $field['#states'][$filter]['#right']
        );
        // Add the field label to the table only if there are changes to that field.
        if (!empty($field_diff_rows) && !empty($field_label_row)) {
	        $xyz="<td colspan=4 >".$field_label_row['data']."</td>";
          $t=$t."<tr>".$xyz."</tr>";
          $i=0;
        }
        $array_count=count($field_diff_rows);
        // Add field diff rows to the table rows.
        if(!empty($field_diff_rows[$i][1]['data']['#markup']) || !empty($field_diff_rows[$i][3]['data']['#markup'])) {
          if ($array_count<=1) {
            $xyz="<tr><td>".$field_diff_rows[$i][0]['data']."</td><td>" .$field_diff_rows[$i][1]['data']['#markup']."</td>";
            $xyz=$xyz ."<td>". $field_diff_rows[$i][2]['data']."</td><td>".$field_diff_rows[$i][3]['data']['#markup']."</td></tr>";
            $t=$t.$xyz;
            $i=$i+1;
          }
          elseif ($array_count>1) {
            for($i=0;$i<$array_count;$i++) {
              $xyz="<tr><td>".$field_diff_rows[$i][0]['data']."</td><td>" .$field_diff_rows[$i][1]['data']['#markup']."</td>";
              $xyz=$xyz ."<td>". $field_diff_rows[$i][2]['data']."</td><td>".$field_diff_rows[$i][3]['data']['#markup']."</td></tr>";
              $t=$t.$xyz;
            }
          }
        }
      }
      $t=$t."</table>";
      return $t;
      //=====================Checking require =========================
      // Add the CSS for the diff.
      $build['#attached']['library'][] = 'diff/diff.general';
      $theme = $this->entityComparison->config->get('general_settings.theme');
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

      $build['back'] = array(
        '#type' => 'link',
        '#attributes' => array(
          'class' => array(
            'button',
            'diff-button',
          ),
        ),
        '#title' => $this->t('Back to Revision Overview'),
        '#url' => Url::fromRoute('entity.node.version_history', ['node' => $node->id()]),
      );
      
      return $field_diff_rows;
    }
    else {
      // @todo When task 'Convert drupal_set_message() to a service' (2278383)
      //   will be merged use the corresponding service instead.
      drupal_set_message($this->t('Selected node revisions could not be loaded.'), 'error');
    }
  }

  /**
   * Build the header for the diff table.
   *
   * @param $left_revision
   *   Revision from the left hand side.
   * @param $right_revision
   *   Revision from the right hand side.
   *
   * @return array
   *   Header for Diff table.
   */
  protected function buildTableHeader($left_revision, $right_revision) {
    $revisions = array($left_revision, $right_revision);
    $header = array();

    foreach ($revisions as $revision) {
      $revision_log = $this->entityComparison->nonBreakingSpace;

      if ($revision->revision_log->value != '') {
        $revision_log = Xss::filter($revision->revision_log->value);
      }
      $username = array(
        '#theme' => 'username',
        '#account' => $revision->uid->entity,
      );
      $revision_date = $this->entityComparison->date->format($revision->getRevisionCreationTime(), 'short');
      $revision_link = $this->t($revision_log . '@date', array(
        '@date' => \Drupal::l($revision_date, Url::fromRoute('entity.node.revision', array(
          'node' => $revision->id(),
          'node_revision' => $revision->getRevisionId(),
        ))),
      ));
      // @todo When theming think about where in the table to integrate this
      //   link to the revision user. There is some issue about multi-line headers
      //   for theme table.
      // $header[] = array(
      //   'data' => $this->t('by' . '!username', array('!username' => drupal_render($username))),
      //   'colspan' => 1,
      // );
      $header[] = array(
        'data' => array('#markup' => $this->entityComparison->nonBreakingSpace),
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
      $row[] = $this->entityComparison->nonBreakingSpace;
    }
    // Third column.
    $row[] = $this->entityComparison->nonBreakingSpace;
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
      $row[] = $this->entityComparison->nonBreakingSpace;
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

}
