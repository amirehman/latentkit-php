<?php

declare(strict_types=1);

namespace LatentKit\Streaming;

/**
 * Turns raw response chunks into server-sent events.
 *
 * Chunks arrive at arbitrary byte boundaries, so a line, an event, or a multi-byte
 * character can be split across two of them. The parser buffers until a blank line
 * closes an event and only then reports it.
 */
final class SseParser
{
    /**
     * @param iterable<string> $chunks
     *
     * @return \Generator<int, array{event: ?string, data: string}>
     */
    public static function parse(iterable $chunks): \Generator
    {
        $buffer = '';
        foreach ($chunks as $chunk) {
            // Normalise the whole buffer, not the chunk: a "\r\n" split across two chunks
            // leaves a lone "\r" at the end of the first, which only becomes a line
            // ending once the next chunk arrives.
            $buffer = str_replace("\r\n", "\n", $buffer . $chunk);
            while (($boundary = strpos($buffer, "\n\n")) !== false) {
                $block = substr($buffer, 0, $boundary);
                $buffer = substr($buffer, $boundary + 2);
                $event = self::event($block);
                if ($event !== null) {
                    yield $event;
                }
            }
        }

        // A stream cut off without the closing blank line still carries a final event.
        $event = self::event(rtrim($buffer, "\r\n"));
        if ($event !== null) {
            yield $event;
        }
    }

    /**
     * @return array{event: ?string, data: string}|null
     */
    private static function event(string $block): ?array
    {
        $name = null;
        $data = [];
        foreach (explode("\n", $block) as $line) {
            if ($line === '' || $line[0] === ':') {
                continue;
            }
            $separator = strpos($line, ':');
            $field = $separator === false ? $line : substr($line, 0, $separator);
            $value = $separator === false ? '' : substr($line, $separator + 1);
            if (str_starts_with($value, ' ')) {
                $value = substr($value, 1);
            }
            if ($field === 'event') {
                $name = $value !== '' ? $value : null;
            } elseif ($field === 'data') {
                $data[] = $value;
            }
        }

        return $data === [] ? null : ['event' => $name, 'data' => implode("\n", $data)];
    }
}
