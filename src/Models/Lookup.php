<?php
declare(strict_types=1);

/**
 * =====================================================================
 * MODEL — Lookup  (controlled dropdown reference tables)
 * =====================================================================
 * The seven reference tables that back the controlled dropdowns
 * (soil types, irrigation types, planting statuses, crop categories,
 * seasons, quality grades, harvest remarks). Each table is a simple
 * id + description pair; core tables store the numeric id.
 *
 * This model owns the registry, the usage counts, the duplicate check
 * and add / update / delete. HTML lives in public/lookups.php.
 *
 * Used as an object:
 *
 *     $lookup = new Lookup();
 *     $lookup->tables();
 *     $lookup->rows('soil_type');
 *     $lookup->save('soil_type', 'add', 'Clay', 0);
 *     $lookup->delete('soil_type', 4);
 */
final class Lookup
{
    private Database $db;

    public function __construct(?Database $db = null)
    {
        $this->db = $db ?? new Database();
    }

    /**
     * Registry of reference tables. Keys are the ?table= values.
     *
     * @return array<string,array<string,mixed>>
     */
    public function tables(): array
    {
        return [
            'soil_type' => [
                'label' => 'Soil types', 'icon' => 'layers', 'id' => 'soil_type_id', 'name' => 'soil_type_name',
                'usage' => ['table' => 'farm', 'column' => 'soil_type_id', 'label' => 'farms'],
            ],
            'irrigation_type' => [
                'label' => 'Irrigation types', 'icon' => 'droplet', 'id' => 'irrigation_id', 'name' => 'irrigation_name',
                'usage' => ['table' => 'farm', 'column' => 'irrigation_id', 'label' => 'farms'],
            ],
            'planting_status' => [
                'label' => 'Planting statuses', 'icon' => 'kanban', 'id' => 'status_id', 'name' => 'status_name',
                'usage' => ['table' => 'planting', 'column' => 'status_id', 'label' => 'plantings'],
            ],
            'crop_category' => [
                'label' => 'Crop categories', 'icon' => 'tags', 'id' => 'category_id', 'name' => 'category_name',
                'usage' => ['table' => 'crop', 'column' => 'category_id', 'label' => 'crops'],
            ],
            'season' => [
                'label' => 'Seasons', 'icon' => 'cloud-sun', 'id' => 'season_id', 'name' => 'season_name',
                'usage' => ['table' => 'crop', 'column' => 'season_id', 'label' => 'crops'],
            ],
            'quality_grade' => [
                'label' => 'Quality grades', 'icon' => 'patch-check', 'id' => 'grade_id', 'name' => 'grade_name',
                'usage' => ['table' => 'harvest', 'column' => 'grade_id', 'label' => 'harvests'],
            ],
            'harvest_remark' => [
                'label' => 'Harvest remarks', 'icon' => 'chat-left-text', 'id' => 'remark_id', 'name' => 'remark_description',
                'usage' => ['table' => 'harvest', 'column' => 'remark_id', 'label' => 'harvests'],
            ],
        ];
    }

    /** Registry entry for one table key, or null when unknown. */
    public function config(string $tableKey): ?array
    {
        return $this->tables()[$tableKey] ?? null;
    }

    /**
     * All values of one reference table with their usage counts.
     *
     * @return array<int,array<string,mixed>>
     */
    public function rows(string $tableKey): array
    {
        $config = $this->config($tableKey);
        if ($config === null) {
            return [];
        }

        return Database::all(
            "SELECT l.{$config['id']} AS id, l.{$config['name']} AS name,
                    (SELECT COUNT(*) FROM {$config['usage']['table']} u WHERE u.{$config['usage']['column']} = l.{$config['id']}) AS used,
                    l.created_at, l.updated_at
               FROM {$tableKey} l
              ORDER BY l.{$config['name']}"
        );
    }

    /**
     * Add or update one reference value.
     *
     * @return array{ok:bool, errors: array<string,string>, dbError:?string}
     */
    public function save(string $tableKey, string $action, string $name, int $recordId): array
    {
        $config = $this->config($tableKey);
        if ($config === null) {
            return ['ok' => false, 'errors' => ['_form' => 'Unknown lookup table.'], 'dbError' => null];
        }

        $v = new Validator(['name' => $name]);
        $v->required('name', 'Description')->max('name', 255, 'Description');

        if ($v->passes()) {
            $duplicate = Database::count(
                "SELECT COUNT(*) FROM {$tableKey}
                  WHERE lower({$config['name']}) = lower(:name) AND {$config['id']} <> :id",
                [':name' => $name, ':id' => $recordId]
            );
            if ($duplicate > 0) {
                $v->addError('name', 'That description already exists in this lookup table.');
            }
        }

        if (!$v->passes()) {
            return ['ok' => false, 'errors' => $v->errors(), 'dbError' => null];
        }

        try {
            if ($action === 'add') {
                $this->db->insert($tableKey, [$config['name'] => $name]);
            } elseif ($action === 'update' && $recordId > 0) {
                $this->db->update($tableKey, [$config['name'] => $name], [$config['id'] . '=eq.' . $recordId]);
            }

            return ['ok' => true, 'errors' => [], 'dbError' => null];
        } catch (PDOException $e) {
            error_log('[lookup ' . $action . '] ' . $e->getMessage());
            return ['ok' => false, 'errors' => [], 'dbError' => friendly_db_error($e)];
        }
    }

    /**
     * Delete one reference value (blocked while records still use it).
     *
     * @return array{ok:bool, error:?string}
     */
    public function delete(string $tableKey, int $recordId): array
    {
        $config = $this->config($tableKey);
        if ($config === null || $recordId <= 0) {
            return ['ok' => false, 'error' => 'Invalid lookup value.'];
        }

        $result = $this->db->delete($tableKey, [$config['id'] . '=eq.' . $recordId]);

        return ['ok' => $result['ok'], 'error' => $result['error']];
    }
}
