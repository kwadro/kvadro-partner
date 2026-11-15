<?php

namespace App\Service\Referral;

/**
 * Extracts partner referral parts from a URL or path.
 *
 * Supported path forms (segment after the page URL is dynamic, not fixed "pr"):
 * - /{pageCode}/{linkKey}          e.g. /pb/h4a69m69e6
 * - .../category/item/{pageCode}/{linkKey}
 *
 * Also accepts a combined referral key: {pageCode}{linkKey} (e.g. pbh4a69m69e6).
 */
class ReferralPathParser
{
    /**
     * @return array{pageCode: string, linkKey: string, referralKey: string}|null
     */
    public function parseUrl(string $url): ?array
    {
        $url = trim($url);
        if ('' === $url) {
            return null;
        }

        $path = parse_url($url, PHP_URL_PATH);
        if (\is_string($path) && '' !== $path) {
            $fromPath = $this->parsePath($path);
            if (null !== $fromPath) {
                return $fromPath;
            }
        }

        $query = parse_url($url, PHP_URL_QUERY);
        if (\is_string($query) && '' !== $query) {
            parse_str($query, $params);
            if (\is_array($params)) {
                foreach (['pr', 'ref', 'bb_ref', 'key'] as $name) {
                    if (!empty($params[$name]) && \is_string($params[$name])) {
                        $fromKey = $this->parseReferralKey($params[$name]);
                        if (null !== $fromKey) {
                            return $fromKey;
                        }
                    }
                }
            }
        }

        return null;
    }

    /**
     * @return array{pageCode: string, linkKey: string, referralKey: string}|null
     */
    public function parsePath(string $path): ?array
    {
        $path = trim($path);
        if ('' === $path) {
            return null;
        }

        // Prefer the trailing /{code}/{key} pair (code replaces legacy "pr").
        if (preg_match('#/([a-z0-9]{2})/([a-zA-Z0-9_-]{3,64})/?$#i', $path, $matches)) {
            return $this->parts(strtolower($matches[1]), $matches[2]);
        }

        // Fallback: last /{code}/{key} occurrence anywhere in the path.
        if (preg_match_all('#/([a-z0-9]{2})/([a-zA-Z0-9_-]{3,64})(?=/|$)#i', $path, $matches, PREG_SET_ORDER)) {
            $last = $matches[\count($matches) - 1];

            return $this->parts(strtolower($last[1]), $last[2]);
        }

        return null;
    }

    /**
     * @return array{pageCode: string, linkKey: string, referralKey: string}|null
     */
    public function parseReferralKey(string $referralKey): ?array
    {
        $referralKey = trim($referralKey);
        if (1 !== preg_match('/^[a-zA-Z0-9_-]{5,66}$/', $referralKey)) {
            return null;
        }

        $pageCode = strtolower(substr($referralKey, 0, 2));
        $linkKey = substr($referralKey, 2);
        if (1 !== preg_match('/^[a-z0-9]{2}$/', $pageCode)) {
            return null;
        }
        if (1 !== preg_match('/^[a-zA-Z0-9_-]{3,64}$/', $linkKey)) {
            return null;
        }

        return $this->parts($pageCode, $linkKey);
    }

    /**
     * @return array{pageCode: string, linkKey: string, referralKey: string}
     */
    private function parts(string $pageCode, string $linkKey): array
    {
        return [
            'pageCode' => $pageCode,
            'linkKey' => $linkKey,
            'referralKey' => $pageCode.$linkKey,
        ];
    }
}
