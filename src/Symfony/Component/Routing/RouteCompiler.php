<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Routing;

/**
 * RouteCompiler compiles Route instances to CompiledRoute instances.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class RouteCompiler implements RouteCompilerInterface
{
    /**
     * Compiles the current route instance.
     *
     * @param Route $route A Route instance
     *
     * @return CompiledRoute A CompiledRoute instance
     */
    public function compile(Route $route)
    {
        $staticPrefix = null;
        $hostnameVariables = array();
        $pathVariables = array();
        $variables = array();
        $tokens = array();
        $regex = null;
        $hostnameRegex = null;

        if (null !== $hostnamePattern = $route->getHostnamePattern()) {

            $result = $this->compilePattern($route, $hostnamePattern, false);

            $hostnameVariables = $result['variables'];
            $variables = array_merge($variables, $hostnameVariables);

            $hostnameRegex = $result['regex'];
        }

        $pattern = $route->getPattern();
        $result = $this->compilePattern($route, $pattern, true);

        $staticPrefix = $result['staticPrefix'];

        $pathVariables = $result['variables'];
        $variables = array_merge($variables, $pathVariables);

        $tokens = array_merge($tokens, $result['tokens']);
        $regex = $result['regex'];

        return new CompiledRoute(
            $route,
            $staticPrefix,
            $regex,
            $tokens,
            array_unique($variables),
            $pathVariables,
            $hostnameVariables,
            $hostnameRegex
        );
    }

    private function compilePattern(Route $route, $pattern, $isPath)
    {
        $len = strlen($pattern);
        $tokens = array();
        $variables = array();
        $pos = 0;
        preg_match_all('#.\{([\w\d_]+)\}#', $pattern, $matches, PREG_OFFSET_CAPTURE | PREG_SET_ORDER);
        foreach ($matches as $match) {
            if ($text = substr($pattern, $pos, $match[0][1] - $pos)) {
                $tokens[] = array('text', $text);
            }
            if ($isPath) {
                $seps = array($pattern[$pos]);
            } else {
                $seps = array('.');
            }
            $pos = $match[0][1] + strlen($match[0][0]);
            $var = $match[1][0];

            if ($req = $route->getRequirement($var)) {
                $regexp = $req;
            } else {
                if ($pos !== $len) {
                    $seps[] = $pattern[$pos];
                }
                $regexp = sprintf('[^%s]+?', preg_quote(implode('', array_unique($seps)), '#'));
            }

            $tokens[] = array('variable', $match[0][0][0], $regexp, $var);
            $variables[] = $var;
        }

        if ($pos < $len) {
            $tokens[] = array('text', substr($pattern, $pos));
        }

        // find the first optional token
        $firstOptional = INF;
        if ($isPath) {
            for ($i = count($tokens) - 1; $i >= 0; $i--) {
                if ('variable' === $tokens[$i][0] && $route->hasDefault($tokens[$i][3])) {
                    $firstOptional = $i;
                } else {
                    break;
                }
            }
        }

        // compute the matching regexp
        $regex = '';
        $indent = 1;
        if (1 === count($tokens) && 0 === $firstOptional) {
            $token = $tokens[0];
            ++$indent;
            $regex .= str_repeat(' ', $indent * 4).sprintf("%s(?:\n", preg_quote($token[1], '#'));
            $regex .= str_repeat(' ', $indent * 4).sprintf("(?P<%s>%s)\n", $token[3], $token[2]);
        } else {
            foreach ($tokens as $i => $token) {
                if ('text' === $token[0]) {
                    $regex .= str_repeat(' ', $indent * 4).preg_quote($token[1], '#')."\n";
                } else {
                    if ($i >= $firstOptional) {
                        $regex .= str_repeat(' ', $indent * 4)."(?:\n";
                        ++$indent;
                    }
                    $regex .= str_repeat(' ', $indent * 4).sprintf("%s(?P<%s>%s)\n", preg_quote($token[1], '#'), $token[3], $token[2]);
                }
            }
        }
        while (--$indent) {
            $regex .= str_repeat(' ', $indent * 4).")?\n";
        }

        return array(
            'staticPrefix' => 'text' === $tokens[0][0] ? $tokens[0][1] : '',
            'regex' => sprintf("#^\n%s$#xs", $regex),
            'tokens' => array_reverse($tokens),
            'variables' => $variables,
        );
    }
}
