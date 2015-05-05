<?php

/**
 * This file is part of holisatis.
 *
 * (c) Gil <gillesodret@users.noreply.github.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace holisticagency\satis\utilities;

use GuzzleHttp\Url;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;

/**
 * Satis http client utilities.
 *
 * @author Gil <gillesodret@users.noreply.github.com>
 */
class SatisHttpClient extends Client
{
    const OK = 200;
    const CREATED = 201;
    const UNAUTHORIZED = 401;
    const FORBIDDEN = 403;
    const NOT_FOUND = 404;
    const NOT_ALLOWED = 405;

    /**
     * Host part of url.
     *
     * @var string
     */
    private $baseUrl;

    /**
     * Path part of url.
     *
     * @var string
     */
    protected $basePath = '/';

    /**
     * Credentials.
     *
     * @var array
     *            - 0 => user
     *            - 1 => password
     */
    private $credentials;

    /**
     * Last http status.
     *
     * @var int
     */
    private $lastStatus;

    /**
     * Last body retrieved.
     *
     * @var GuzzleHttp\Stream
     */
    private $lastBody;

    /**
     * Satis Http Server Informations.
     *
     * @var SatisHttpServerInfo
     */
    private $httpServer;

    /**
     * Constructor.
     *
     * @param string                   $homepage    satis repository url
     * @param array|null               $credentials Credentials (user, pass)
     * @param SatisHttpServerInfo|null $httpServer  Satis Http Server Informations
     */
    public function __construct($homepage, array $credentials = null, SatisHttpServerInfo $httpServer = null)
    {
        $this->setCredentials($credentials);
        $this->setUrlHelper($homepage);
        parent::__construct(['base_url' => $this->baseUrl]);
        $this->httpServer = is_null($httpServer) ? new SatisHttpServerInfo() : $httpServer;
        $this->checkServer();
    }

    /**
     * Set base parameters of homepage url.
     *
     * @param string $homepage satis repository url
     */
    private function setUrlHelper($homepage)
    {
        $parsedUrl = Url::fromString($homepage)->getParts();

        if (isset($parsedUrl['user']) && isset($parsedUrl['pass'])) {
            $this->credentials = array('auth' => array($parsedUrl['user'], $parsedUrl['pass']));
        }

        if (isset($parsedUrl['path'])) {
            $this->basePath = $parsedUrl['path'];
            $this->basePath .= (substr($this->basePath, -1) == '/' ? '' : '/');
        }

        $this->baseUrl = Url::buildUrl(array(
            'scheme' => $parsedUrl['scheme'],
            'host'   => $parsedUrl['host'],
            'port'   => $parsedUrl['port'],
        ));

        return $this;
    }

    /**
     * Sets the credentials to authenticate.
     *
     * @param array|null $credentials Credentials (user, pass)
     */
    public function setCredentials(array $credentials = null)
    {
        $this->credentials = array('auth' => $credentials);

        return $this;
    }

    /**
     * Get the last http status retrieved.
     *
     * @return int last http status recieved
     */
    public function status()
    {
        return $this->lastStatus;
    }

    /**
     * Get the last body response retrieved.
     *
     * @return string last body recieved
     */
    public function body()
    {
        return ''.$this->lastBody;
    }

    /**
     * Check if client/server can talk together.
     *
     * @throws \Exception if misconfigured client/server
     *
     * @return bool true if client/server can talk together
     */
    public function checkServer()
    {
        if (is_null($this->credentials['auth']) xor !$this->httpServer->isPrivate()) {
            throw new \Exception('Error Initializing SatisHttpClient (auth problem)', 4);
        }

        return true;
    }

    /**
     * Get a file from homepage url.
     *
     * @param string $file a file to GET
     *
     * @return SatisHttpClient this SatisHttpClient Instance
     */
    public function getFile($file = 'satis.json')
    {
        try {
            $response = $this->get($this->basePath.$file, $this->credentials);
            $this->lastStatus = $response->getStatusCode();
            $this->lastBody = ''.$response->getBody();
        } catch (ClientException $e) {
            //@TODO could not resolve host
            //@TODO timeout (no response)
            if (preg_match('{404 (Not Found|Introuvable)}', $e->getResponse())) {
                $this->lastStatus = self::NOT_FOUND;
            }
            if (preg_match('{403 Forbidden}', $e->getResponse())) {
                $this->lastStatus = self::FORBIDDEN;
            }
            if (preg_match('{401 (Unauthorized|Non-Autoris)}', $e->getResponse())) {
                $this->lastStatus = self::UNAUTHORIZED;
            }
        }

        return $this;
    }

    /**
     * Put a file to homepage url.
     *
     * @param string $file    the file to PUT
     * @param string $content the content a the file
     * @param array  $headers additional headers
     *
     * @throws \Exception if $file is not an allowed file
     *
     * @return SatisHttpClient this SatisHttpClient Instance
     */
    public function putFile($file = 'satis.json', $content = '', $headers = array())
    {
        try {
            if ($this->httpServer->check($file, $this->basePath)) {
                $response = $this->put($this->basePath.str_replace('\\', '/', $file), array_merge(
                    array(
                        'body' => $content,
                        'headers' => $headers,
                    ),
                    $this->credentials
                ));
                $this->lastStatus = $response->getStatusCode();
                $this->lastBody = ''.$response->getBody();

                return $this;
            }

            throw new \Exception(
                'Error Processing PUT Request of '.$file.' (not allowed files or sub-directories)',
                1
            );
        } catch (ClientException $e) {
            if (preg_match('{403 Forbidden}', $e->getResponse())) {
                $this->lastStatus = self::FORBIDDEN;
            }
            if (preg_match('{405 Method Not Allowed}', $e->getResponse())) {
                $this->lastStatus = self::NOT_ALLOWED;
            }
            if (preg_match('{401 (Unauthorized|Non-Autoris)}', $e->getResponse())) {
                $this->lastStatus = self::UNAUTHORIZED;
            }
        }

        return $this;
    }

    /**
     * Put a repository set of files to homepage url as a bundle.
     *
     * @param string $zip the set of files bundled in a zip archive
     *
     * @throws \Exception If $zip is not an archive zip file
     *
     * @return SatisHttpClient this SatisHttpClient Instance
     */
    public function putBundleZip($zip = 'build.zip')
    {
        if ($this->httpServer->isBundled() && file_exists($zip) && $zipContents = file_get_contents($zip)) {
            $this->putFile(basename($zip), $zipContents, array('X-Explode-Archive' => 'true'));

            return $this;
        }

        throw new \Exception('Error Processing PUT Request of '.$zip.' (zip problem)', 2);
    }

    /**
     * Put a repository set of files to homepage url one by one.
     *
     * @param string $dir the set of files
     *
     * @throws \Exception if $dir is not a directory
     *
     * @return SatisHttpClient this SatisHttpClient Instance
     */
    public function putDir($dir)
    {
        if (is_dir($dir)) {
            $finder = $this->httpServer->find($dir);

            foreach ($finder as $file) {
                if ($contents = $file->getContents()) {
                    $this->putFile($file->getRelativePathname(), $contents);
                }
            }

            return $this;
        }
        throw new \Exception('Error Processing PUT Request of '.$dir.' (directory problem)', 3);
    }
}
