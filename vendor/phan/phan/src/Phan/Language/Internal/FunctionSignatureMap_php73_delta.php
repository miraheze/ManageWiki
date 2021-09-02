<?php // phpcs:ignoreFile

/**
 * This contains the information needed to convert the function signatures for php 7.3 to php 7.2 (and vice versa)
 *
 * This has two sections.
 * The 'new' section contains function/method names from FunctionSignatureMap (And alternates, if applicable) that do not exist in php7.2 or have different signatures in php 7.3.
 *   If they were just updated, the function/method will be present in the 'added' signatures.
 * The 'old' signatures contains the signatures that are different in php 7.2.
 *   Functions are expected to be removed only in major releases of php. (e.g. php 7.0 removed various functions that were deprecated in 5.6)
 *
 * @see FunctionSignatureMap.php
 *
 * @phan-file-suppress PhanPluginMixedKeyNoKey (read by Phan when analyzing this file)
 *
 * TODO: Fix GMP signatures for gmp_div in 7.2, update other deltas.
 */
return [
'new' => [
'array_key_first' => ['int|string|null', 'array'=>'array'],
'array_key_last' => ['int|string|null', 'array'=>'array'],
'DateTime::createFromImmutable' => ['static', 'datetime'=>'DateTimeImmutable'],
'fpm_get_status' => ['array|false'],
'gmp_binomial' => ['GMP|false', 'n'=>'GMP|string|int', 'k'=>'int'],
'gmp_lcm' => ['GMP', 'num1'=>'GMP|string|int', 'num2'=>'GMP|string|int'],
'gmp_perfect_power' => ['bool', 'num'=>'GMP|string|int'],
'gmp_kronecker' => ['int', 'num1'=>'GMP|string|int', 'num2'=>'GMP|string|int'],
'gc_status' => ['array{runs:int,collected:int,threshold:int,roots:int}'],
'hrtime' => ['array{0:int,1:int}|int|false', 'as_number='=>'bool'],
'is_countable' => ['bool', 'value'=>'mixed'],
'JsonException::__clone' => ['void'],
'JsonException::__construct' => ['void'],
'JsonException::__toString' => ['string'],
'JsonException::__wakeup' => ['void'],
'JsonException::getCode' => ['int'],
'JsonException::getFile' => ['string'],
'JsonException::getLine' => ['int'],
'JsonException::getMessage' => ['string'],
'JsonException::getPrevious' => ['?Throwable'],
'JsonException::getTrace' => ['array<int,array<string,mixed>>'],
'JsonException::getTraceAsString' => ['string'],
'ldap_add_ext' => ['resource|false', 'ldap'=>'resource', 'dn'=>'string', 'entry'=>'array', 'controls='=>'array'],
'ldap_bind_ext' => ['resource|false', 'ldap'=>'resource', 'dn='=>'string', 'password='=>'?string', 'controls='=>'?array'],
'ldap_mod_add_ext' => ['resource|false', 'ldap'=>'resource', 'dn'=>'string', 'entry'=>'array', 'controls='=>'array'],
'ldap_mod_del_ext' => ['resource|false', 'ldap'=>'resource', 'dn'=>'string', 'entry'=>'array', 'controls='=>'array'],
'ldap_mod_replace_ext' => ['resource|false', 'ldap'=>'resource', 'dn'=>'string', 'entry'=>'array', 'controls='=>'array'],
'ldap_rename_ext' => ['resource|false', 'ldap'=>'resource', 'dn'=>'string', 'new_rdn'=>'string', 'new_parent'=>'string', 'delete_old_rdn'=>'bool', 'controls='=>'array'],
'net_get_interfaces' => ['array<string,array<string,mixed>>|false'],
'openssl_pkey_derive' => ['string|false', 'public_key'=>'mixed', 'private_key'=>'mixed', 'key_length='=>'?int'],
'session_set_cookie_params\'1' => ['bool', 'options'=>'array{lifetime?:int,path?:string,domain?:?string,secure?:bool,httponly?:bool}'],
'socket_wsaprotocol_info_export' => ['string|false', 'socket'=>'resource', 'process_id'=>'int'],
'socket_wsaprotocol_info_import' => ['resource|false', 'info_id'=>'string'],
'socket_wsaprotocol_info_release' => ['bool', 'info_id'=>'string'],
'SplPriorityQueue::isCorrupted' => ['bool'],
],
'old' => [
],
];
