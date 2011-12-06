<?php
/**
 * MicrodataPHP
 * http://github.com/linclark/MicrodataPHP
 * Copyright (c) 2011 Lin Clark
 * Licensed under the MIT license: http://www.opensource.org/licenses/mit-license.php
 *
 * Based on MicrodataJS
 * http://gitorious.org/microdatajs/microdatajs
 * Copyright (c) 2009-2011 Philip Jgenstedt
 */

/**
 * Defines a parser for extracting microdata items from HTML.
 */
class MicrodataPhp {
  protected $url;
  public $xpath;

  public function __construct($url) {
    $dom = new DOMDocument();
    // Extend DOMElements class so that we can add Microdata DOM API functions.
    $dom->registerNodeClass('DOMElement', 'MicrodataPhpDomElement');
    $dom->preserveWhiteSpace = false;
    @$dom->loadHTMLFile($url);

    $this->url = $url;
    $this->xpath = new DOMXPath($dom);
  }

  public function php_array() {
    $result = new stdClass();
    $result->items = array();
    foreach ($this->items() as $item) {
      if (!$item->hasAttribute('itemprop')) {
        array_push($result->items, $this->getObject($item, array()));
      }
    }
    return $result->items;
  }

  public function items(array $types = array()) {
    // Return top level items.
    return $this->xpath->query('//*[@itemscope]');
  }

  public function properties($root) {
    $props = array();

    if ($root->itemScope()) {
      $toTraverse = array($root);

      foreach ($root->itemRef() as $itemref) {
        //@todo Implement itemref support.
      }
      while (count($toTraverse)) {
        $this->traverse($toTraverse[0], $toTraverse, $props, $root);
      }
    }

    return $props;
  }

  /**
   * Helper functions.
   *
   * In MicrodataJS, this is handled using a closure. PHP 5.3 allows closures,
   * but cannot use $this within the closure. PHP 5.4 reintroduces support for
   * $this. When PHP 5.3/5.4 are more widely supported on shared hosting,
   * these functions could be handled with closures.
   */

  /**
   * Traverse the tree.
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
      // We use an xpath expression to get children instead of childNodes
      // because childNodes returns DOMText children as well, which breaks on
      // the call to getAttributes() in itemProp().
      $children = $this->xpath->query($node->getNodePath() . '/*');
      foreach ($children as $child) {
        $this->traverse($child, $toTraverse, $props, $root);
      }
    }
  }

  function getObject($item, $memory) {
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
      foreach ($this->properties($item) as $elem) {
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
class MicrodataPhpDomElement extends DOMElement {
  
  public function itemScope() {
    return $this->hasAttribute('itemscope');
  }

  public function itemType() {
    $itemtype = $this->getAttribute('itemtype');
    if (!empty($itemtype)) {
      return $this->tokenList($itemtype);
    }
    // Return NULL instead of the empty string returned by getAttributes so we
    // can use the function for boolean tests.
    return NULL;
  }

  public function itemId() {
    $itemid = $this->getAttribute('itemid');
    if (!empty($itemid)) {
      return $itemid;
    }
    // Return NULL instead of the empty string returned by getAttributes so we
    // can use the function for boolean tests.
    return NULL;
  }

  public function itemProp() {
    $itemprop = $this->getAttribute('itemprop');
    if (!empty($itemprop)) {
      return $this->tokenList($itemprop);
    }
    return array();
  }

  public function itemRef() {
    $itemref = $this->getAttribute('itemref');
    if (!empty($itemref)) {
      return $this->tokenList($itemref);
    }
    return array();
  }

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
      case 'TIME':
        $datetime = $this->getAttribute('datetime');
        if (!empty($datetime))
          return $datetime;
      default:
        return $this->textContent;
    }
  }

  protected function tokenList($string) {
    return explode(' ', trim($string));
  }
}

?>