<?php
/**
 * Isolated regression check for the two cancellation callers. No application
 * loader, session, database connection, notifications or records are used.
 * Run: php scripts/check_courier_cancellation.php
 */
error_reporting(E_ALL);
set_error_handler(function ($severity, $message, $file, $line) {
    throw new ErrorException($message, 0, $severity, $file, $line);
});

class Conexion
{
    public static $lastStatement;
    public static $executeResult = true;
    public $sql;
    public $bindings = [];

    public function __construct()
    {
        self::$lastStatement = $this;
    }

    public function cdp_query($sql)
    {
        $this->sql = $sql;
    }

    public function bind($key, $value)
    {
        $this->bindings[$key] = $value;
    }

    public function cdp_execute()
    {
        return self::$executeResult;
    }
}

require_once dirname(__DIR__) . '/helpers/querys.php';

function cancellationCheck($condition, $message)
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

foreach ([
    [['id' => 42], null],
    [['id' => 42, 'message' => null], null],
    [['id' => 42, 'message' => ''], ''],
    [['id' => 42, 'message' => '0'], '0'],
    [['id' => 42, 'message' => 'Customer requested cancellation'], 'Customer requested cancellation'],
] as $case) {
    [$input, $expectedReason] = $case;
    cancellationCheck(cdp_deleteCourier($input) === true, 'Successful cancellation must return true.');
    $statement = Conexion::$lastStatement;
    cancellationCheck($statement->bindings === [':id' => 42, ':reason_cancel' => $expectedReason], 'Cancellation must preserve the supplied reason or use NULL when omitted.');
    cancellationCheck(strpos($statement->sql, "status_courier ='21'") !== false, 'Cancellation must keep status 21.');
    cancellationCheck(strpos($statement->sql, 'order_id=:id') !== false, 'Cancellation must target the supplied shipment.');
}

Conexion::$executeResult = false;
cancellationCheck(cdp_deleteCourier(['id' => 42]) === false, 'Database failure must remain a failure.');

restore_error_handler();
echo "Courier cancellation regression passed (5 reason cases; database failure preserved).\n";
