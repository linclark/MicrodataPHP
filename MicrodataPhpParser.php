<?php
require_once('simplehtmldom/simple_html_dom.php');
/**
 * Defines a parser for extracting microdata items from HTML.
 */
class MicrodataPhpParser {
  protected $url;
  protected $dom;
  public $xpath;

  public function __construct($url) {
    $this->url = $url;
    $dom = new DOMDocument;
    $dom->preserveWhiteSpace = false;
    @$dom->loadHTMLFile($this->url);
    $this->dom = $dom;
    $this->xpath = new DOMXPath($this->dom);
  }

  public function json() {

    $result = new stdClass();
    $result->items = array();
    foreach ($this->items() as $item) {
      if (!$item->hasAttribute('itemprop')) {
        array_push($result->items, $this->getObject($item, array()));
      }
    }
    dpm($result->items);
  }

  public function items(array $types = array()) {
    // Return top level items.
    return $this->xpath->query('//*[@itemscope]');
  }

  public function itemScope($item) {
    return $item->hasAttribute('itemscope');
  }

  public function itemtype($item) {
    $itemtype = $item->getAttribute('itemtype');
    if (!empty($itemtype)) {
      return $itemtype;
    }
    return FALSE;
  }

  public function itemid($item) {
    $itemid = $item->getAttribute('itemid');
    if (!empty($itemid)) {
      return $itemid;
    }
    return FALSE;
  }

  public function itemref($item) {
    $itemref = $item->getAttribute('itemref');
    if (!empty($itemref)) {
      return $this->tokenList($itemref);
    }
    return array();
  }

  public function itemProp($item) {
    $itemprop = $item->getAttribute('itemprop');
    if (!empty($itemprop)) {
      return $this->tokenList($itemprop);
    }
    return array();
  }

  public function itemValue($item) {
    $itemprop = $this->itemProp($item);
    if (empty($itemprop))
      return null;
    if ($this->itemScope($item)) {
      return $item;
    }
    switch (strtoupper($item->tagName)) {
      case 'META':
        return $item->getAttribute('content');
      case 'AUDIO':
      case 'EMBED':
      case 'IFRAME':
      case 'IMG':
      case 'SOURCE':
      case 'TRACK':
      case 'VIDEO':
        // @todo Should this test resolve?
        return $item->getAttribute('src');
      case 'A':
      case 'AREA':
      case 'LINK':
        // @todo Should this test resolve?
        return $item->getAttribute('href');
      case 'OBJECT':
        // @todo Should this test resolve?
        return $item->getAttribute('data');
      case 'TIME':
        $datetime = $item->getAttribute('datetime');
        if (!empty($datetime))
          return $datetime;
      default:
        return $item->textContent;
    }
  }

  public function properties($root) {
    $props = array();

    if ($this->itemScope($root)) {
      $toTraverse = array($root);

      foreach ($this->itemref($root) as $itemref) {
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
      $names = $this->itemProp($node);
      if (count($names)) {
        //@todo Add support for property name filtering.
        $props[] = $node;
      }
      if ($this->itemScope($node)) {
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
      if ($itemtype = $this->itemtype($item)) {
        $result->type = $itemtype;
      }
      // Add itemid. 
      if ($itemid = $this->itemid($item)) {
        $result->id = $itemid;
      }
      // Add properties.
      foreach ($this->properties($item) as $elem) {
        if ($this->itemScope($elem)) {
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
          $value = $this->itemValue($elem);
        }
        foreach ($this->itemProp($elem) as $prop) {
          $result->properties[$prop][] = $value;
        }
      }

      return $result;
    }

  protected function tokenList($string) {
    return explode(' ', trim($string));
  }
}
?>