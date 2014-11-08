<?php

namespace linclark\MicrodataPHP;

/**
 * Extend the DOMDocument class with the Microdata API functions.
 */
class MicrodataPhpDOMDocument extends \DOMDocument {
  /** @var \DOMXPath $xpath */
  protected $xpath;

  /**
   * Retrieves a list of microdata items.
   *
   * @return DOMNodeList
   *   A DOMNodeList containing all top level microdata items.
   *
   * @todo Allow restriction by type string.
   */
  public function getItems() {
    // Return top level items.
    return $this->xpath()->query('//*[@itemscope and not(@itemprop)]');
  }

  /**
   * Creates a DOMXPath to query this document.
   *
   * @return DOMXPath
   *   DOMXPath object.
   */
  public function xpath() {
    if (!isset($this->xpath)) {
      $this->xpath = new \DOMXPath($this);
    }

    return $this->xpath;
  }
}
