<?php

/*
 * This file is part of the JsonSchema package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace JsonSchema\Uri;

use JsonSchema\Exception\UriResolverException;

/**
 * Resolves JSON Schema URIs
 * 
 * @author Sander Coolen <sander@jibber.nl> 
 */
class UriResolver
{
    /**
     * Parses a URI into five main components
     * 
     * @param string $uri
     * @return array 
     */
    public function parse($uri)
    {
        preg_match('|^(([^:/?#]+):)?(//([^/?#]*))?([^?#]*)(\?([^#]*))?(#(.*))?|', $uri, $match);

        $components = array();
        if (5 < count($match)) {
            $components =  array(
                'scheme'    => $match[2],
                'authority' => $match[4],
                'path'      => $match[5]
            );
        } 
        if (7 < count($match)) {
            $components['query'] = $match[7];
        }
        if (9 < count($match)) {
            $components['fragment'] = $match[9];
        }
        
        return $components;
    }
    
    /**
     * Builds a URI based on n array with the main components
     * 
     * @param array $components
     * @return string 
     */
    public function generate(array $components)
    {
        $uri = '';
        if(!empty($components['scheme'])) $uri = $components['scheme'] . '://';

        $uri .= $components['authority']
             . $components['path'];

        if (array_key_exists('query', $components) && !empty($components['query'])) {
            $uri .= '?'.$components['query'];
        }
        if (array_key_exists('fragment', $components) && !empty($components['fragment'])) {
            $uri .= '#'.$components['fragment'];
        }
        
        return $uri;
    }
    
    /**
     * Resolves a URI
     * 
     * @param string $uri Absolute or relative
     * @param type $baseUri Optional base URI
     * @return string
     */
    public function resolve($uri, $baseUri = null)
    {
        $components = $this->parse($uri);
        $path = $components['path'];
        
        if (! empty($components['scheme'])) {
            return $uri;
        }
        $baseComponents = $this->parse($baseUri);
        $basePath = $baseComponents['path'];
        
        $baseComponents['path'] = self::combineRelativePathWithBasePath($path, $basePath);
        if(!empty($components['fragment'])) $baseComponents['fragment'] = $components['fragment'];

        return $this->generate($baseComponents);
    }

    public function resolveWithoutFragment($uri, $baseUri = null)
    {
        $components = $this->parse($uri);
        $path = $components['path'];

        if (! empty($components['scheme'])) {
            return $uri;
        }
        $baseComponents = $this->parse($baseUri);
        $basePath = $baseComponents['path'];

        $baseComponents['path'] = self::combineRelativePathWithBasePath($path, $basePath);

        unset($baseComponents['fragment']);

        return $this->generate($baseComponents);
    }
    /**
     * Tries to glue a relative path onto an absolute one
     * 
     * @param string $relativePath
     * @param string $basePath
     * @return string Merged path
     * @throws UriResolverException 
     */
    static function combineRelativePathWithBasePath($relativePath, $basePath)
    {
        $relativePath = self::normalizePath($relativePath);
        $basePathSegments = self::getPathSegments($basePath);

        if($relativePath == '') return $basePath;

        preg_match('|^/?(\.\./(?:\./)*)*|', $relativePath, $match);
        $numLevelUp = strlen($match[0]) /3 + 1;

        if ($numLevelUp >= count($basePathSegments)) {
            throw new UriResolverException(sprintf("Unable to resolve URI '%s' from base '%s'", $relativePath, $basePath));
        }
        $basePathSegments = array_slice($basePathSegments, 0, -$numLevelUp);
        $path = preg_replace('|^/?(\.\./(\./)*)*|', '', $relativePath);
        
        return implode(DIRECTORY_SEPARATOR, $basePathSegments) . '/' . $path;
    }
    
    /**
     * Normalizes a URI path component by removing dot-slash and double slashes
     * 
     * @param string $path
     * @return string
     */
    private static function normalizePath($path)
    {
        $path = preg_replace('|((?<!\.)\./)*|', '', $path);
        $path = preg_replace('|//|', '/', $path);
        
        return $path;
    }
    
    /**
     * @return array
     */
    private static function getPathSegments($path) {
        if (strpos($path, DIRECTORY_SEPARATOR) !== FALSE) {
            return explode(DIRECTORY_SEPARATOR, $path);
        }

        return explode("/", $path);
    }
    
    /**
     * @param string $uri
     * @return boolean 
     */
    public function isValid($uri)
    {
        $components = $this->parse($uri);
        
        return !empty($components);
    }
}
