<?php

namespace LDAP;

/**
 * Centralized LDAP connection manager.
 *
 * Replaces ad-hoc ldap_connect + set_option + bind + unbind boilerplate
 * scattered across lib/*.php with a single injectable class.
 *
 * All ldap_set_option calls (PROTOCOL_VERSION, REFERRALS) live exclusively
 * here. TLS is enforced via LDAPTLS_REQCERT env var set in private/config.php.
 *
 * Note: Class named "Client" (not "Connection") because PHP 8.1+ defines
 * a native final class LDAP\Connection in the LDAP extension.
 */
class Client
{
    /** @var mixed LDAP connection resource handle */
    private $resource;

    /**
     * @param string $host     LDAP server hostname
     * @param int    $port     LDAP server port
     * @param string $bindDn   Distinguished name for bind
     * @param string $password Password for bind DN
     * @param string $scheme   URI scheme: 'ldap' or 'ldaps' (default 'ldap')
     */
    public function __construct(string $host, int $port, string $bindDn, string $password, string $scheme = 'ldap')
    {
        $uri = "{$scheme}://{$host}:{$port}";
        $conn = @ldap_connect($uri);

        if (!$conn) {
            return;
        }

        ldap_set_option($conn, LDAP_OPT_PROTOCOL_VERSION, 3);
        ldap_set_option($conn, LDAP_OPT_REFERRALS, 0);

        ldap_bind($conn, $bindDn, $password);

        $this->resource = $conn;
    }

    /**
     * Returns the underlying LDAP connection resource for native ldap_* operations.
     *
     * @return mixed The LDAP resource handle (or null if connection failed)
     */
    public function getResource(): mixed
    {
        return $this->resource;
    }

    /**
     * Creates a Client from global configuration ($GLOBALS).
     *
     * Uses get_ldap_uri() for host resolution (supports multiple hosts,
     * LDAPS protocol, active DC caching). Falls back to $GLOBALS for
     * credentials.
     *
     * @return self A bound, configured Client instance
     * @throws \RuntimeException when any required global config key is missing
     */
    public static function factory(): self
    {
        if (!function_exists('get_ldap_uri')) {
            throw new \RuntimeException('get_ldap_uri() not available — ensure private/config.php is loaded');
        }

        $uri = get_ldap_uri();
        $parts = parse_url($uri);

        $host = $parts['host'] ?? '';
        $port = $parts['port'] ?? 389;
        $scheme = $parts['scheme'] ?? 'ldap';

        if (empty($host)) {
            throw new \RuntimeException('Could not resolve LDAP host from get_ldap_uri()');
        }
        if (empty($GLOBALS['ldap_admuser'])) {
            throw new \RuntimeException('Missing required config: ldap_admuser');
        }
        if (empty($GLOBALS['ldap_admpwd'])) {
            throw new \RuntimeException('Missing required config: ldap_admpwd');
        }

        return new self(
            $host,
            (int) $port,
            $GLOBALS['ldap_admuser'],
            $GLOBALS['ldap_admpwd'],
            $scheme
        );
    }

    /**
     * Releases the LDAP connection resource on destruction.
     */
    public function __destruct()
    {
        if ($this->resource) {
            @ldap_unbind($this->resource);
            $this->resource = null;
        }
    }
}
