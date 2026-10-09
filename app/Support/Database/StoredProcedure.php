<?php

namespace App\Support\Database;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use PDO;

class StoredProcedure
{
    /**
     * Execute a stored procedure and return all rows of the first result set.
     */
    public static function select(string $name, array $params = [], ?string $connection = null): array
    {
        return DB::connection($connection)->select(
            self::buildSql($name, $params),
            array_values($params)
        );
    }

    /**
     * Execute a stored procedure and return only the first row, or null if none.
     */
    public static function first(string $name, array $params = [], ?string $connection = null): ?object
    {
        return self::select($name, $params, $connection)[0] ?? null;
    }

    /**
     * Execute a stored procedure that returns multiple result sets
     * (e.g. dashboard widgets) and return each set as an array of rows.
     */
    public static function multiple(string $name, array $params = [], ?string $connection = null): array
    {
        $statement = DB::connection($connection)->getPdo()->prepare(self::buildSql($name, $params));
        $statement->execute(array_values($params));

        $resultSets = [];

        do {
            if ($statement->columnCount() > 0) {
                $resultSets[] = $statement->fetchAll(PDO::FETCH_OBJ);
            }
        } while ($statement->nextRowset());

        return $resultSets;
    }

    /**
     * Build an "EXEC usp_name @param1 = ?, @param2 = ?" statement.
     * Values are always passed as bindings, never concatenated into the SQL.
     */
    private static function buildSql(string $name, array $params): string
    {
        self::assertSafeName($name);

        $placeholders = [];

        foreach (array_keys($params) as $key) {
            self::assertSafeName($key);
            $placeholders[] = "@{$key} = ?";
        }

        return trim("EXEC {$name} " . implode(', ', $placeholders));
    }

    /**
     * Guard against injection through procedure or parameter names.
     */
    private static function assertSafeName(string $name): void
    {
        if (! preg_match('/^[A-Za-z_][A-Za-z0-9_.]*$/', $name)) {
            throw new InvalidArgumentException("Invalid stored procedure or parameter name: {$name}");
        }
    }
}