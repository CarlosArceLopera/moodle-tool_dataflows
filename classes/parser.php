<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace tool_dataflows;

use Symfony\Component\ExpressionLanguage\ExpressionLanguage;

/**
 * Expression Parsing helper class
 *
 * @package    tool_dataflows
 * @author     Kevin Pham <kevinpham@catalyst-au.net>
 * @copyright  Catalyst IT, 2022
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class parser {

    public function __construct() {
        $expressionlanguage = new ExpressionLanguage();
        $this->expressionlanguage = $expressionlanguage;
    }

    /**
     * Given an expression, and data. Evaluate and return the result.
     *
     * @param      string $expression
     * @param      array $variables containing anything relevant to the evaluation of the result
     * @return     mixed
     */
    public function evaluate(string $expression, array $variables) {
        return $this->internal_evaluator($expression, $variables, function () {
            // Do nothing.
        });
    }

    /**
     * Evalulates the expression provided and on fail will throw an exception.
     *
     * @param      string $expression
     * @param      array $variables containing anything relevant to the evaluation of the result
     * @return     mixed
     */
    public function evaluate_or_fail(string $expression, array $variables, $failcallback = null) {
        return $this->internal_evaluator($expression, $variables, $failcallback);
    }

    /**
     * Returns whether or not the provided string contains an expression, and the matches
     *
     * @param      string $expression
     * @param      array $matches
     */
    public function has_expression(string $expression): array {
        preg_match_all('/(?<expressionwrapper>\${{(?<expression>.*)}})/mU', (string) $expression, $matches, PREG_SET_ORDER);
        return [!empty($matches), $matches];
    }

    /**
     * Evalulates the expressions in the string provided.
     *
     * @param      string $string
     * @param      array $variables containing anything relevant to the evaluation of the result
     * @param      ?callable $failcallback containing anything relevant to the evaluation of the result
     * @return     mixed
     */
    private function internal_evaluator(string $string, array $variables, ?callable $failcallback) {
        // TODO: lint expressions before storing them. https://symfony.com/blog/new-in-symfony-5-1-expressionlanguage-validator .
        $evaluatedexpression = $string;
        [$hasexpression, $matches] = $this->has_expression($string);
        if ($hasexpression) {
            foreach ($matches as $match) {
                // Try and evalulate the expression. For results that could
                // not be matched, log this since it's probably defined in
                // the wrong spot.
                try {
                    $parsethis = trim($match['expression']);
                    error_reporting(E_ALL & ~E_NOTICE);
                    $result = $this->expressionlanguage->evaluate(
                        $parsethis,
                        $variables
                    );
                    error_reporting();
                    if ($result === null) {
                        if ($failcallback) {
                            $failcallback('Could not evaluate the expression ${{ ' . $parsethis . ' }}');
                        } else {
                            throw new \Exception('Could not evaluate the expression ${{ ' . $parsethis . ' }}');
                        }
                    }
                    // Set the evalulated expression to the one that passed through the expression language.
                    if (isset($result)) {
                        $evaluatedexpression = str_replace($match['expressionwrapper'], $result, $evaluatedexpression);
                    }
                } catch (\Throwable $e) {
                    if (isset($failcallback)) {
                        $failcallback("{$e->getMessage()}. for expression $string", $e);
                    } else {
                        throw $e;
                    }
                    continue;
                }
            }

            return $evaluatedexpression;
        }
        return $string;
    }
}
