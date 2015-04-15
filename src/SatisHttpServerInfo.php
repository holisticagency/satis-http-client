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

use Symfony\Component\Finder\Finder;

/**
 * Satis http server information utilities.
 *
 * @author Gil <gillesodret@users.noreply.github.com>
 */
class SatisHttpServerInfo
{
    /**
     * Says if it is allowed to PUT a repository by Zip Bundle method.
     *
     * @var bool
     */
    private $acceptBundle = true;

    /**
     * Says if authentication is needed to GET and PUT.
     *
     * @var bool
     */
    private $needAuthentication = true;

    /**
     * List of file extensions allowed to PUT.
     *
     * @var array
     */
    protected $allowedFiles = array('json', 'zip');

    /**
     * List of directories where it is allowed to PUT.
     *
     * @var array
     */
    protected $allowedDirectories = array('dist', 'include');

    /**
     * Check if a file is allowed to PUT based extension.
     *
     * @param string $file file pathname to check
     *
     * @return bool true if it is allowed
     */
    private function checkExtension($file)
    {
        $allowed = implode('|', $this->allowedFiles);
        if (preg_match(',\.('.$allowed.')$,', $file)) {
            return true;
        }

        return false;
    }

    /**
     * Check if a file is allowed to PUT based sub-directory.
     *
     * @param string $file     file pathname to check
     * @param string $basePath path part of an url
     *
     * @return bool true if it is allowed
     */
    private function checkDirectory($file, $basePath = '/')
    {
        $func = function ($path) {
            return $path.'/';
        };
        $allowed = implode('|', array_map($func, $this->allowedDirectories));
        if (preg_match(
            ',^'.$basePath.'('.$allowed.')?'.quotemeta(basename(str_replace('\\', '/', $file))).'$,',
            $basePath.str_replace('\\', '/', $file)
        )) {
            return true;
        }

        return false;
    }

    /**
     * Shortcut check function.
     *
     * @param string $file     file pathname to check
     * @param string $basePath path part of an url
     *
     * @return bool true if it is allowed
     */
    public function check($file, $basePath = '/')
    {
        return $this->checkExtension($file) && $this->checkDirectory($file, $basePath);
    }

    /**
     * Find locally a set of files dealing with http server constraints.
     *
     * @param string $dir a local directory
     *
     * @return Symfony\Component\Finder\Finder The set of files
     */
    public function find($dir)
    {
        $allowedFiles = ',\.('.implode('|', $this->allowedFiles).')$,';
        $allowedDirectories = ',^('.implode('|', $this->allowedDirectories).')?,';

        $finder = new Finder();
        $finder->files()->in($dir)->name($allowedFiles)->path($allowedDirectories);

        return $finder;
    }

    /**
     * Sets the zip bundle flag.
     *
     * @param bool $accept true if it is allowed to PUT a repository by Zip Bundle method
     *
     * @return SatisHttpServerInfo this SatisHttpServerInfo Instance
     */
    public function setAcceptBundle($accept)
    {
        $this->acceptBundle = (bool) $accept;

        return $this;
    }

    /**
     * Sets the authentication flag.
     *
     * @param bool $authenticate true if authentication is needed to GET and PUT.
     *
     * @return SatisHttpServerInfo this SatisHttpServerInfo Instance
     */
    public function setNeedAuthentication($authenticate)
    {
        $this->needAuthentication = (bool) $authenticate;

        return $this;
    }

    /**
     * Sets the list of file extensions allowed to PUT.
     *
     * @param array $allowed list of allowed extensions
     *
     * @return SatisHttpServerInfo this SatisHttpServerInfo Instance
     */
    public function setAllowedFiles(array $allowed)
    {
        $this->allowedFiles = $allowed;

        return $this;
    }

    /**
     * Sets the list of directories where it is allowed to PUT.
     *
     * @param array $allowed list of alloawed directories
     *
     * @return SatisHttpServerInfo this SatisHttpServerInfo Instance
     */
    public function setAllowedDirectories(array $allowed)
    {
        $this->allowedDirectories = $allowed;

        return $this;
    }

    /**
     * Says if authentication is needed to GET and PUT.
     *
     * @return bool true if authentication is needed
     */
    public function isPrivate()
    {
        return $this->needAuthentication;
    }

    /**
     * Says if it is allowed to PUT a repository by Zip Bundle method.
     *
     * @return bool true if it is allowed to PUT a repository by Zip Bundle method
     */
    public function isBundled()
    {
        return $this->acceptBundle;
    }
}
