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
use GuzzleHttp\Subscriber\Mock;
use holisticagency\satis\utilities\SatisHttpClient;
use GuzzleHttp\Message\Response;
use GuzzleHttp\Stream\Stream;
use org\bovigo\vfs\vfsStream;
use org\bovigo\vfs\vfsStreamWrapper;

/**
 * Base Tests.
 *
 * @author Gil <gillesodret@users.noreply.github.com>
 */
class SatisHttpClientTest extends PHPUnit_Framework_TestCase
{
    protected $mocks;

    protected $client;

    protected $otherclient;

    protected function setUp()
    {
        $this->mocks = array(
            'JsonOk' => new Mock([
                new Response(
                    200, [
                        'Content-Type: application/json',
                        'Content-Length: 331',
                        'Content-Disposition: attachment; filename="satis.json"',
                    ],
                    Stream::factory(
<<<EOT
{
    "name": "default name",
    "homepage": "http://localhost:54715",
    "repositories": [
        {
            "type": "vcs",
            "url": "https://github.com/vendor/name.git"
        }
    ],
    "require-all": true,
    "archive": {
        "directory": "dist",
        "format": "zip"
    },
    "output-html": false
}
EOT
                )),
            ]),
            'JsonNotExist' => new Mock([
                new Response(404),
            ]),
            'Forbidden' => new Mock([
                new Response(403),
            ]),
            'NeedCredentials' => new Mock([
                new Response(401),
            ]),
            'Created' => new Mock([
                new Response(201),
            ]),
            'ForbiddenOrNotAllowed' => new Mock([
                new Response(403),
                new Response(405),
            ]),
            'ThreeFilesCreated' => new Mock([
                new Response(201),
                new Response(201),
                new Response(201),
            ]),
        );

        $this->client = new SatisHttpClient('http://localhost:54715');
        $this->otherclient = new SatisHttpClient('http://localhost/withpath/');
        $this->clientwithcreds1 = new SatisHttpClient('http://localhost/withpath/', array('user', 'pass'));
        $this->clientwithcreds2 = new SatisHttpClient('https://user:pass@localhost/withpath/');
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

    /**
     * @dataProvider checkExtensionsProvider
     */
    public function testCheckExtensions($file, $expected)
    {
        $reflection = new \ReflectionClass(get_class($this->client));
        $method = $reflection->getMethod('checkExtension');
        $method->setAccessible(true);

        $this->assertEquals($expected, $method->invokeArgs($this->client, array($file)));
    }

    /**
     * @dataProvider checkDirectoriesProvider
     */
    public function testCheckDirectories($file, $expected)
    {
        $reflection = new \ReflectionClass(get_class($this->client));
        $method = $reflection->getMethod('checkDirectory');
        $method->setAccessible(true);

        $this->assertEquals($expected, $method->invokeArgs($this->client, array($file)));
    }

    /**
     * @dataProvider checkDirectoriesProvider
     */
    public function testCheckDirectoriesWithPathInUrl($file, $expected)
    {
        $reflection = new \ReflectionClass(get_class($this->otherclient));
        $method = $reflection->getMethod('checkDirectory');
        $method->setAccessible(true);

        $this->assertEquals($expected, $method->invokeArgs($this->otherclient, array($file)));
    }

    /**
     * @expectedException     Exception
     * @expectedExceptionCode 1
     */
    public function testPutFileNotAllowed()
    {
        $this->client->getEmitter()->attach($this->mocks['Forbidden']);

        $response = $this->client->putFile('index.php');
    }

    public function testGetJsonFileOK()
    {
        $this->client->getEmitter()->attach($this->mocks['JsonOk']);

        $response = $this->client->getFile();
        $laststatus = $response->status();
        $body = $response->body();

        $this->assertTrue(
            $laststatus == SatisHttpClient::OK &&
            (strlen($body) > 0 && json_decode($body, true))
        );
    }

    public function testGetJsonFileNotFound()
    {
        $this->client->getEmitter()->attach($this->mocks['JsonNotExist']);

        $response = $this->client->getFile();
        $laststatus = $response->status();

        $this->assertTrue($laststatus == SatisHttpClient::NOT_FOUND);
    }

    public function testGetJsonFileNeedAuthentication()
    {
        $this->client->getEmitter()->attach($this->mocks['NeedCredentials']);

        $response = $this->client->getFile();
        $laststatus = $response->status();

        $this->assertTrue($laststatus == SatisHttpClient::UNAUTHORIZED);
    }

    public function testGetJsonFileForbidden()
    {
        $this->client->getEmitter()->attach($this->mocks['Forbidden']);

        $response = $this->client->getFile();
        $laststatus = $response->status();

        $this->assertTrue($laststatus == SatisHttpClient::FORBIDDEN);
    }

    public function testGetJsonFileWhithCredentialsAsArray()
    {
        $this->clientwithcreds1->getEmitter()->attach($this->mocks['JsonOk']);

        $response = $this->clientwithcreds1->getFile();
        $laststatus = $response->status();

        $this->assertTrue($laststatus == SatisHttpClient::OK);
    }

    public function testGetJsonFileWhithCredentialsInUrl()
    {
        $this->clientwithcreds2->getEmitter()->attach($this->mocks['JsonOk']);

        $response = $this->clientwithcreds2->getFile();
        $laststatus = $response->status();

        $this->assertTrue($laststatus == SatisHttpClient::OK);
    }

    public function testPutJsonFileNeedAuthentication()
    {
        $this->client->getEmitter()->attach($this->mocks['NeedCredentials']);

        $response = $this->client->putFile();
        $laststatus = $response->status();

        $this->assertTrue($laststatus == SatisHttpClient::UNAUTHORIZED);
    }

    public function testPutNewJsonFileForbiddenOrNotAllowed()
    {
        $this->client->getEmitter()->attach($this->mocks['ForbiddenOrNotAllowed']);

        $response = $this->client->putFile();
        $firstStatus = $response->status();

        $response = $this->client->putFile();
        $secondStatus = $response->status();

        $this->assertTrue(
            $firstStatus == SatisHttpClient::FORBIDDEN &&
            $secondStatus == SatisHttpClient::NOT_ALLOWED
        );
    }

    public function testPutNewOrUpdatedJsonFileAuthorized()
    {
        $this->client->getEmitter()->attach($this->mocks['Created']);

        $response = $this->client->putFile('test.json', '{"test": true}');
        $laststatus = $response->status();

        $this->assertTrue($laststatus == SatisHttpClient::CREATED);
    }

    public function testPutNewOrUpdatedJsonFileAuthorizedWithHeaders()
    {
        $this->client->getEmitter()->attach($this->mocks['Created']);

        $response = $this->client->putFile(
            'test.json',
            '{"x-header-test": true}',
            ['X-Header-Test' => 'true']
        );
        $laststatus = $response->status();

        $this->assertTrue($laststatus == SatisHttpClient::CREATED);
    }

    /**
     * @expectedException     Exception
     * @expectedExceptionCode 2
     */
    public function testPutBundleRepositoryZipdoesntFit()
    {
        $this->client->putBundleZip('build/build.zip');

        $this->assertTrue($this->client->status() == SatisHttpClient::UNAUTHORIZED);
    }

    public function testPutBundleRepositoryNeedAuthentication()
    {
        vfsStreamWrapper::register();
        $root = vfsStream::newDirectory('build');
        vfsStreamWrapper::setRoot($root);
        $file = vfsStream::newFile('build.zip');
        $file->setContent('zipcontent');
        $root->addChild($file);
        $this->client->getEmitter()->attach($this->mocks['NeedCredentials']);

        $this->client->putBundleZip(vfsStream::url('build/build.zip'));

        $this->assertTrue($this->client->status() == SatisHttpClient::UNAUTHORIZED);
    }

    public function testPutBundleRepositoryForbiddenOrNotAllowed()
    {
        vfsStreamWrapper::register();
        $root = vfsStream::newDirectory('build');
        vfsStreamWrapper::setRoot($root);
        $file = vfsStream::newFile('build.zip');
        $file->setContent('zipcontent');
        $root->addChild($file);
        $this->client->getEmitter()->attach($this->mocks['ForbiddenOrNotAllowed']);

        $this->client->putBundleZip(vfsStream::url('build/build.zip'));
        $firstStatus = $this->client->status();

        $this->client->putBundleZip(vfsStream::url('build/build.zip'));
        $secondStatus = $this->client->status();

        $this->assertTrue(
            $firstStatus == SatisHttpClient::FORBIDDEN &&
            $secondStatus == SatisHttpClient::NOT_ALLOWED
        );
    }

    public function testPutBundleRepositoryOK()
    {
        vfsStreamWrapper::register();
        $root = vfsStream::newDirectory('build');
        vfsStreamWrapper::setRoot($root);
        $file = vfsStream::newFile('build.zip');
        $file->setContent('repositoryzippedcontent');
        $root->addChild($file);
        $this->client->getEmitter()->attach($this->mocks['Created']);

        $this->client->putBundleZip(vfsStream::url('build/build.zip'));

        $this->assertTrue($this->client->status() == SatisHttpClient::CREATED);
    }

    public function testPutBundleRepositoryWAbsolutePath()
    {
        vfsStreamWrapper::register();
        $root = vfsStream::newDirectory('/home/somebody/.composer/cache/http---localhost-54715/build');
        vfsStreamWrapper::setRoot($root);

        $file = vfsStream::newFile('build.zip');
        $file->setContent('repositoryzippedcontent');
        $root->addChild($file);

        $this->client->getEmitter()->attach($this->mocks['Created']);

        $this->client->putBundleZip(vfsStream::url($root->getName().'/build.zip'));

        $this->assertTrue($this->client->status() == SatisHttpClient::CREATED);
    }

    /**
     * @expectedException     Exception
     * @expectedExceptionCode 3
     */
    public function testPutDirFails()
    {
        vfsStreamWrapper::register();
        $root = vfsStream::newDirectory('repository');
        vfsStreamWrapper::setRoot($root);
        $this->client->putDir(vfsStream::url('build/'));
    }

    public function testPutDirOK()
    {
        vfsStreamWrapper::register();
        $root = vfsStream::newDirectory('build');
        vfsStreamWrapper::setRoot($root);
        $file = vfsStream::newFile('packages.json');
        $file->setContent('{
    "packages": [],
    "includes": {
        "include/all$c8233cd260af0878200d33532a634f58473ab51a.json": {
            "sha1": "c8233cd260af0878200d33532a634f58473ab51a"
        }
    }
}
');
        $root->addChild($file);
        $file = vfsStream::newFile('include/all$c8233cd260af0878200d33532a634f58473ab51a.json');
        $file->setContent('{
    "packages": {
        "vendor/name": {
        }
    }
}');
        $root->addChild($file);
        $file = vfsStream::newFile('dist/vendor-name-dev-master-e66490.zip');
        $file->setContent('packagezippedcontent');
        $root->addChild($file);
        $this->client->getEmitter()->attach($this->mocks['ThreeFilesCreated']);

        $this->client->putDir(vfsStream::url('build'));

        $this->assertTrue($this->client->status() == SatisHttpClient::CREATED);
    }
}
