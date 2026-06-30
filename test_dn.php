<?php
require_once(__DIR__ . '/private/config.php');
$ldap_conn = ldap_connect(get_ldap_uri());
ldap_set_option($ldap_conn, LDAP_OPT_PROTOCOL_VERSION, 3);
ldap_set_option($ldap_conn, LDAP_OPT_REFERRALS, 0);
ldap_bind($ldap_conn, $ldap_admuser, $ldap_admpwd);

$filter = "(sAMAccountName=jpastor)";
$root_dn = preg_replace('/^.*?DC=/i', 'DC=', $ldap_dn);
$search = @ldap_search($ldap_conn, $root_dn, $filter, ['dn', 'logonworkstation', 'userworkstations']);
$entries = ldap_get_entries($ldap_conn, $search);

print_r($entries[0]);

ldap_unbind($ldap_conn);
