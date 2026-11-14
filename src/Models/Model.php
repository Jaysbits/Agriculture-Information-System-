<?php
declare(strict_types=1);

/**
 * =====================================================================
 * MODEL LAYER — shared base class
 * =====================================================================
 *
 * Every entity in the system gets its own model file inside src/Models/.
 * Each model owns the database logic for one table (list / search /
 * sort / pagination, create, update, delete and the reads used by the
 * detail pages). The HTML lives separately in public/<entity>/*.php —
 * those "view" files never build SQL themselves, they only call the
 * static methods exposed here.
 *
 * This base class holds the small amount of SQL glue that is identical
 * for every entity so the individual model files only describe their
 * own table and business rules.
 */
abstract class Model
{
    /** Table backing the model. */
    protected static string $table = '';

    /** Primary-key column of the table. */
    protected static string $primaryKey = 'id';

    /**
     * Run a filtered/sorted query with pagination.
     *
     * The row query must end with "LIMIT :limit OFFSET :offset"; those
     * two named placeholders are appended automatically.
     *
     * @param array<string,mixed> $params
     * @return array{rows: array<int,array<string,mixed>>, pager: array<string,mixed>}
     */
    protected static function paginateQuery(string $countSql, string $rowsSql, array $params, int $page): array
    {
        $total = Database::count($countSql, $params);
        $pager = paginate($total, $page);

        $rows = Database::all($rowsSql, array_merge($params, [
            ':limit'  => $pager['limit'],
            ':offset' => $pager['offset'],
        ]));

        return ['rows' => $rows, 'pager' => $pager];
    }

    /**
     * Build a safe ORDER BY fragment. Only whitelisted map keys are ever
     * used as columns, and the direction is forced to ASC/DESC, so the
     * value cannot be used for SQL injection.
     */
    protected static function orderBy(string $column, string $dir, string $tieBreak): string
    {
        return $column . ' ' . (strtolower($dir) === 'desc' ? 'DESC' : 'ASC') . ', ' . $tieBreak;
    }

    /**
     * Delete one row by primary key.
     *
     * Foreign-key / trigger failures are translated into a friendly
     * message instead of leaking SQL to the page.
     *
     * @return array{ok:bool, deleted:bool, error:?string}
     */
    public static function delete(int $id): array
    {
        try {
            $deleted = Database::run(
                'DELETE FROM ' . static::$table . ' WHERE ' . static::$primaryKey . ' = :id',
                [':id' => $id]
            )->rowCount();

            return ['ok' => true, 'deleted' => $deleted > 0, 'error' => null];
        } catch (PDOException $e) {
            error_log('[' . static::$table . ' delete] ' . $e->getMessage());
            return ['ok' => false, 'deleted' => false, 'error' => friendly_db_error($e)];
        }
    }
}
