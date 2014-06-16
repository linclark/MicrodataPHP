<?php

/**
 * @file
 * Contains linclark\MicrodataPhpTest
 */

namespace linclark\MicrodataPHP;

/**
 * Tests the MicrodataPHP functionality.
 */
class MicrodataPhpTest extends \PHPUnit_Framework_TestCase {

  /**
   * Tests parsing a sample html document.
   */
  public function testParseMicroData() {
    $config = $this->getConfig('person.html');
    $microdata = new MicrodataPhp($config);
    $data = $microdata->obj();

    $name = $data->items[0]->properties['name'][0];

    $this->assertEquals($name, "Jane Doe", "The name matches.");
  }

  public function testItemtype() {
    $config = $this->getConfig('itemtype.html');
    $microdata = new MicrodataPhp($config);
    $data = $microdata->obj();

    $type = $data->items[0]->type;

    $this->assertCount(2, $type, 'Incorrect number of itemtypes found.');
    $this->assertTrue(in_array('http://schema.org/ComedyEvent', $type) && in_array('http://schema.org/DanceEvent', $type), 'Incorrect types extracted.');
  }

  /**
   * @expectedException \InvalidArgumentException
   */
  public function testConstructorNoUrlOrHtml() {
    $config = array();
    new MicrodataPhp($config);
  }

  protected function getConfig($file) {
    return array('html' => file_get_contents(__DIR__  . '/../data/' . $file));
  }
}
