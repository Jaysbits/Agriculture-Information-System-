<?php
declare(strict_types=1);

/**
 * =====================================================================
 * MODEL LAYER — shared base class
 * =====================================================================
 *
 * Every entity in the system gets its own model file inside src/Models/.
 * Each model owns the database logic for one table and is used as a
 * normal object:
 *
 *     $farmer = new Farmer();
 *     $farmer->all(['address=ilike.%Cebu%'], ['order' => 'last_name']);
 *     $farmer->find(3);
 *     $farmer->create($data);
 *     $farmer->update(3, $data);
 *     $farmer->delete(3);
 *
 * The generic CRUD methods below delegate to the Database class, which
 * understands the PostgREST-style filter strings used by Supabase:
 * 'column=eq.value', 'column=ilike.%x%', 'or=(a.ilike.%x%,b.ilike.%x%)'.
 *
 * The HTML lives in public/<entity>/*.php and never builds SQL itself.
 */
abstract class Model
{
    protected Database $db;

    /** Table backing the model. */
    protected string $table = '';

    /** Primary-key column of the table. */
    protected string $primaryKey = 'id';

    public function __construct(?Database $db = null)
    {
        $this->db = $db ?? new Database();
    }

    /**
     * SELECT rows from the model's table.
     *
     * @param array<int,string> $filters  e.g. ['farmer_id=eq.5', 'address=ilike.%Cebu%']
     * @param array{order?:string, limit?:int, offset?:int} $options
     * @return array<int,array<string,mixed>>
     */
    public function all(array $filters = [], array $options = []): array
    {
        return $this->db->select($this->table, '*', $filters, $options);
    }

    /** One row by primary key, or null when it does not exist. */
    public function find(int $id): ?array
    {
        return $this->db->find($this->table, $id, $this->primaryKey);
    }

    /**
     * INSERT a row and return it.
     *
     * @param array<string,mixed> $data column => value
     * @return array<string,mixed>
     */
    public function create(array $data): array
    {
        return $this->db->insert($this->table, $data);
    }

    /**
     * UPDATE one row by primary key and return the changed rows.
     *
     * @param array<string,mixed> $data column => value
     * @return array<int,array<string,mixed>>
     */
    public function update(int $id, array $data): array
    {
        return $this->db->update($this->table, $data, [$this->primaryKey . '=eq.' . $id]);
    }

    /**
     * DELETE one row by primary key.
     *
     * Foreign-key / trigger failures are translated into a friendly
     * message instead of leaking SQL to the page.
     *
     * @return array{ok:bool, deleted:bool, error:?string}
     */
    public function delete(int $id): array
    {
        return $this->db->delete($this->table, [$this->primaryKey . '=eq.' . $id]);
    }

    /** Number of rows, optionally filtered. */
    public function count(array $filters = []): int
    {
        return count($this->db->select($this->table, $this->primaryKey, $filters));
    }

    /**
     * Run a filtered/sorted query with pagination.
     *
     * The row query must end with "LIMIT :limit OFFSET :offset"; those
     * two named placeholders are appended automatically.
     *
     * @param array<string,mixed> $params
     * @return array{rows: array<int,array<string,mixed>>, pager: array<string,mixed>}
     */
    protected function paginateQuery(string $countSql, string $rowsSql, array $params, int $page): array
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
    protected function orderBy(string $column, string $dir, string $tieBreak): string
    {
        return $column . ' ' . (strtolower($dir) === 'desc' ? 'DESC' : 'ASC') . ', ' . $tieBreak;
    }
}
