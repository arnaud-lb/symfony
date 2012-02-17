<?php

use Symfony\Component\Routing\Exception\MethodNotAllowedException;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Symfony\Component\Routing\RequestContext;

/**
 * ProjectUrlMatcher
 *
 * This class has been auto-generated
 * by the Symfony Routing Component.
 */
class ProjectUrlMatcher extends Symfony\Component\Routing\Matcher\UrlMatcher
{
    /**
     * Constructor.
     */
    public function __construct(RequestContext $context)
    {
        $this->context = $context;
    }

    public function match($pathinfo)
    {
        $allow = array();
        $pathinfo = urldecode($pathinfo);

        // foo
        if (0 === strpos($pathinfo, '/foo') && preg_match('#^/foo/(?P<bar>baz|symfony)$#xs', $pathinfo, $matches)) {
            return array_merge($this->mergeDefaults($matches, array (  'def' => 'test',)), array('_route' => 'foo'));
        }

        // bar
        if (0 === strpos($pathinfo, '/bar') && preg_match('#^/bar/(?P<foo>[^/]+?)$#xs', $pathinfo, $matches)) {
            if (!in_array($this->context->getMethod(), array('GET', 'HEAD'))) {
                $allow = array_merge($allow, array('GET', 'HEAD'));
                goto not_bar;
            }
            $matches['_route'] = 'bar';
            return $matches;
        }
        not_bar:

        // barhead
        if (0 === strpos($pathinfo, '/barhead') && preg_match('#^/barhead/(?P<foo>[^/]+?)$#xs', $pathinfo, $matches)) {
            if (!in_array($this->context->getMethod(), array('GET', 'HEAD'))) {
                $allow = array_merge($allow, array('GET', 'HEAD'));
                goto not_barhead;
            }
            $matches['_route'] = 'barhead';
            return $matches;
        }
        not_barhead:

        // baz
        if ($pathinfo === '/test/baz') {
            return array('_route' => 'baz');
        }

        // baz2
        if ($pathinfo === '/test/baz.html') {
            return array('_route' => 'baz2');
        }

        // baz3
        if ($pathinfo === '/test/baz3/') {
            return array('_route' => 'baz3');
        }

        // baz4
        if (0 === strpos($pathinfo, '/test') && preg_match('#^/test/(?P<foo>[^/]+?)/$#xs', $pathinfo, $matches)) {
            $matches['_route'] = 'baz4';
            return $matches;
        }

        // baz5
        if (0 === strpos($pathinfo, '/test') && preg_match('#^/test/(?P<foo>[^/]+?)/$#xs', $pathinfo, $matches)) {
            if ($this->context->getMethod() != 'POST') {
                $allow[] = 'POST';
                goto not_baz5;
            }
            $matches['_route'] = 'baz5';
            return $matches;
        }
        not_baz5:

        // baz.baz6
        if (0 === strpos($pathinfo, '/test') && preg_match('#^/test/(?P<foo>[^/]+?)/$#xs', $pathinfo, $matches)) {
            if ($this->context->getMethod() != 'PUT') {
                $allow[] = 'PUT';
                goto not_bazbaz6;
            }
            $matches['_route'] = 'baz.baz6';
            return $matches;
        }
        not_bazbaz6:

        // foofoo
        if ($pathinfo === '/foofoo') {
            return array (  'def' => 'test',  '_route' => 'foofoo',);
        }

        // quoter
        if (preg_match('#^/(?P<quoter>[\']+)$#xs', $pathinfo, $matches)) {
            $matches['_route'] = 'quoter';
            return $matches;
        }

        if (0 === strpos($pathinfo, '/a')) {
            if (0 === strpos($pathinfo, '/a/b\'b')) {
                // foo1
                if (preg_match('#^/a/b\'b/(?P<foo>[^/]+?)$#xs', $pathinfo, $matches)) {
                    $matches['_route'] = 'foo1';
                    return $matches;
                }

                // bar1
                if (preg_match('#^/a/b\'b/(?P<bar>[^/]+?)$#xs', $pathinfo, $matches)) {
                    $matches['_route'] = 'bar1';
                    return $matches;
                }

            }

            // overriden
            if ($pathinfo === '/a/overriden2') {
                return array('_route' => 'overriden');
            }

            if (0 === strpos($pathinfo, '/a/b\'b')) {
                // foo2
                if (preg_match('#^/a/b\'b/(?P<foo1>[^/]+?)$#xs', $pathinfo, $matches)) {
                    $matches['_route'] = 'foo2';
                    return $matches;
                }

                // bar2
                if (preg_match('#^/a/b\'b/(?P<bar1>[^/]+?)$#xs', $pathinfo, $matches)) {
                    $matches['_route'] = 'bar2';
                    return $matches;
                }

            }

        }

        // foo3
        if (preg_match('#^/(?P<_locale>[^/]+?)/b/(?P<foo>[^/]+?)$#xs', $pathinfo, $matches)) {
            $matches['_route'] = 'foo3';
            return $matches;
        }

        // bar3
        if (preg_match('#^/(?P<_locale>[^/]+?)/b/(?P<bar>[^/]+?)$#xs', $pathinfo, $matches)) {
            $matches['_route'] = 'bar3';
            return $matches;
        }

        // ababa
        if ($pathinfo === '/ababa') {
            return array('_route' => 'ababa');
        }

        if (0 === strpos($pathinfo, '/aba')) {
            // foo4
            if (preg_match('#^/aba/(?P<foo>[^/]+?)$#xs', $pathinfo, $matches)) {
                $matches['_route'] = 'foo4';
                return $matches;
            }

        }

        if (preg_match('#^a\\.example\\.com$#xs', $hostname, $hostnameMatches)) {
            // route1
            if ($pathinfo === '/route1') {
                return array('_route' => 'route1');
            }

            if (0 === strpos($pathinfo, '/c2')) {
                // route2
                if ($pathinfo === '/c2/route2') {
                    return array('_route' => 'route2');
                }

            }

        }
        if (preg_match('#^b\\.example\\.com$#xs', $hostname, $hostnameMatches)) {
            if (0 === strpos($pathinfo, '/c2')) {
                // route3
                if ($pathinfo === '/c2/route3') {
                    return array('_route' => 'route3');
                }

            }

        }
        if (preg_match('#^a\\.example\\.com$#xs', $hostname, $hostnameMatches)) {
            // route4
            if ($pathinfo === '/route4') {
                return array('_route' => 'route4');
            }

        }
        if (preg_match('#^c\\.example\\.com$#xs', $hostname, $hostnameMatches)) {
            // route5
            if ($pathinfo === '/route5') {
                return array('_route' => 'route5');
            }

        }
        // route6
        if ($pathinfo === '/route6') {
            return array('_route' => 'route6');
        }

        if (preg_match('#^(?P<var1>[^\\.]+?)\\.example\\.com$#xs', $hostname, $hostnameMatches)) {
            // route11
            if ($pathinfo === '/route11') {
                $matches = $hostnameMatches;
                $matches['_route'] = 'route11';
                return $matches;
            }

            // route12
            if ($pathinfo === '/route12') {
                return array_merge($this->mergeDefaults($hostnameMatches, array (  'var1' => 'val',)), array('_route' => 'route12'));
            }

            // route13
            if (0 === strpos($pathinfo, '/route13') && preg_match('#^/route13/(?P<name>[^/]+?)$#xs', $pathinfo, $matches)) {
                $matches = $matches + $hostnameMatches;
                $matches['_route'] = 'route13';
                return $matches;
            }

            // route14
            if (0 === strpos($pathinfo, '/route14') && preg_match('#^/route14/(?P<name>[^/]+?)$#xs', $pathinfo, $matches)) {
                return array_merge($this->mergeDefaults($matches + $hostnameMatches, array (  'var1' => 'val',)), array('_route' => 'route14'));
            }

        }
        if (preg_match('#^c\\.example\\.com$#xs', $hostname, $hostnameMatches)) {
            // route15
            if (0 === strpos($pathinfo, '/route15') && preg_match('#^/route15/(?P<name>[^/]+?)$#xs', $pathinfo, $matches)) {
                $matches['_route'] = 'route15';
                return $matches;
            }

        }
        // route16
        if (0 === strpos($pathinfo, '/route16') && preg_match('#^/route16/(?P<name>[^/]+?)$#xs', $pathinfo, $matches)) {
            return array_merge($this->mergeDefaults($matches, array (  'var1' => 'val',)), array('_route' => 'route16'));
        }

        // route17
        if ($pathinfo === '/route17') {
            return array('_route' => 'route17');
        }

        throw 0 < count($allow) ? new MethodNotAllowedException(array_unique($allow)) : new ResourceNotFoundException();
    }
}
