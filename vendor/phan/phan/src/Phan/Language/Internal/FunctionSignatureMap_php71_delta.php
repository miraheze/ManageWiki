<?php // phpcs:ignoreFile

/**
 * This contains the information needed to convert the function signatures for php 7.1 to php 7.0 (and vice versa)
 *
 * This has two sections.
 * The 'new' section contains function/method names from FunctionSignatureMap (And alternates, if applicable) that do not exist in php7.0 or have different signatures in php 7.1.
 *   If they were just updated, the function/method will be present in the 'added' signatures.
 * The 'old' signatures contains the signatures that are different in php 7.0.
 *   Functions are expected to be removed only in major releases of php. (e.g. php 7.0 removed various functions that were deprecated in 5.6)
 *
 * @see FunctionSignatureMap.php
 *
 * @phan-file-suppress PhanPluginMixedKeyNoKey (read by Phan when analyzing this file)
 */
return [
'new' => [
'Closure::fromCallable' => ['Closure', 'callable'=>'callable'],
'SQLite3::createFunction' => ['bool', 'name'=>'string', 'callback'=>'callable', 'argument_count='=>'int', 'flags='=>'int'],
'curl_multi_errno' => ['int', 'multi_handle'=>'resource'],
'curl_share_errno' => ['int', 'share_handle'=>'resource'],
'curl_share_strerror' => ['string', 'error_code'=>'int'],
'get_headers' => ['array<int|string,array|string>|false', 'url'=>'string', 'associative='=>'bool', 'context='=>'resource'],
'getenv\'1' => ['array<string,string>'],
'getopt' => ['array<string,string>|array<string,false>|array<string,list<mixed>>', 'short_options'=>'string', 'long_options='=>'array', '&w_rest_index='=>'int'],
'hash_hkdf' => ['string|false', 'algo'=>'string', 'key'=>'string', 'length='=>'int', 'info='=>'string', 'salt='=>'string'],
'is_iterable' => ['bool', 'value'=>'mixed'],
'openssl_get_curve_names' => ['list<string>'],
'pcntl_async_signals' => ['bool', 'enable='=>'bool'],
'pcntl_signal_get_handler' => ['int|callable', 'signal'=>'int'],
'pg_fetch_all' => ['array<int,array>|false', 'result'=>'resource', 'mode='=>'int'],
'pg_last_error' => ['string', 'connection='=>'resource', 'operation='=>'int'],
'pg_select' => ['string|bool', 'connection'=>'resource', 'table_name'=>'string', 'conditions'=>'array', 'flags='=>'int', 'mode='=>'int'],
'sapi_windows_cp_conv' => ['string', 'in_codepage'=>'int|string', 'out_codepage'=>'int|string', 'subject'=>'string'],
'sapi_windows_cp_get' => ['int', 'kind='=>'string'],
'sapi_windows_cp_is_utf8' => ['bool'],
'sapi_windows_cp_set' => ['bool', 'codepage'=>'int'],
'session_create_id' => ['string', 'prefix='=>'string'],
'session_gc' => ['int|false'],
'unpack' => ['array|false', 'format'=>'string', 'string'=>'string', 'offset='=>'int'],
],
'old' => [
'SQLite3::createFunction' => ['bool', 'name'=>'string', 'callback'=>'callable', 'argument_count='=>'int'],
'get_headers' => ['array|false', 'url'=>'string', 'associative='=>'bool'],
'getopt' => ['array<string,string>|array<string,false>|array<string,list<string|false>>', 'short_options'=>'string', 'long_options='=>'array'],
'pg_fetch_all' => ['array', 'result'=>'resource'],
'pg_last_error' => ['string', 'connection='=>'resource'],
'pg_select' => ['mixed', 'connection'=>'resource', 'table_name'=>'string', 'conditions'=>'array', 'flags='=>'int'],
'unpack' => ['array', 'format'=>'string', 'string'=>'string'],
],
];
