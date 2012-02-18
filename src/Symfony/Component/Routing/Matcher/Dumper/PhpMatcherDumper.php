<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Routing\Matcher\Dumper;

use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

/**
 * PhpMatcherDumper creates a PHP class able to match URLs for a given set of routes.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Arnaud Le Blanc <arnaud.lb@gmail.com>
 */
class PhpMatcherDumper extends MatcherDumper
{
    /**
     * Dumps a set of routes to a PHP class.
     *
     * Available options:
     *
     *  * class:      The class name
     *  * base_class: The base class name
     *
     * @param  array  $options An array of options
     *
     * @return string A PHP class representing the matcher class
     */
    public function dump(array $options = array())
    {
        $options = array_merge(array(
            'class'      => 'ProjectUrlMatcher',
            'base_class' => 'Symfony\\Component\\Routing\\Matcher\\UrlMatcher',
        ), $options);

        // trailing slash support is only enabled if we know how to redirect the user
        $interfaces = class_implements($options['base_class']);
        $supportsRedirections = isset($interfaces['Symfony\Component\Routing\Matcher\RedirectableUrlMatcherInterface']);

        return
            $this->startClass($options['class'], $options['base_class']).
            $this->addConstructor().
            $this->addMatcher($supportsRedirections).
            $this->endClass()
        ;
    }

    private function addMatcher($supportsRedirections)
    {
        $code = implode("\n", $this->compileRoutes($this->getRoutes(), $supportsRedirections));

        return <<<EOF

    public function match(\$pathinfo)
    {
        \$allow = array();
        \$pathinfo = urldecode(\$pathinfo);

$code
        throw 0 < count(\$allow) ? new MethodNotAllowedException(array_unique(\$allow)) : new ResourceNotFoundException();
    }

EOF;
    }

    private function compileRoutes(RouteCollection $routes, $supportsRedirections)
    {
        $code = array();
        $indent = 0;

        $collections = $this->groupRoutesByHostnameRegex($routes)->getRoot();

        $fetchedHostname = false;

        foreach ($collections as $collection) {

            if ($regex = $collection->get('hostnameRegex')) {

                if (!$fetchedHostname) {
                    $code[] = "\$hostname = \$this->context->getHost();";
                    $fetchedHostname = true;
                }

                $code[] = sprintf("if (preg_match(%s, \$hostname, \$hostnameMatches)) {", var_export(str_replace(array("\n", ' '), '', $regex), true));

                $indent = 4;
            }

            $collection = $this->buildPrefixTree($collection);
            $lines = $this->compilePrefixRoutes($collection, $supportsRedirections);
            $code = array_merge($code, $this->indentCode($lines, $indent));

            if ($regex) {
                $indent = 0;
                $code[] = '}';
            }
        }

        return $this->indentCode($code, 8);
    }

    private function compilePrefixRoutes(DumperPrefixCollection $collection, $supportsRedirections, $parentPrefix = '')
    {
        $code = array();
        $indent = 0;

        $prefix = $collection->getPrefix();

        $optimizable = 1 < strlen($prefix) && 1 < count($collection->getRoutes());

        $optimizedPrefix = $parentPrefix;

        if ($optimizable) {

            $optimizedPrefix = $prefix;

            $code[] = sprintf("if (0 === strpos(\$pathinfo, %s)) {", var_export($prefix, true));
            $indent = 4;
        }

        foreach ($collection as $route) {
            if ($route instanceof DumperCollection) {
                $lines = $this->compilePrefixRoutes($route, $supportsRedirections, $optimizedPrefix);
            } else {
                $lines = $this->compileRoute($route->getRoute(), $route->getName(), $supportsRedirections, $optimizedPrefix);
                $lines = $this->undentCode($lines, 8);
            }
            $code = array_merge($code, $this->indentCode($lines, $indent));
        }

        if ($optimizable) {
            $indent = 0;
            $code[] = '}';
            $code[] = '';
        }

        return $code;
    }

    private function compileRoute(Route $route, $name, $supportsRedirections, $parentPrefix = null)
    {
        $code = array();
        $compiledRoute = $route->compile();
        $conditions = array();
        $hasTrailingSlash = false;
        $matches = false;
        $hostnameMatches = false;
        $methods = array();
        if ($req = $route->getRequirement('_method')) {
            $methods = explode('|', strtoupper($req));
            // GET and HEAD are equivalent
            if (in_array('GET', $methods) && !in_array('HEAD', $methods)) {
                $methods[] = 'HEAD';
            }
        }
        $supportsTrailingSlash = $supportsRedirections && (!$methods || in_array('HEAD', $methods));

        if (!count($compiledRoute->getPathVariables()) && false !== preg_match('#^(.)\^(?P<url>.*?)\$\1#', str_replace(array("\n", ' '), '', $compiledRoute->getRegex()), $m)) {
            if ($supportsTrailingSlash && substr($m['url'], -1) === '/') {
                $conditions[] = sprintf("rtrim(\$pathinfo, '/') === %s", var_export(rtrim(str_replace('\\', '', $m['url']), '/'), true));
                $hasTrailingSlash = true;
            } else {
                $conditions[] = sprintf("\$pathinfo === %s", var_export(str_replace('\\', '', $m['url']), true));
            }
        } else {
            if ($compiledRoute->getStaticPrefix() && $compiledRoute->getStaticPrefix() != $parentPrefix) {
                $conditions[] = sprintf("0 === strpos(\$pathinfo, %s)", var_export($compiledRoute->getStaticPrefix(), true));
            }

            $regex = str_replace(array("\n", ' '), '', $compiledRoute->getRegex());
            if ($supportsTrailingSlash && $pos = strpos($regex, '/$')) {
                $regex = substr($regex, 0, $pos).'/?$'.substr($regex, $pos + 2);
                $hasTrailingSlash = true;
            }
            $conditions[] = sprintf("preg_match(%s, \$pathinfo, \$matches)", var_export($regex, true));

            $matches = true;
        }

        if ($compiledRoute->getHostnameVariables()) {
            $hostnameMatches = true;
        }

        $conditions = implode(' && ', $conditions);

        $gotoname = 'not_'.preg_replace('/[^A-Za-z0-9_]/', '', $name);

        $code[] = <<<EOF
        // $name
        if ($conditions) {
EOF;

        if ($methods) {
            if (1 === count($methods)) {
                $code[] = <<<EOF
            if (\$this->context->getMethod() != '$methods[0]') {
                \$allow[] = '$methods[0]';
                goto $gotoname;
            }
EOF;
            } else {
                $methods = implode('\', \'', $methods);
                $code[] = <<<EOF
            if (!in_array(\$this->context->getMethod(), array('$methods'))) {
                \$allow = array_merge(\$allow, array('$methods'));
                goto $gotoname;
            }
EOF;
            }
        }

        if ($hasTrailingSlash) {
            $code[] = sprintf(<<<EOF
            if (substr(\$pathinfo, -1) !== '/') {
                return \$this->redirect(\$pathinfo.'/', '%s');
            }
EOF
            , $name);
        }

        if ($scheme = $route->getRequirement('_scheme')) {
            if (!$supportsRedirections) {
                throw new \LogicException('The "_scheme" requirement is only supported for URL matchers that implement RedirectableUrlMatcherInterface.');
            }

            $code[] = sprintf(<<<EOF
            if (\$this->context->getScheme() !== '$scheme') {
                return \$this->redirect(\$pathinfo, '%s', '$scheme');
            }
EOF
            , $name);
        }

        // optimize parameters array

        if (($matches || $hostnameMatches) && $compiledRoute->getDefaults()) {

            $vars = array();
            if ($matches) {
                $vars[] = '$matches';
            }
            if ($hostnameMatches) {
                $vars[] = '$hostnameMatches';
            }
            $matchesExpr = implode(' + ', $vars);

            $code[] = sprintf("            return array_merge(\$this->mergeDefaults(%s, %s), array('_route' => '%s'));"
                , $matchesExpr, str_replace("\n", '', var_export($compiledRoute->getDefaults(), true)), $name);

        } elseif ($matches || $hostnameMatches) {

            if (!$matches) {
                $code[] = "            \$matches = \$hostnameMatches;";
            } else {
                if ($hostnameMatches) {
                    $code[] = "            \$matches = \$matches + \$hostnameMatches;";
                }
            }

            $code[] = sprintf("            \$matches['_route'] = '%s';", $name);
            $code[] = sprintf("            return \$matches;", $name);

        } elseif ($compiledRoute->getDefaults()) {
            $code[] = sprintf('            return %s;', str_replace("\n", '', var_export(array_merge($compiledRoute->getDefaults(), array('_route' => $name)), true)));
        } else {
            $code[] = sprintf("            return array('_route' => '%s');", $name);
        }
        $code[] = "        }";

        if ($methods) {
            $code[] = "        $gotoname:";
        }

        $code[] = '';

        return $code;
    }

    private function startClass($class, $baseClass)
    {
        return <<<EOF
<?php

use Symfony\Component\Routing\Exception\MethodNotAllowedException;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Symfony\Component\Routing\RequestContext;

/**
 * $class
 *
 * This class has been auto-generated
 * by the Symfony Routing Component.
 */
class $class extends $baseClass
{

EOF;
    }

    private function addConstructor()
    {
        return <<<EOF
    /**
     * Constructor.
     */
    public function __construct(RequestContext \$context)
    {
        \$this->context = \$context;
    }

EOF;
    }

    private function endClass()
    {
        return <<<EOF
}

EOF;
    }

    /**
     * Removes the given number of spaces from the begining of each line
     *
     * @param  array $lines Array of lines
     * @param  int   $width The number of spaces
     * @return array Array of undented lines
     */
    private function undentCode(array $lines, $width)
    {
        $code = array();
        $re = sprintf('#^ {0,%s}#', $width);

        foreach ($lines as $line) {
            foreach (explode("\n", $line) as $line) {
                if (trim($line)) {
                    $code[] = preg_replace($re, '', $line);
                } else {
                    $code[] = '';
                }
            }
        }

        return $code;
    }

    /**
     * Prepends the given number of spaces at the begining of each line
     *
     * @param  array $lines Array of lines
     * @param  int   $width The number of spaces
     * @return array Array of indented lines
     */
    private function indentCode(array $lines, $width)
    {
        $code = array();
        $indent = str_repeat(' ', $width);

        foreach ($lines as $line) {
            foreach (explode("\n", $line) as $line) {
                if (trim($line)) {
                    $code[] = $indent . $line;
                } else {
                    $code[] = '';
                }
            }
        }

        return $code;
    }

    /**
     * Groups consecutive routes having the same hostnameRegex
     *
     * The results is a collection of collections of routes having the same
     * hostnameRegex
     */
    private function groupRoutesByHostnameRegex(RouteCollection $routes, DumperCollection $root = null, DumperCollection $collection = null)
    {
        if (null === $root) {
            $root = new DumperCollection();
        }

        if (null === $collection) {
            $collection = new DumperCollection();
            $collection->set('hostnameRegex', null);

            $root->addRoute($collection);
        }

        foreach ($routes as $name => $route) {

            if ($route instanceof RouteCollection) {

                $collection = $this->groupRoutesByHostnameRegex($route, $root, $collection);

            } else {

                $regex = $route->compile()->getHostnameRegex();

                if ($regex !== $collection->get('hostnameRegex')) {

                    $collection = new DumperCollection();
                    $collection->set('hostnameRegex', $regex);
                    $root->addRoute($collection);
                }

                $collection->addRoute(new DumperRoute($name, $route, $routes));

            }
        }

        return $collection;
    }

    /**
     * Organizes the routes into a prefix tree
     *
     * Routes order is preserved such that traversing the tree will traverse the
     * routes in the origin order
     */
    private function buildPrefixTree(DumperCollection $collection)
    {
        $tree = new DumperPrefixCollection;
        $tree->setPrefix('');
        $current = $tree;

        foreach ($collection->getRoutes() as $route) {
            $current = $current->addPrefixRoute($route);
        }

        return $tree;
    }
}
