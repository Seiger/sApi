<?php namespace Seiger\sApi\Support;

/**
 * Normalizes raw API request payloads before controller validation.
 *
 * External accounting systems may prepend invisible bytes such as UTF-8 BOM,
 * zero-width no-break space, or control whitespace before JSON payloads. The
 * helper keeps that tolerance in one shared place so endpoint controllers and
 * access logging parse the same body representation.
 *
 * @since 1.0.5
 */
final class RequestPayload
{
    /**
     * Resolve the request body to an array payload.
     *
     * Raw JSON is attempted first after cleaning transport artifacts from the
     * body edges. If the framework JSON bag is still usable it is accepted as a
     * secondary source, and regular request input remains the final fallback for
     * non-JSON clients.
     *
     * @param object $request HTTP request object compatible with Laravel input APIs.
     *
     * @return array<int|string, mixed> Parsed request payload.
     *
     * @since 1.0.5
     */
    public static function array(object $request): array
    {
        $content = self::clean((string)$request->getContent());
        if ($content !== '') {
            $decoded = json_decode($content, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return $decoded;
            }
        }

        try {
            $payload = $request->json()->all();
            if (is_array($payload) && $payload !== []) {
                return $payload;
            }
        } catch (\Throwable) {
            // Raw content fallback above is intentionally tolerant to invisible leading bytes.
        }

        $payload = $request->all();

        return is_array($payload) ? $payload : [];
    }

    /**
     * Strip invisible transport bytes from the beginning and end of a body.
     *
     * The method intentionally does not touch the middle of the payload: valid
     * JSON string content must remain byte-for-byte intact while leading BOM,
     * NBSP, and control whitespace sent by integration clients are ignored.
     *
     * @param string $content Raw request body.
     *
     * @return string Body content safe for JSON decoding.
     *
     * @since 1.0.5
     */
    public static function clean(string $content): string
    {
        if ($content === '') {
            return '';
        }

        $edgeBytes = '(?:[\x00-\x20\x7F]|\xC2\xA0|\xEF\xBB\xBF)+';
        $content = (string)preg_replace('/^' . $edgeBytes . '/', '', $content);
        $content = (string)preg_replace('/' . $edgeBytes . '$/', '', $content);

        return $content;
    }
}
