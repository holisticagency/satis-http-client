<?php

/**
 * This file is part of holisatis.
 *
 * (c) Gil <gillesodret@users.noreply.github.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace holisticagency\satis\Test;

use PHPUnit_Framework_TestCase;
use holisticagency\satis\utilities\SatisHttpServerInfo;

/**
 * ServerInfo Tests.
 *
 * @author Gil <gillesodret@users.noreply.github.com>
 */
class SatisHttpServerInfoTest extends PHPUnit_Framework_TestCase
{
    protected $httpServerInfo;

    public function setUp()
    {
        $this->httpServerInfo = new SatisHttpServerInfo();
    }

    public function checkExtensionsProvider()
    {
        return array(
          array('test.json', true),
          array('dist/some.zip', true),
          array('test.JSON', false),
          array('index.php', false),
          array('index.html', false),
        );
    }

    public function checkDirectoriesProvider()
    {
        return array(
          array('satis.json', true),
          array('dist/some.zip', true),
          array('build.zip', true),
          array('.hidden/satis.json', false),
          array('include/all$c8233cd260af0878200d33532a634f58473ab51a.json', true),
          array('/include/all.json', false),
          array('anywhere/satis.json', false),
          array('include/anywhere/satis.json', false),
          array('anywhere/dist/satis.json', false),
          array('../../../../satis.json', false),

          //windows sub-directories
          array('dist\some.zip', true),
          array('include\all$c8233cd260af0878200d33532a634f58473ab51a.json', true),
        );
    }

    public function checkExtensionsWithOtherSpecificationsProvider()
    {
        return array(
          array('test.json', false),
          array('dist/some.zip', false),
          array('index.php', false),
          array('index.html', false),
          array('index.yml', true),
        );
    }

    public function checkDirectoriesWithOtherSpecificationsProvider()
    {
        return array(
          array('satis.json', true),
          array('dist/some.zip', false),
          array('yaml/some.yml', true),
          array('build.zip', true),

          //windows sub-directories
          array('yaml\some.zip', true),
          array('include\all$c8233cd260af0878200d33532a634f58473ab51a.json', false),
        );
    }

    /**
     * @dataProvider checkExtensionsProvider
     */
    public function testCheckExtensions($file, $expected)
    {
        $reflection = new \ReflectionClass(get_class($this->httpServerInfo));
        $method = $reflection->getMethod('checkExtension');
        $method->setAccessible(true);

        $this->assertEquals($expected, $method->invokeArgs($this->httpServerInfo, array($file)));
    }

    /**
     * @dataProvider checkDirectoriesProvider
     */
    public function testCheckDirectories($file, $expected)
    {
        $reflection = new \ReflectionClass(get_class($this->httpServerInfo));
        $method = $reflection->getMethod('checkDirectory');
        $method->setAccessible(true);

        $this->assertEquals($expected, $method->invokeArgs($this->httpServerInfo, array($file)));
    }

    /**
     * @dataProvider checkDirectoriesProvider
     */
    public function testCheckDirectoriesWithPathInUrl($file, $expected)
    {
        $reflection = new \ReflectionClass(get_class($this->httpServerInfo));
        $method = $reflection->getMethod('checkDirectory');
        $method->setAccessible(true);

        $this->assertEquals($expected, $method->invokeArgs($this->httpServerInfo, array($file, '/withpath')));
    }

    public function testServerCanBeSetAnonymously()
    {
      $this->httpServerInfo->setNeedAuthentication(false);

      $this->assertFalse($this->httpServerInfo->isPrivate());
    }

    public function testServerDoesNotAcceptBundle()
    {
      $this->httpServerInfo->setAcceptBundle(false);

      $this->assertFalse($this->httpServerInfo->isBundled());
    }

    /**
     * @dataProvider checkDirectoriesWithOtherSpecificationsProvider
     */
    public function testCheckDirectoriesWithOtherSpecifications($file, $expected)
    {
      $this->httpServerInfo->setAllowedDirectories(array('yaml'));      

      $reflection = new \ReflectionClass(get_class($this->httpServerInfo));
      $method = $reflection->getMethod('checkDirectory');
      $method->setAccessible(true);

      $this->assertEquals($expected, $method->invokeArgs($this->httpServerInfo, array($file)));
    }

    /**
     * @dataProvider checkExtensionsWithOtherSpecificationsProvider
     */
    public function testCheckExtensionsWithOtherSpecifications($file, $expected)
    {
      $this->httpServerInfo->setAllowedFiles(array('yml'));      

      $reflection = new \ReflectionClass(get_class($this->httpServerInfo));
      $method = $reflection->getMethod('checkExtension');
      $method->setAccessible(true);

      $this->assertEquals($expected, $method->invokeArgs($this->httpServerInfo, array($file)));
    }
}
