<?php
// {{{ICINGA_LICENSE_HEADER}}}
// {{{ICINGA_LICENSE_HEADER}}}

namespace Icinga\Data\Filter;

class FilterMatch extends FilterExpression
{
    public function matches($row)
    {
        if (! isset($row->{$this->column})) {
            // TODO: REALLY? Exception?
            return false;
        }
        $expression = (string) $this->expression;
        if (strpos($expression, '*') === false) {
            return (string) $row->{$this->column} === $expression;
        } else {
            $parts = array();
            foreach (preg_split('/\*/', $expression) as $part) {
                $parts[] = preg_quote($part);
            }
            return preg_match('/^' . implode('.*', $parts) . '$/', $row->{$this->column});
        }
    }
}
