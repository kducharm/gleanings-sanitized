<?php

namespace Drupal;

use Behat\Behat\Context\SnippetAcceptingContext;
use Behat\Gherkin\Node\TableNode;
use Drupal\DrupalExtension\Context\RawDrupalContext;

/**
 * FeatureContext class defines custom step definitions for Behat.
 */
class FeatureContext extends RawDrupalContext implements SnippetAcceptingContext {

  /**
   * Gets a TableNode object from the data in a given CSV file.
   *
   * @param string $csv
   *   The name of a CSV file in the tests/behat/features/data directory, e.g.
   *   "file.csv".
   *
   * @return \Behat\Gherkin\Node\TableNode
   *   A TableNode object.
   */
  protected function getTableNodeFromCsv($csv) {
    $data = array_map('str_getcsv', file(__DIR__ . "../../../data/{$csv}"));
    return new TableNode($data);
  }

}
