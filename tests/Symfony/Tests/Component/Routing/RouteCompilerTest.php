<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Component\Routing;

use Symfony\Component\Routing\Route;

class RouteCompilerTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @dataProvider provideCompileData
     */
    public function testCompile($name, $arguments, $prefix, $regex, $variables, $tokens)
    {
        $r = new \ReflectionClass('Symfony\\Component\\Routing\\Route');
        $route = $r->newInstanceArgs($arguments);

        $compiled = $route->compile();
        $this->assertEquals($prefix, $compiled->getStaticPrefix(), $name.' (static prefix)');
        $this->assertEquals($regex, str_replace(array("\n", ' '), '', $compiled->getRegex()), $name.' (regex)');
        $this->assertEquals($variables, $compiled->getVariables(), $name.' (variables)');
        $this->assertEquals($tokens, $compiled->getTokens(), $name.' (tokens)');
    }

    public function provideCompileData()
    {
        return array(
            array(
                'Static route',
                array('/foo'),
                '/foo', '#^/foo$#xs', array(), array(
                    array('text', '/foo'),
                )),

            array(
                'Route with a variable',
                array('/foo/{bar}'),
                '/foo', '#^/foo/(?P<bar>[^/]+?)$#xs', array('bar'), array(
                    array('variable', '/', '[^/]+?', 'bar'),
                    array('text', '/foo'),
                )),

            array(
                'Route with a variable that has a default value',
                array('/foo/{bar}', array('bar' => 'bar')),
                '/foo', '#^/foo(?:/(?P<bar>[^/]+?))?$#xs', array('bar'), array(
                    array('variable', '/', '[^/]+?', 'bar'),
                    array('text', '/foo'),
                )),

            array(
                'Route with several variables',
                array('/foo/{bar}/{foobar}'),
                '/foo', '#^/foo/(?P<bar>[^/]+?)/(?P<foobar>[^/]+?)$#xs', array('bar', 'foobar'), array(
                    array('variable', '/', '[^/]+?', 'foobar'),
                    array('variable', '/', '[^/]+?', 'bar'),
                    array('text', '/foo'),
                )),

            array(
                'Route with several variables that have default values',
                array('/foo/{bar}/{foobar}', array('bar' => 'bar', 'foobar' => '')),
                '/foo', '#^/foo(?:/(?P<bar>[^/]+?)(?:/(?P<foobar>[^/]+?))?)?$#xs', array('bar', 'foobar'), array(
                    array('variable', '/', '[^/]+?', 'foobar'),
                    array('variable', '/', '[^/]+?', 'bar'),
                    array('text', '/foo'),
                )),

            array(
                'Route with several variables but some of them have no default values',
                array('/foo/{bar}/{foobar}', array('bar' => 'bar')),
                '/foo', '#^/foo/(?P<bar>[^/]+?)/(?P<foobar>[^/]+?)$#xs', array('bar', 'foobar'), array(
                    array('variable', '/', '[^/]+?', 'foobar'),
                    array('variable', '/', '[^/]+?', 'bar'),
                    array('text', '/foo'),
                )),

            array(
                'Route with an optional variable as the first segment',
                array('/{bar}', array('bar' => 'bar')),
                '', '#^/(?:(?P<bar>[^/]+?))?$#xs', array('bar'), array(
                    array('variable', '/', '[^/]+?', 'bar'),
                )),

            array(
                'Route with an optional variable as the first segment with requirements',
                array('/{bar}', array('bar' => 'bar'), array('bar' => '(foo|bar)')),
                '', '#^/(?:(?P<bar>(foo|bar)))?$#xs', array('bar'), array(
                    array('variable', '/', '(foo|bar)', 'bar'),
                )),
        );
    }

    /**
     * @dataProvider provideCompileExtendedData
     */
    public function testCompileExtended($name, $arguments, $prefix, $regex, $variables, $pathVariables, $tokens, $hostnameRegex, $hostnameVariables, $hostnameTokens)
    {
        $r = new \ReflectionClass('Symfony\\Component\\Routing\\Route');
        $route = $r->newInstanceArgs($arguments);

        $compiled = $route->compile();
        $this->assertEquals($prefix, $compiled->getStaticPrefix(), $name.' (static prefix)');
        $this->assertEquals($regex, str_replace(array("\n", ' '), '', $compiled->getRegex()), $name.' (regex)');
        $this->assertEquals($variables, $compiled->getVariables(), $name.' (variables)');
        $this->assertEquals($pathVariables, $compiled->getPathVariables(), $name.' (path variables)');
        $this->assertEquals($tokens, $compiled->getTokens(), $name.' (tokens)');


        $this->assertEquals($hostnameRegex, str_replace(array("\n", ' '), '', $compiled->getHostnameRegex()), $name.' (hostname regex)');
        $this->assertEquals($hostnameVariables, $compiled->getHostnameVariables(), $name.' (hostname variables)');
        $this->assertEquals($hostnameTokens, $compiled->getHostnameTokens(), $name.' (hostname tokens)');
    } 

    public function provideCompileExtendedData()
    {
        return array(
            array(
                'Route with hostname pattern',
                array('/hello', array(), array(), array(), 'www.example.com'),
                '/hello', '#^/hello$#xs', array(), array(), array(
                    array('text', '/hello'),
                ),
                '#^www\.example\.com$#xs', array(), array(
                    array('text', 'www.example.com'),
                ),
            ),
            array(
                'Route with hostname pattern and some variables',
                array('/hello/{name}', array(), array(), array(), 'www.example.{tld}'),
                '/hello', '#^/hello/(?P<name>[^/]+?)$#xs', array('tld', 'name'), array('name'), array(
                    array('variable', '/', '[^/]+?', 'name'),
                    array('text', '/hello'),
                ),
                '#^www\.example\.(?P<tld>[^\.]+?)$#xs', array('tld'), array(
                    array('variable', '.', '[^\.]+?', 'tld'),
                    array('text', 'www.example'),
                ),
            ),
            array(
                'Route with variable at begining of hostname',
                array('/hello', array(), array(), array(), '{locale}.example.{tld}'),
                '/hello', '#^/hello$#xs', array('locale', 'tld'), array(), array(
                    array('text', '/hello'),
                ),
                '#^(?P<locale>[^\.]+?)\.example\.(?P<tld>[^\.]+?)$#xs', array('locale', 'tld'), array(
                    array('variable', '.', '[^\.]+?', 'tld'),
                    array('text', '.example'),
                    array('variable', '', '[^\.]+?', 'locale'),
                ),
            ),
            array(
                'Route with hostname variables that has a default value',
                array('/hello', array('locale' => 'a', 'tld' => 'b'), array(), array(), '{locale}.example.{tld}'),
                '/hello', '#^/hello$#xs', array('locale', 'tld'), array(), array(
                    array('text', '/hello'),
                ),
                '#^(?P<locale>[^\.]+?)\.example\.(?P<tld>[^\.]+?)$#xs', array('locale', 'tld'), array(
                    array('variable', '.', '[^\.]+?', 'tld'),
                    array('text', '.example'),
                    array('variable', '', '[^\.]+?', 'locale'),
                ),
            ),            
        );
    }
}

