<?php

namespace Negotiation;

use Negotiation\Exception\InvalidHeader;

abstract class AbstractNegotiator
{
    /**
     * @param string $header     A string containing an `Accept|Accept-*` header.
     * @param array  $priorities A set of server priorities.
     *
     * @return AcceptHeader best matching type
     */
    public function getBest($header, array $priorities)
    {
        if (empty($priorities)) {
            throw new \InvalidArgumentException('A set of server priorities should be given.');
        }

        if (!$header) {
            throw new \InvalidArgumentException('The header string should not be empty.');
        }

        $headers    = $this->parseHeader($header);
        $headers    = array_map(array($this, 'acceptFactory'), $headers);
        $priorities = array_map(array($this, 'acceptFactory'), $priorities);

        $matches         = $this->findMatches($headers, $priorities);
        $specificMatches = array_reduce($matches, 'Negotiation\Match::reduce', []);

        usort($specificMatches, 'Negotiation\Match::compare');

        $match = array_shift($specificMatches);

        return null === $match ? null : $priorities[$match->index];
    }

    /**
     * @param AcceptHeader $header
     * @param AcceptHeader $priority
     * @param integer      $index
     *
     * @return Match|null Headers matched
     */
    protected function match(AcceptHeader $header, AcceptHeader $priority, $index)
    {
        $ac = $header->getType();
        $pc = $priority->getType();

        $equal = !strcasecmp($ac, $pc);

        if ($equal || $ac == '*') {
            $score = 1 * $equal;

            return new Match($header->getQuality(), $score, $index);
        }

        return null;
    }

    /**
     * @param string $header accept header part or server priority
     *
     * @return AcceptHeader Parsed header object
     */
    abstract protected function acceptFactory($header);

    /**
     * @param string $header A string that contains an `Accept*` header.
     *
     * @return AcceptHeader[]
     */
    private function parseHeader($header)
    {
        $res = preg_match_all('/(?:[^,"]*+(?:"[^"]*+")?)+[^,"]*+/', $header, $matches);

        if (!$res) {
            throw new InvalidHeader(sprintf('Failed to parse accept header: "%s"', $header));
        }

        return array_values(array_filter(array_map('trim', $matches[0])));
    }

    /**
     * @param AcceptHeader[] $headerParts
     * @param Priority[]     $priorities  Configured priorities
     *
     * @return Match[] Headers matched
     */
    private function findMatches(array $headerParts, array $priorities)
    {
        $matches = [];
        foreach ($priorities as $index => $p) {
            foreach ($headerParts as $h) {
                if ($match = $this->match($h, $p, $index)) {
                    $matches[] = $match;
                }
            }
        }

        return $matches;
    }
}
