<?php
/**
 * MicrodataPHP
 * http://github.com/linclark/MicrodataPHP
 * Copyright (c) 2011 Lin Clark
 * Licensed under the MIT license: http://www.opensource.org/licenses/mit-license.php
 *
 * Based on MicrodataJS
 * http://gitorious.org/microdatajs/microdatajs
 * Copyright (c) 2009-2011 Philip Jägenstedt
 */

/**
 * Extracts microdata from HTML.
 *
 * Currently supported formats:
 *   - PHP object
 *   - JSON
 */
class MicrodataPhp {
  public $dom;

  /**
   * Constructs a MicrodataPhp object.
   *
   * @param $url
   *   The url of the page to be parsed.
   */
  public function __construct($url) {
    $dom = new MicrodataPhpDOMDocument($url);
    $dom->registerNodeClass('DOMDocument', 'MicrodataPhpDOMDocument');
    $dom->registerNodeClass('DOMElement', 'MicrodataPhpDOMElement');
    $dom->preserveWhiteSpace = false;
    @$dom->loadHTMLFile($url);

    $this->dom = $dom;
  }

  /**
   * Retrieve microdata as a PHP object.
   *
   * @return
   *   An object with an 'items' property, which is an array of top level
   *   microdata items as objects with the following properties:
   *   - type: An array of itemtype(s) for the item, if specified.
   *   - id: The itemid of the item, if specified.
   *   - properties: An array of itemprops. Each itemprop is keyed by the
   *     itemprop name and has its own array of values. Values can be strings
   *     or can be other items, represented as objects.
   *
   * @todo MicrodataJS allows callers to pass in a selector for limiting the
   *   parsing to one section of the document. Consider adding such
   *   functionality.
   */
  public function obj() {
    $result = new stdClass();
    $result->items = array();
    foreach ($this->dom->getItems() as $item) {
      array_push($result->items, $this->getObject($item, array()));
    }
    return $result;
  }

  /**
   * Retrieve microdata in JSON format.
   *
   * @return
   *   See obj().
   *
   * @todo MicrodataJS allows callers to pass in a function to format the JSON.
   * Consider adding such functionality.
   */
  public function json() {
    return json_encode($this->obj());
  }

  /**
   * Helper function.
   *
   * In MicrodataJS, this is handled using a closure. PHP 5.3 allows closures,
   * but cannot use $this within the closure. PHP 5.4 reintroduces support for
   * $this. When PHP 5.3/5.4 are more widely supported on shared hosting,
   * this function could be handled with a closure.
   */
  protected function getObject($item, $memory) {
    $result = new stdClass();
    $result->properties = array();
  
    // Add itemtype.
    if ($itemtype = $item->itemType()) {
      $result->type = $itemtype;
    }
    // Add itemid. 
    if ($itemid = $item->itemid()) {
      $result->id = $itemid;
    }
    // Add properties.
    foreach ($item->properties() as $elem) {
      if ($elem->itemScope()) {
        if (in_array($elem, $memory)) {
          $value = 'ERROR';
        }
        else {
          $memory[] = $item;
          $value = $this->getObject($elem, $memory);
          array_pop($memory);
        }
      }
      else {
        $value = $elem->itemValue();
      }
      foreach ($elem->itemProp() as $prop) {
        $result->properties[$prop][] = $value;
      }
    }

    return $result;
  }

}

/**
 * Extend the DOMElement class with the Microdata API functions.
 */
class MicrodataPhpDOMElement extends DOMElement {

  /**
   * Determine whether the itemscope attribute is present on this element.
   *
   * @return
   *   boolean TRUE if this is an item, FALSE if it is not.
   */
  public function itemScope() {
    return $this->hasAttribute('itemscope');
  }

  /**
   * Retrieve this item's itemtypes.
   *
   * @return
   *   An array of itemtype tokens.
   */
  public function itemType() {
    $itemtype = $this->getAttribute('itemtype');
    if (!empty($itemtype)) {
      return $this->tokenList($itemtype);
    }
    // Return NULL instead of the empty string returned by getAttributes so we
    // can use the function for boolean tests.
    return NULL;
  }

  /**
   * Retrieve this item's itemid.
   *
   * @return
   *   A string with the itemid.
   */
  public function itemId() {
    $itemid = $this->getAttribute('itemid');
    if (!empty($itemid)) {
      return $itemid;
    }
    // Return NULL instead of the empty string returned by getAttributes so we
    // can use the function for boolean tests.
    return NULL;
  }

  /**
   * Retrieve this item's itemprops.
   *
   * @return
   *   An array of itemprop tokens.
   */
  public function itemProp() {
    $itemprop = $this->getAttribute('itemprop');
    if (!empty($itemprop)) {
      return $this->tokenList($itemprop);
    }
    return array();
  }

  /**
   * Retrieve the ids of other items which this item references.
   *
   * @return
   *   An array of ids as contained in the itemref attribute.
   */
  public function itemRef() {
    $itemref = $this->getAttribute('itemref');
    if (!empty($itemref)) {
      return $this->tokenList($itemref);
    }
    return array();
  }

  /**
   * Retrieve the properties
   *
   * @return
   *   An array of MicrodataPhpDOMElements which are properties of this
   *   element.
   */
  public function properties() {
    $props = array();

    if ($this->itemScope()) {
      $toTraverse = array($this);

      foreach ($this->itemRef() as $itemref) {
        $children = $this->ownerDocument->xpath()->query('//*[@id="'.$itemref.'"]');
        foreach($children as $child) {
          $this->traverse($child, $toTraverse, $props, $this);
        }
      }
      while (count($toTraverse)) {
        $this->traverse($toTraverse[0], $toTraverse, $props, $this);
      }
    }

    return $props;
  }

  /**
   * Retrieve the element's value, determined by the element type.
   *
   * @return
   *   The string value if the element is not an item, or $this if it is
   *   an item.
   */
  public function itemValue() {
    $itemprop = $this->itemProp();
    if (empty($itemprop))
      return null;
    if ($this->itemScope()) {
      return $this;
    }
    switch (strtoupper($this->tagName)) {
      case 'META':
        return $this->getAttribute('content');
      case 'AUDIO':
      case 'EMBED':
      case 'IFRAME':
      case 'IMG':
      case 'SOURCE':
      case 'TRACK':
      case 'VIDEO':
        // @todo Should this test that the URL resolves?
        return $this->getAttribute('src');
      case 'A':
      case 'AREA':
      case 'LINK':
        // @todo Should this test that the URL resolves?
        return $this->getAttribute('href');
      case 'OBJECT':
        // @todo Should this test that the URL resolves?
        return $this->getAttribute('data');
      case 'DATA':
        return $this->getAttribute('value');
      case 'TIME':
        $datetime = $this->getAttribute('datetime');
        if (!empty($datetime))
          return $datetime;
      default:
        return $this->textContent;
    }
  }

  /**
   * Parse space-separated tokens into an array.
   *
   * @param string $string
   *   A space-separated list of tokens.
   *
   * @return array
   *   An array of tokens.
   */
  protected function tokenList($string) {
    return explode(' ', trim($string));
  }

  /**
   * Traverse the tree.
   * 
   * In MicrodataJS, this is handled using a closure.
   * See comment for MicrodataPhp:getObject() for an explanation of closure use
   * in this library.
   */
  protected function traverse($node, &$toTraverse, &$props, $root) {
    foreach ($toTraverse as $i => $elem)  {
      if ($elem->isSameNode($node)){
        unset($toTraverse[$i]);
      }
    }
    if (!$root->isSameNode($node)) {
      $names = $node->itemProp();
      if (count($names)) {
        //@todo Add support for property name filtering.
        $props[] = $node;
      }
      if ($node->itemScope()) {
        return;
      }
    }
    if (isset($node)) {
      // An xpath expression is used to get children instead of childNodes
      // because childNodes contains DOMText children as well, which breaks on
      // the call to getAttributes() in itemProp().
      $children = $this->ownerDocument->xpath()->query($node->getNodePath() . '/*');
      foreach ($children as $child) {
        $this->traverse($child, $toTraverse, $props, $root);
      }
    }
  }

}

/**
 * Extend the DOMDocument class with the Microdata API functions.
 */
class MicrodataPhpDOMDocument extends DOMDocument {
  /**
   * Retrieves a list of microdata items.
   *
   * @return
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
   * @return
   *   DOMXPath object.
   */
  public function xpath() {
    return new DOMXPath($this);
  }
}

?>