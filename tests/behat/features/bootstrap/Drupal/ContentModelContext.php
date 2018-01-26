<?php

namespace Drupal;

use Behat\Behat\Context\Context;
use Behat\Gherkin\Node\TableNode;
use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\FieldConfigInterface;
use TravisCarden\BehatTableComparison\TableEqualityAssertion;

/**
 * Provides content model step definitions for Behat.
 */
class ContentModelContext extends FeatureContext implements Context {

  /**
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  private $entityTypeManager;

  /**
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  private $configFactory;

  public function __construct() {
    $this->entityTypeManager = \Drupal::entityTypeManager();
    $this->configFactory = \Drupal::configFactory();
  }

  /**
   * @Then exactly the following auto labels should be configured
   */
  public function assertAutoLabels(TableNode $expected) {
    $config = \Drupal::config('auto_entitylabel.settings')->get();
    $auto_label_info = [];
    foreach ($config as $key => $value) {
      $key_suffix = '_pattern';
      if (substr($key, -8) === $key_suffix) {
        $entity_types = [
          'node_type',
          'taxonomy_vocabulary',
        ];
        foreach ($entity_types as $entity_type_id) {
          $key_prefix = "{$entity_type_id}_";
          if (strpos($key, $key_prefix) === 0) {
            $id = substr($key, strlen($key_prefix), -strlen($key_suffix));
            /** @var \Drupal\Core\Entity\EntityInterface $entity_type */
            $entity_type = \Drupal::entityTypeManager()
              ->getStorage($entity_type_id)
              ->load($id);
            if ($entity_type) {
              $auto_label_info[] = [
                (string) $entity_type->getEntityType()->getLabel(),
                $entity_type->label(),
                $value,
              ];
            }
          }
        }
      }
    }
    $actual = new TableNode($auto_label_info);

    (new TableEqualityAssertion($expected, $actual))
      ->expectHeader([
        'type',
        'bundle',
        'pattern',
      ])
      ->ignoreRowOrder()
      ->setMissingRowsLabel('Missing patterns')
      ->setUnexpectedRowsLabel('Unexpected patterns')
      ->assert();
  }

  /**
   * @Then exactly the following entity type bundles should exist
   */
  public function assertBundles(TableNode $expected) {
    $bundle_info = [];
    foreach ($this->getEntityTypesWithBundles() as $entity_type) {
      $bundles = $this->entityTypeManager
        ->getStorage($entity_type->getBundleEntityType())
        ->loadMultiple();
      foreach ($bundles as $bundle) {
        $is_moderated = $bundle->getThirdPartySetting('workbench_moderation', 'enabled');
        $description = '';
        $description_getter = 'getDescription';
        if (method_exists($bundle, $description_getter)) {
          $description = call_user_func([
            $bundle,
            $description_getter,
          ]);
        }
        if (!isset($description) || !$description) {
          $description = '';
        }

        $bundle_info[] = [
          $entity_type->getBundleLabel(),
          $bundle->label(),
          $bundle->id(),
          $is_moderated ? 'moderated' : '',
          $description,
        ];
      }
    }
    $actual = new TableNode($bundle_info);

    (new TableEqualityAssertion($expected, $actual))
      ->expectHeader([
        'type',
        'label',
        'machine name',
        'moderated',
        'description',
      ])
      ->ignoreRowOrder()
      ->setMissingRowsLabel('Missing bundles')
      ->setUnexpectedRowsLabel('Unexpected bundles')
      ->assert();
  }

  /**
   * @Given exactly the fields in :csv should exist
   */
  public function assertFieldsFromCsv($csv) {
    $this->assertFieldsFromTable($this->getTableNodeFromCsv($csv));
  }

  /**
   * @Then exactly the following fields should exist
   */
  public function assertFieldsFromTable(TableNode $expected) {
    $fields = [];
    foreach ($this->getEntityTypesWithBundles() as $entity_type) {
      $bundles = $this->entityTypeManager
        ->getStorage($entity_type->getBundleEntityType())
        ->loadMultiple();
      foreach ($bundles as $bundle) {
        /** @var string[] $ids */
        $ids = \Drupal::entityQuery('field_config')
          ->condition('bundle', $bundle->id())
          ->execute();

        if (!$ids) {
          continue;
        }

        $display_id = "{$entity_type->id()}.{$bundle->id()}.default";
        $form_display = EntityFormDisplay::load($display_id);
        if (is_null($form_display)) {
          throw new \Exception(sprintf('No such form display %s.', $display_id));
        }
        $form_components = $form_display->getComponents();

        /** @var FieldConfigInterface $field_config */
        foreach (FieldConfig::loadMultiple($ids) as $id => $field_config) {
          $machine_name = $this->getFieldMachineNameFromConfigId($id);
          $field_storage = $field_config->getFieldStorageDefinition();
          $form_component = isset($form_components[$machine_name]) ? $form_components[$machine_name] : ['type' => 'hidden'];
          $fields[] = [
            $entity_type->getBundleLabel(),
            $bundle->label(),
            $field_config->getLabel(),
            $machine_name,
            $field_config->getType(),
            $field_config->isRequired() ? 'required' : '',
            $field_config->isTranslatable() ? 'translatable' : '',
            $field_storage->getCardinality() === -1 ? 'unlimited' : $field_storage->getCardinality(),
            $form_component['type'],
            $field_config->getDescription(),
          ];
        }
      }
    }
    $actual = new TableNode($fields);

    (new TableEqualityAssertion($expected, $actual))
      ->expectHeader([
        'entity type',
        'bundle',
        'label',
        'machine name',
        'type',
        'required',
        'translatable',
        'cardinality',
        'widget',
        'description',
      ])
      ->ignoreRowOrder()
      ->setMissingRowsLabel('Missing fields')
      ->setUnexpectedRowsLabel('Unexpected fields')
      ->assert();
  }

  /**
   * Gets the defined entity types that have bundles.
   *
   * @return \Drupal\Core\Entity\EntityTypeInterface[]
   *   An array of entity types.
   */
  protected function getEntityTypesWithBundles() {
    $entity_types = $this->entityTypeManager->getDefinitions();
    foreach ($entity_types as $id => $entity_type) {
      // Remove entity types that don't have bundles.
      $bundle_label = $entity_type->getBundleLabel();
      $bundle_entity_type = $entity_type->getBundleEntityType();
      if (empty($bundle_label) || empty($bundle_entity_type)) {
        unset($entity_types[$id]);
      }
    }
    return $entity_types;
  }

  /**
   * Gets the field machine name from a configuration object ID.
   *
   * @param string $id
   *   The field configuration object ID.
   *
   * @return string|false
   *   The machine name if found or FALSE if not.
   */
  protected function getFieldMachineNameFromConfigId($id) {
    return substr($id, strrpos($id, '.') + 1);
  }

}
