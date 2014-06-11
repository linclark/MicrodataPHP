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
    $config = ['html' => file_get_contents(__DIR__ . '/../data/ironworks.html')];
    $microdata = new MicrodataPhp($config);
    $data = $microdata->obj();

    $name = $data->items[0]->properties['name'][0];

    $this->assertEquals($name, "Iron Works BBQ", "The name matches.");
  }

}
