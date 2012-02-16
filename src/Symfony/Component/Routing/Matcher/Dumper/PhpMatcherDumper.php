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
        $fetchedHostname = false;
        $indent = '        ';

        $hostnameGroups = $this->groupRoutes($this->flattenRoutes($routes));

        foreach ($hostnameGroups as $group) {

            if ($regex = $group['regex']) {

                if (!$fetchedHostname) {
                    $code[] = sprintf("%s\$hostname = \$this->context->getHost();", $indent);
                    $fetchedHostname = true;
                }

                $regex = str_replace(array("\n", ' '), '', $regex);

                $code[] = sprintf("%sif (preg_match(%s, \$hostname, \$hostnameMatches)) {", $indent, var_export($regex, true));

                $indent .= '    ';
            }

            foreach ($this->compilePrefixGroups($group['prefixGroups'], $supportsRedirections) as $line) {
                if (trim($line)) {
                    $code[] = $indent . $line;
                } else {
                    $code[] = '';
                }
            }

            if ($regex) {
                $indent = substr($indent, 0, -4);
                $code[] = sprintf("%s}", $indent);
            }
        }

        return $code;
    }

    /**
     * Compiles an array of prefix groups (routes sharing the same prefix)
     */
    private function compilePrefixGroups($groups, $supportsRedirections)
    {
        $code = array();

        $indent = '';

        foreach ($groups as $group) {
            $lines = $this->compilePrefixGroup($group, $supportsRedirections);
            $code = array_merge($code, $lines);
        }

        return $code;
    }

    /**
     * Compiles a group of routes sharing the same prefix
     */
    private function compilePrefixGroup($group, $supportsRedirections)
    {
        $code = array();

        $indent = '';

        if ($prefix = $group['prefix']) {
            $code[] = sprintf("%sif (0 === strpos(\$pathinfo, %s)) {", $indent, var_export($prefix, true));
            $indent = '    ';
        }

        foreach ($group['routes'] as $routeInfo) {

            if (isset($routeInfo['routes'])) {
                foreach ($this->compilePrefixGroup($routeInfo, $supportsRedirections) as $line) {
                    $code[] = $indent . $line;
                }
            } else {

                $route = $routeInfo['route'];
                $name = $routeInfo['name'];

                foreach ($this->compileRoute($route, $name, $supportsRedirections, $group['prefix']) as $line) {
                    foreach (explode("\n", $line) as $line) {
                        $code[] = $indent . substr($line, 8);
                    }
                }
            }
        }

        if ($prefix) {
            $code[] = "}";
        }

        return $code;
    }

    /**
     * Flattens a tree of routes in a single array. Output is an array of "route
     * info" arrays (an array with the route, its name and its parent collection
     * ) in declaration order (the routes are added as the tree is traversed
     * depth-first).
     */
    private function flattenRoutes(RouteCollection $routes, array &$bucket = array())
    {
        foreach ($routes as $name => $route) {
            if ($route instanceof RouteCollection) {
                $this->flattenRoutes($route, $bucket);
            } else {
                $bucket[] = array(
                    'name' => $name,
                    'route' => $route,
                    'parent' => $routes,
                );
            }
        }

        return $bucket;
    }

    /**
     * Groups sequences of routes having the same hostname pattern and hostname
     * requirements, keeping original order.
     *
     * @param array $routes Array of routes returned by flattenRoutes()
     */
    private function groupRoutes(array $routes)
    {
        $groups = array();

        // using ArrayObject to avoid playing with references
        $group = new \ArrayObject(array(
            'regex' => null,
            'routes' => array(),
        ));

        $groups[] = $group;

        foreach ($routes as $routeInfo) {

            $route = $routeInfo['route'];
            $regex = $route->compile()->getHostnameRegex();

            if ($regex !== $group['regex']) {

                $group = new \ArrayObject(array(
                    'regex' => $regex,
                    'routes' => array(),
                ));

                $groups[] = $group;
            }

            $group['routes'][] = $routeInfo;
        }

        foreach ($groups as $group) {
            $group['prefixGroups'] = $this->groupRoutesByPrefix($group['routes']);
        }

        return $groups;
    }

    /**
     * Organizes an array of routes into a tree of routes in which all childs of
     * a node share the same prefix, keeping routes order (traversing the tree
     * depth-first will visit each node in the original order of $routes).
     */
    private function groupRoutesByPrefix(array $routes)
    {
        $groups = array();

        $group = new \ArrayObject(array(
            'prefix' => '',
            'routes' => array(),
        ));

        $groupStack = array();

        $groups[] = $group;

        foreach ($routes as $routeInfo) {

            $route = $routeInfo['route'];
            $coll = $routeInfo['parent'];

            $pattern = $route->getPattern();

            while (true) {

                if ($group['prefix'] === $coll->getPrefix()) {
                    $group['routes'][] = $routeInfo;
                    break;

                } else if ('' !== $group['prefix'] && 0 === strpos($pattern, $group['prefix'])) {
                    $parent = $group;
                    $group = new \ArrayObject(array(
                        'prefix' => $coll->getPrefix(),
                        'routes' => array(),
                    ));
                    $parent['routes'][] = $group;
                    $groupStack[] = $group;
                    $group['routes'][] = $routeInfo;
                    break;

                } else {
                    $group = array_pop($groupStack);
                    if (!$group) {
                        $group = new \ArrayObject(array(
                            'prefix' => $coll->getPrefix(),
                            'routes' => array(),
                        ));
                        $groupStack[] = $group;
                        $groups[] = $group;
                    }
                }
            }
        }

        return $groups;
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

        if ($compiledRoute->getHostnameRegex()) {
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
}
