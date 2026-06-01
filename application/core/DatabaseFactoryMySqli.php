<?php

/**
 * Class DatabaseFactory
 *
 * Use it like this:
 * $database = DatabaseFactory::getFactory()->getConnection();
 *
 * That's my personal favourite when creating a database connection.
 * It's a slightly modified version of Jon Raphaelson's excellent answer on StackOverflow:
 * http://stackoverflow.com/questions/130878/global-or-singleton-for-database-connection
 *
 * Full quote from the answer:
 *
 * "Then, in 6 months when your app is super famous and getting dugg and slashdotted and you decide you need more than
 * a single connection, all you have to do is implement some pooling in the getConnection() method. Or if you decide
 * that you want a wrapper that implements SQL logging, you can pass a PDO subclass. Or if you decide you want a new
 * connection on every invocation, you can do do that. It's flexible, instead of rigid."
 *
 * Thanks! Big up, mate!
 */
class DatabaseFactoryMySqli
{
    private static $factory;
    private $mysqli;

    public static function getFactory()
    {
        if (!self::$factory) {
            self::$factory = new DatabaseFactoryMySqli();
        }
        return self::$factory;
    }

    public function getConnectionMySqli() {
        if (!$this->mysqli) {

            mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
            try {
            $this->mysqli = new mysqli("localhost", "root", "", "huge");
            $this->mysqli->set_charset("utf8mb4");
            } catch(Exception $e) {
            error_log($e->getMessage());
            exit('Error connecting to database'); //Should be a message a typical user could understand
            }
            
        }
        return $this->mysqli;
    }
}
