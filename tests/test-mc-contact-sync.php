<?php
/**
 * Lightweight, dependency-free unit tests for Mc_Contact_Sync::map_fields().
 * Run: php tests/test-mc-contact-sync.php
 *
 * map_fields() is a pure function, so no WordPress runtime is required.
 */

define('MC_CONTACT_SYNC_TEST', true);
require __DIR__ . '/../includes/class-mc-contact-sync.php';

$failures = 0;
function check($label, $actual, $expected)
{
    global $failures;
    $a = json_encode($actual);
    $e = json_encode($expected);
    if ($a === $e) {
        echo "PASS  $label\n";
    } else {
        $failures++;
        echo "FAIL  $label\n      expected: $e\n      actual:   $a\n";
    }
}

// 1. Standard fields map straight through.
check(
    'standard fields',
    Mc_Contact_Sync::map_fields(
        array(
            array('wordpress_attribute' => 'your-email', 'mailercloud_attribute' => 'email'),
            array('wordpress_attribute' => 'your-name',  'mailercloud_attribute' => 'name'),
        ),
        array('your-email' => 'jane@example.com', 'your-name' => 'Jane')
    ),
    array('email' => 'jane@example.com', 'name' => 'Jane')
);

// 2. custom_fields_ prefix nests under custom_fields[<id>].
check(
    'custom field nesting',
    Mc_Contact_Sync::map_fields(
        array(
            array('wordpress_attribute' => 'your-email', 'mailercloud_attribute' => 'email'),
            array('wordpress_attribute' => 'phone',      'mailercloud_attribute' => 'custom_fields_123'),
        ),
        array('your-email' => 'jane@example.com', 'phone' => '+91999')
    ),
    array('email' => 'jane@example.com', 'custom_fields' => array('123' => '+91999'))
);

// 3. tags row decodes the JSON literal in mailercloud_attribute.
check(
    'tags row',
    Mc_Contact_Sync::map_fields(
        array(
            array('wordpress_attribute' => 'your-email', 'mailercloud_attribute' => 'email'),
            array('wordpress_attribute' => 'tags',       'mailercloud_attribute' => '["t1","t2"]'),
        ),
        array('your-email' => 'jane@example.com')
    ),
    array('email' => 'jane@example.com', 'tags' => array('t1', 't2'))
);

// 4. Missing / empty source values are skipped (no PHP notices, no empty keys).
check(
    'missing + empty values skipped',
    Mc_Contact_Sync::map_fields(
        array(
            array('wordpress_attribute' => 'your-email', 'mailercloud_attribute' => 'email'),
            array('wordpress_attribute' => 'your-name',  'mailercloud_attribute' => 'name'),
            array('wordpress_attribute' => 'absent',     'mailercloud_attribute' => 'last_name'),
        ),
        array('your-email' => 'jane@example.com', 'your-name' => '')
    ),
    array('email' => 'jane@example.com')
);

// 5. Empty / malformed mapping returns empty payload.
check('empty mapping', Mc_Contact_Sync::map_fields(array(), array('a' => 'b')), array());

echo "\n" . ($failures === 0 ? "ALL PASS\n" : "$failures FAILURE(S)\n");
exit($failures === 0 ? 0 : 1);
